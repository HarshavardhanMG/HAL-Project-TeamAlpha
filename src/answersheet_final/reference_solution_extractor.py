import os
import google.generativeai as genai
import base64
import time
import concurrent.futures
from concurrent.futures import ThreadPoolExecutor
from pdf2image import convert_from_path
import shutil
from queue import Queue
from threading import Lock
from datetime import datetime, timedelta
from PIL import Image
import io
from absl import logging

logging.set_verbosity(logging.ERROR)

class ImageOptimizer:
    @staticmethod
    def optimize_image(image, target_size_kb=500):
        # Start with quality 95
        quality = 95
        img_byte_arr = io.BytesIO()
        
        # Convert to RGB if image is RGBA
        if image.mode == 'RGBA':
            image = image.convert('RGB')
            
        # Resize if image is too large
        max_dimension = 1800
        if max(image.size) > max_dimension:
            ratio = max_dimension / max(image.size)
            new_size = tuple(int(dim * ratio) for dim in image.size)
            image = image.resize(new_size, Image.Resampling.LANCZOS)
        
        # Compress image while maintaining readability
        image.save(img_byte_arr, format='JPEG', quality=quality, optimize=True)
        while img_byte_arr.tell() > target_size_kb * 1024 and quality > 30:
            quality -= 5
            img_byte_arr = io.BytesIO()
            image.save(img_byte_arr, format='JPEG', quality=quality, optimize=True)
            
        return Image.open(img_byte_arr)

class RateLimiter:
    def __init__(self):
        self.request_timestamps = {}
        self.daily_counts = {}
        self.locks = {}
        self.reset_daily_counts()
        
    def reset_daily_counts(self):
        today = datetime.now().date()
        self.daily_counts = {api_id: {'count': 0, 'date': today} for api_id in range(5)}
    
    def can_make_request(self, api_id):
        current_time = time.time()
        today = datetime.now().date()
        
        if api_id not in self.request_timestamps:
            self.request_timestamps[api_id] = []
            self.locks[api_id] = Lock()
            
        with self.locks[api_id]:
            if self.daily_counts[api_id]['date'] != today:
                self.daily_counts[api_id] = {'count': 0, 'date': today}
            
            self.request_timestamps[api_id] = [
                ts for ts in self.request_timestamps[api_id] 
                if current_time - ts < 60
            ]
            
            if (len(self.request_timestamps[api_id]) >= 15 or
                self.daily_counts[api_id]['count'] >= 1500):
                return False
                
            self.request_timestamps[api_id].append(current_time)
            self.daily_counts[api_id]['count'] += 1
            return True

class DocumentProcessor:
    def __init__(self):
        self.api_keys = [
            "AIzaSyBhqjzqPaPPXSKV7CHrkPEg6I-j13NnR9M",
            "AIzaSyCjzP5q9drpBf7dq2-lut4mkpcv2cPXrYo",
            "AIzaSyDFEz3Fsw8YENh-phqcq2Xj0zBhedJ3XyE",
            "AIzaSyAXwnBiaXvNGNiAAiUM48vXoKcppBoU",
            "AIzaSyCWLrLSpAXl-JBc6D6bX5Y6v8URlc11xrM"
        ]
        self.models = self.initialize_apis()
        self.base_dir = os.path.dirname(os.path.abspath(__file__))
        self.temp_dir = os.path.join(self.base_dir, "temp")
        self.output_dir = os.path.join(self.base_dir, "api_outputs")
        self.rate_limiter = RateLimiter()
        self.image_optimizer = ImageOptimizer()
        
    def initialize_apis(self):
        models = []
        generation_config = {
            "temperature": 1,
            "top_p": 0.95,
            "top_k": 40,
            "max_output_tokens": 8192,
        }
        
        for api_key in self.api_keys:
            genai.configure(api_key=api_key)
            model = genai.GenerativeModel(
                model_name="gemini-1.5-flash",
                generation_config=generation_config
            )
            models.append(model)
        return models

    def setup_directories(self):
        for dir_path in [self.temp_dir, self.output_dir]:
            if os.path.exists(dir_path):
                shutil.rmtree(dir_path)
            os.makedirs(dir_path)
        
        chunk_dirs = []
        for i in range(5):
            chunk_dir = os.path.join(self.temp_dir, f'chunk_{i}')
            os.makedirs(chunk_dir)
            chunk_dirs.append(chunk_dir)
        return chunk_dirs

    def convert_pdf(self, pdf_path):
        # Reduced DPI for faster processing while maintaining readability
        images = convert_from_path(pdf_path, dpi=120)
        image_paths = []
        
        for idx, image in enumerate(images):
            # Optimize each image
            optimized_image = self.image_optimizer.optimize_image(image)
            image_path = os.path.join(self.temp_dir, f'page_{idx:03d}.jpg')
            optimized_image.save(image_path, 'JPEG', quality=85)
            image_paths.append(image_path)
            
        return image_paths

    def process_single_image(self, img_path, chunk_index, model):
        while True:  # Keep trying until successful
            try:
                while not self.rate_limiter.can_make_request(chunk_index):
                    time.sleep(1)
                
                with Image.open(img_path) as img:
                    img_base64 = None
                    with io.BytesIO() as bio:
                        img.save(bio, format='JPEG')
                        img_base64 = base64.b64encode(bio.getvalue()).decode()

                prompt = """
                # Reference Solution Content Extraction Prompt

Extract the content from the marking scheme PDF following these structured guidelines:

## 1. Question Identification
- Extract the question number and total marks allocated
- Include section information if specified (e.g., "SECTION A", "SECTION B")
- Preserve any sub-question numbering (a, b, c or i, ii, iii)
- Format as: "Q[number] ([total marks])"

## 2. Expected Answer Components
- Extract all valid answer points listed
- Maintain the hierarchical structure of solutions
- Preserve alternative solutions marked with "OR"
- Include acceptable variations in answers (if mentioned)
- Note any "Award full marks if..." conditions

## 3. Mark Distribution
- Extract mark allocation for each component
- Format partial marks as fractions (e.g., "½ mark")
- Include cumulative marks if shown
- Note any special marking instructions (e.g., "Award 1 mark for...")

## 4. Mathematical Content
- Use LaTeX notation for mathematical expressions
- Preserve equation numbering if present
- Maintain formula relationships and dependencies
- Include units where specified
- Keep all numerical values and calculations

## 5. Diagram Requirements
- Note if diagrams are required
- Extract marking points related to diagrams
- Include any specific diagram features that should be evaluated
- Note marks allocated for diagram components

## 6. Special Instructions
- Include any "Note:" or special consideration statements
- Preserve alternative acceptable answers
- Include any specific conditions for awarding marks
- Note any common mistakes to watch for

## Output Format
```
Section: [Section Name]
Question [Number] ([Total Marks])
Expected Answer:
- [Point 1] ([marks])
- [Point 2] ([marks])
[Alternative Solution if present]
OR
- [Alternative approach] ([marks])

Special Instructions:
- [Any specific marking guidelines]
```

## Extraction Rules
1. Maintain the exact marking scheme structure
2. Preserve all mathematical notations
3. Include all alternative solutions
4. Keep mark allocations precise
5. Preserve any step-by-step solution requirements
6. Note any specific conditions for partial marks
7. Include all acceptable variations of answers
8. Maintain any cross-references between parts
9. Preserve any specific terminology requirements
10. Keep any formula-specific conditions

## Important Considerations
- Extract only content that appears in the marking scheme
- Maintain the original marking hierarchy
- Preserve all marking conditions and exceptions
- Keep mathematical precision requirements
- Include all acceptable alternative approaches
- Note any specific presentation requirements
                """

                response = model.generate_content([
                    prompt,
                    {"mime_type": "image/jpeg", "data": img_base64}
                ])
                
                if not response or not response.text:
                    raise Exception("Empty response received from API")
                
                time.sleep(4)
                return response.text
                
            except Exception as e:
                print(f"Processing failed for {os.path.basename(img_path)}: {str(e)}")
                print("Retrying after delay...")
                time.sleep(10)

    def process_chunk(self, chunk_index, chunk_dir):
        model = self.models[chunk_index]
        results = []
        
        image_files = sorted([f for f in os.listdir(chunk_dir) if f.endswith(('.jpg', '.jpeg', '.png'))])
        print(f"\nAPI {chunk_index + 1} processing {len(image_files)} images")
        
        for img_file in image_files:
            img_path = os.path.join(chunk_dir, img_file)
            print(f"API {chunk_index + 1} -> {img_file}")
            result = self.process_single_image(img_path, chunk_index, model)
            results.append(result)
            
        output_file = os.path.join(self.output_dir, f'api_{chunk_index}_output.txt')
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("\n".join(results))
            
        return chunk_index, output_file

    def distribute_images(self, image_paths, chunk_dirs):
        total_images = len(image_paths)
        images_per_chunk = total_images // 5
        remainder = total_images % 5
        
        start_idx = 0
        for i in range(5):
            chunk_size = images_per_chunk + (1 if i < remainder else 0)
            chunk_images = image_paths[start_idx:start_idx + chunk_size]
            
            for img_path in chunk_images:
                new_path = os.path.join(chunk_dirs[i], os.path.basename(img_path))
                shutil.move(img_path, new_path)
            start_idx += chunk_size

    def process_document(self, pdf_path, output_file):
        try:
            chunk_dirs = self.setup_directories()
            image_paths = self.convert_pdf(pdf_path)
            self.distribute_images(image_paths, chunk_dirs)
            
            with ThreadPoolExecutor(max_workers=5) as executor:
                futures = [
                    executor.submit(self.process_chunk, i, chunk_dir)
                    for i, chunk_dir in enumerate(chunk_dirs)
                ]
                
                completed_chunks = []
                for future in concurrent.futures.as_completed(futures):
                    chunk_index, chunk_file = future.result()
                    completed_chunks.append((chunk_index, chunk_file))
                    
            completed_chunks.sort(key=lambda x: x[0])
            with open(output_file, 'w', encoding='utf-8') as outfile:
                for _, chunk_file in completed_chunks:
                    with open(chunk_file, 'r', encoding='utf-8') as infile:
                        outfile.write(infile.read() + '\n')
                        
            return output_file
            
        finally:
            # Remove temp and api_outputs directories
            for dir_path in [self.temp_dir, self.output_dir]:
                if os.path.exists(dir_path):
                    shutil.rmtree(dir_path)

def main():
    if len(sys.argv) != 3:
        print("Usage: python reference_solution_extractor.py <pdf_path> <output_path>")
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    output_file = sys.argv[2]
    
    processor = DocumentProcessor()
    result_file = processor.process_document(pdf_path, output_file)
    print(f"Processing complete. Output saved to: {result_file}")

if __name__ == "__main__":
    import sys
    main()