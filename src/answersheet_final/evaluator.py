import os
import google.generativeai as genai
import time
from datetime import datetime
from threading import Lock
from absl import logging
import sys
import json
import io

# Suppress gRPC warnings before initializing anything else
os.environ['GRPC_DEFAULT_SSL_ROOTS_FILE_PATH'] = '1'  # Dummy value to bypass check
logging.set_verbosity(logging.ERROR)  # Set before any other imports

# Fix encoding for Windows systems
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

class RateLimiter:
    def __init__(self):
        self.request_timestamps = []
        self.daily_count = {'count': 0, 'date': datetime.now().date()}
        self.lock = Lock()

    def can_make_request(self):
        with self.lock:
            now = datetime.now()
            if now.date() != self.daily_count['date']:
                self.daily_count = {'count': 0, 'date': now.date()}
            if self.daily_count['count'] >= 50:
                return False
            self.request_timestamps = [
                t for t in self.request_timestamps 
                if (now - t).total_seconds() < 60
            ]
            if len(self.request_timestamps) >= 5:
                return False
            return True

    def add_request(self):
        with self.lock:
            self.request_timestamps.append(datetime.now())
            self.daily_count['count'] += 1

class DocumentProcessor:
    def __init__(self):
        self.rate_limiter = RateLimiter()
        genai.configure(api_key='insert your api key here')
        generation_config = {
            "temperature": 0.7,
            "top_p": 1.0,
            "top_k": 50,
            "max_output_tokens": 10240,
        }
        self.model = genai.GenerativeModel(
            model_name="learnlm-1.5-pro-experimental",
            generation_config=generation_config
        )

    def process_text(self, question_paper_text, answer_key_text, student_text):
        max_retries = 3
        retry_count = 0
        
        while retry_count < max_retries:
            try:
                while not self.rate_limiter.can_make_request():
                    time.sleep(1)
                
                prompt = f"""
                You are an expert exam evaluator. Please evaluate the student's answers based on:

                Question Paper:
                {question_paper_text}

                Answer Key:
                {answer_key_text}

                Student's Answer:
                {student_text}

                You are provided with three documents:

Question Paper: Contains the questions, marks assigned to each question and the maximum marks for which the students writes the exam. 
Reference Answer Key: Contains the correct answers, detailed solution steps, alternative acceptable responses, and partial marking guidelines.
Student Answer Sheet: Contains the student's responses, which might have missing, incorrect, or mismatched question numbers.
Your task is to evaluate the student's answers by comparing them with the reference answers and the questions. Your evaluation should be robust enough to handle cases where question numbers are missing or mismatched. Use semantic analysis, keyword extraction, and contextual clues (e.g., formulas, diagrams, key phrases) to map and assess the responses.

Instructions:
Do not rely solely on question numbers; if missing or mismatched, map using content-based analysis.
Note: Do not penalize scores heavily for low-confidence matching if the content is correct. Adjust scoring so that when the reference answer and student answer align well, the maximum marks are awarded.
refer the question paper for the question structures and marks assigned to each question, and if there are any choice questions.
3. Answer Evaluation and Scoring
Evaluation Logic for Each Question:
Correctness Check:
Compare the student's response directly with the reference answer.
Partial Credit (if applicable):
Award partial marks if the response is missing minor steps or details, according to the reference guidelines.
Use weighted criteria for partial credit so that correct reasoning and final answers yield near-maximum scores.
For responses with minor deviations (e.g., formatting or labeling issues), deduct only minimal marks.
Feedback Generation:
Provide specific, detailed feedback such as "Fully correct answer – all steps and reasoning are accurate" or "Minor detail missing – awarded partial credit."
4. Output Format
For each evaluated question, provide a detailed breakdown:
Question Identifier:
Maximum Marks:
Display the total marks available for the question.
Awarded Marks:
Show the marks the student earned after evaluation.
Detailed Feedback:
Provide comments on the strengths of the answer and any specific errors or missing details.
Indicate if the answer was "Matched Based on Content" when applicable.
Final Summary Section:

Total Marks Awarded:
Sum the marks from all evaluated responses.
Percentage Score:
Calculate the percentage based on the total possible marks.
Overall Feedback:
Summarize overall performance, noting areas of strength and any specific improvement suggestions.
Example Output:
Question 1:
  - Maximum Marks: 10
  - Awarded Marks: 10
  - Feedback: "Fully correct answer with all calculation steps and correct final result. Response matched directly with reference answer."

Question 2:
  - Maximum Marks: 15
  - Awarded Marks: 15
  - Feedback: "The answer demonstrates a thorough understanding and correct application of the concept. Minor differences in phrasing did not affect correctness."

Question 3:
  - Maximum Marks: 5
  - Awarded Marks: 5
  - Feedback: "Complete and accurate answer. All key components are present, and the reasoning is sound."
  
Final Score:
  - Total Marks Awarded: 30/30
  - Percentage: 100%
  - Overall Feedback: "Excellent performance with all answers correct. Clear reasoning and accurate use of diagrams and calculations throughout."

make sure all the answers are evaluated and scored correctly.you must evaluate all the answers,as there there will be no manual evaluation or supervision the marks given by you is the final.
and make sure the alloted marks is out of the maximum marks of the question paper (i.e: if the maximum marks of the question paper is 70, then the total marks alloted is for 70).
                """
                
                response = self.model.generate_content(prompt)
                self.rate_limiter.add_request()
                return response.text

            except Exception as e:
                retry_count += 1
                if retry_count >= max_retries:
                    raise
                time.sleep(2 ** retry_count)

def main():
    try:
        sys.stdin.reconfigure(encoding='utf-8')
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')

        if len(sys.argv) != 4:
            error_msg = json.dumps({
                'error': "Usage: python evaluator.py <question_paper> <answer_key> <student_answer>",
                'status': 'failed'
            })
            print(error_msg)
            sys.exit(1)

        paths = {
            'question_paper': sys.argv[1],
            'answer_key': sys.argv[2],
            'student_answer': sys.argv[3]
        }

        # Validate files
        contents = {}
        for key, path in paths.items():
            if not os.path.exists(path):
                raise FileNotFoundError(f"Missing {key.replace('_', ' ')} file")
            with open(path, 'r', encoding='utf-8') as f:
                contents[key] = f.read()
                if not contents[key]:
                    raise ValueError(f"Empty {key.replace('_', ' ')} file")

        processor = DocumentProcessor()
        evaluation_result = processor.process_text(
            contents['question_paper'],
            contents['answer_key'],
            contents['student_answer']
        )

        if evaluation_result:
            print(evaluation_result.encode('utf-8', 'replace').decode('utf-8').strip())
            sys.stdout.flush()
            return 0
        else:
            raise ValueError("Empty evaluation result")

    except Exception as e:
        error_msg = json.dumps({
            'error': f"Evaluation failed: {str(e)}",
            'status': 'failed'
        })
        print(error_msg)
        return 1

if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)