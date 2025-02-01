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
import sys

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
                Please analyze this image and extract the content as it is written, following these guidelines(if applicable):

1. Question Structure:
- Start each answer with "Question [number]:" on a new line
- For multi-part questions, use "[letter])" format (e.g., "a)", "b)")
- Preserve all subquestion numbering as written (i, ii, iii or 1, 2, 3)
- Include the marks allocation if shown (e.g., "[2 marks]")

2. Mathematical Content:
- Use LaTeX notation for all mathematical expressions
- Preserve equation alignments and numbering
- Use proper subscripts and superscripts

3. Diagram Analysis:
- Begin with "Diagram Description:" on a new line (if the particular answer conatains a diagram or graph)
- List key components/elements in a structured manner
- Note any significant markings, angles, or measurements
- Describe any arrows, direction indicators, or field lines

4. Content Organization:
- Preserve paragraph breaks as written
- Maintain step-by-step solutions with clear numbering
- Use consistent indentation for related content
- Separate different solution approaches with horizontal lines
- Mark any given conditions or data clearly

6. Answer Validation:
- Check for completeness of all parts
- Verify mathematical consistency
- Ensure all symbols are properly defined
- Confirm units are consistently used
- Validate numerical calculations

Output Format Requirements:
- Use clear line breaks between questions
- Maintain consistent indentation
- Use proper markdown formatting
- Preserve mathematical notation integrity
- Include all steps of calculations
- Mark the final answer clearly

Key Considerations:
-Extract text exactly as written in the image.
-Maintain the original problem-solving sequence.
-Ensure all parts of the answer are extracted accurately.
-Describe diagrams comprehensively.
-Preserve the logical flow of the solution.
-Do not add any extra comments or explanations beyond what is explicitly written in the image.
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
    if len(sys.argv) != 2:
        print("Usage: python answer_sheet_extractor.py <pdf_path>")
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    processor = DocumentProcessor()
    output_file = processor.process_document(pdf_path, "student_answer.txt")
    print(f"Text output saved to: {output_file}")

if __name__ == "__main__":
    main()