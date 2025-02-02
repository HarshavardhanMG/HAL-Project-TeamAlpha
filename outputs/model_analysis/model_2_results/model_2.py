import os
import google.generativeai as genai
import time
from datetime import datetime
from threading import Lock
from absl import logging
from concurrent.futures import ThreadPoolExecutor
import concurrent.futures

logging.set_verbosity(logging.ERROR)

class RateLimiter:
    def __init__(self):
        self.request_timestamps = {}
        self.daily_counts = {}
        self.locks = {}
        self.reset_daily_counts()
        
    def reset_daily_counts(self):
        today = datetime.now().date()
        self.daily_counts = {api_id: {'count': 0, 'date': today} for api_id in range(10)}
    
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
            
            if (len(self.request_timestamps[api_id]) >= 6 or  # 60 requests/min divided by 10 APIs
                self.daily_counts[api_id]['count'] >= 150):    # 1500 daily divided by 10 APIs
                return False
                
            self.request_timestamps[api_id].append(current_time)
            self.daily_counts[api_id]['count'] += 1
            return True

class DocumentProcessor:
    def __init__(self):
        self.api_keys = [
            "insert your api keys here(10)"
        ]
        self.models = self.initialize_apis()
        self.rate_limiter = RateLimiter()
        
    def initialize_apis(self):
        models = []
        generation_config = {
    "temperature": 0.5,   # Moderate randomness
    "top_p": 0.9,         # Slightly wider distribution than Config 1
    "top_k": 50,          # Consider more tokens for diversity
    "max_output_tokens": 8192,
}

        
        for api_key in self.api_keys:
            genai.configure(api_key=api_key)
            models.append(genai.GenerativeModel(
                model_name="learnlm-1.5-pro-experimental",
                generation_config=generation_config
            ))
        return models

    def process_text(self, question_paper_text, answer_key_text, student_text):
        while True:
            try:
                api_id = None
                while api_id is None:
                    for candidate_id in range(10):
                        if self.rate_limiter.can_make_request(candidate_id):
                            api_id = candidate_id
                            break
                    if api_id is None:
                        time.sleep(1)
                
                model = self.models[api_id]
                
                prompt = f"""
                You are an expert exam evaluator. Please evaluate the student's answers based on the following information:

                Question Paper:
                {question_paper_text}

                Answer Key:
                {answer_key_text}

                Student's Answer:
                {student_text}

                Task Overview:

Evaluate student answers by comparing them with the provided reference solutions and the question paper.
Ensure evaluation accuracy even when question numbers are missing or mismatched in the student's answers.
Instructions:

Parse the Documents:
Extract questions and expected answers from the question paper and answer key.
Extract student responses from the student answer sheet, even if the question numbers are incorrect or missing.
Matching Logic:
Use semantic similarity between student responses and reference solutions to match answers to questions.
Prioritize identifying key phrases, calculations, or keywords that align the student's response with the corresponding question.
Handle cases where diagrams or specific terminologies (e.g., formulas, labeled variables) provide clues to identify the relevant question.
Marking Details:

Compare each matched response with the corresponding reference solution:
Content: Verify calculations, logical reasoning, and completeness.
Special Instructions: Apply partial marking rules where specified (e.g., alternative correct answers or diagrams).
Diagrams: Check for accuracy, labeling, and completeness.
Deduct marks for missing or incorrect steps as per the marking scheme.
Highlight ambiguous or partially matched responses for manual review.
Handling Missing or Incorrect Question Numbers:

For responses where the question number is not correctly identified:
Match the response based on its content using contextual clues (e.g., formulas, keywords, diagrams).
Annotate these cases in the output as "Matched Based on Content."
If the AI is unable to confidently match a response, flag it as "Unmatched – Requires Review."
Output Format:

Provide a detailed score breakdown for each question:
Question Number (as identified or matched)
Maximum Marks
Awarded Marks
Feedback (e.g., missing steps, incorrect answer, partially correct solution, unmatched question number).
Summarize the total score and percentage at the end.
Include a section for flagged responses that need manual review.
Additional Features:

Provide a log of all unmatched or ambiguously matched responses for manual verification.
Highlight areas where student answers deviate from expected solutions to help with future evaluations.
                """

                # Debug logging (optional)
                # print("Sending prompt to API:", prompt[:100] + "...")
                
                response = model.generate_content(prompt)
                
                if not response or not response.text:
                    raise Exception("Empty response received from API")
                
                time.sleep(1)
                return response.text
                
            except Exception as e:
                print(f"Processing failed: {str(e)}")
                print("Retrying after delay...")
                time.sleep(10)

    def process_documents(self, question_paper_path, answer_key_path, student_answer_path, output_file):
        try:
            # Read input files
            with open(question_paper_path, 'r', encoding='utf-8') as f:
                question_paper_text = f.read()
            with open(answer_key_path, 'r', encoding='utf-8') as f:
                answer_key_text = f.read()
            with open(student_answer_path, 'r', encoding='utf-8') as f:
                student_text = f.read()
            
            # Process the texts
            result = self.process_text(question_paper_text, answer_key_text, student_text)
            
            # Write result to output file
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(result)
            
            return output_file
            
        except Exception as e:
            print(f"Error processing documents: {str(e)}")
            return None

def main():
    processor = DocumentProcessor()
    # Update paths to be relative to the project root
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    output_dir = os.path.join(base_dir, "model_analysis", "results")
    os.makedirs(output_dir, exist_ok=True)
    
    def process_student(i):
        student_file = os.path.join(base_dir, "model_analysis", f"stu_{i}.txt")
        output_file = os.path.join(output_dir, f"stu_{i}_results.txt")
        
        result_path = processor.process_documents(
            os.path.join(base_dir, "model_analysis", "question_paper.txt"),
            os.path.join(base_dir, "model_analysis", "answer_key.txt"),
            student_file,
            output_file
        )
        return result_path
    
    with ThreadPoolExecutor(max_workers=5) as executor:
        futures = {executor.submit(process_student, i): i for i in range(1, 31)}
        for future in concurrent.futures.as_completed(futures):
            i = futures[future]
            try:
                result_path = future.result()
                print(f"Processed stu_{i}: {result_path}")
            except Exception as e:
                print(f"Error processing stu_{i}: {str(e)}")

if __name__ == "__main__":
    main()