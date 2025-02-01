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
        genai.configure(api_key='AIzaSyBhqjzqPaPPXSKV7CHrkPEg6I-j13NnR9M')
        self.model = genai.GenerativeModel('gemini-pro')

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

Question Paper: Contains the questions, associated diagrams, formulas, and instructions.
Reference Answer Key: Contains the correct answers, detailed solution steps, alternative acceptable responses, and partial marking guidelines.
Student Answer Sheet: Contains the student’s responses, which might have missing, incorrect, or mismatched question numbers.
Your task is to evaluate the student’s answers by comparing them with the reference answers and the questions. Your evaluation should be robust enough to handle cases where question numbers are missing or mismatched. Use semantic analysis, keyword extraction, and contextual clues (e.g., formulas, diagrams, key phrases) to map and assess the responses.

Instructions:

1. Document Parsing and Data Extraction
Question Paper & Answer Key Extraction:

Extract each question and its details (diagrams, formulas, keywords).
Extract the corresponding reference answers along with any special instructions and partial marking guidelines.
Student Answer Sheet Extraction:

Extract student responses, identifying content, keywords, and any diagrams or formulas.
Do not rely solely on question numbers; if missing or mismatched, map using content-based analysis.
2. Matching Student Responses to Questions
Primary Matching:

Use any provided question numbers to map responses to the corresponding questions.
Content-Based Matching (for missing or mismatched numbers):

Use semantic similarity metrics to map responses based on key phrases, formulas, or diagram references.
Mark such responses as “Matched Based on Content” for reference in the feedback.
Ambiguity Handling:

If multiple possible matches occur or the confidence is lower, choose the best match based on semantic similarity.
Note: Do not penalize scores heavily for low-confidence matching if the content is correct. Adjust scoring so that when the reference answer and student answer align well, the maximum marks are awarded.
3. Answer Evaluation and Scoring
Evaluation Logic for Each Question:
Correctness Check:
Compare the student’s response directly with the reference answer.
Ensure that if the answer fully matches the reference answer (even if provided with a different question number), the full marks are awarded.
Partial Credit (if applicable):
Award partial marks only if the response is missing minor steps or details, according to the reference guidelines.
Use weighted criteria for partial credit so that correct reasoning and final answers yield near-maximum scores.
Confidence Calibration:
If the semantic similarity score is high (above a defined threshold, e.g., 0.85 or as calibrated), treat the response as correct and assign full marks.
For responses with minor deviations (e.g., formatting or labeling issues), deduct only minimal marks.
Feedback Generation:
Provide specific, detailed feedback such as “Fully correct answer – all steps and reasoning are accurate” or “Minor detail missing – awarded partial credit.”
If matched by content rather than number, note “Response matched based on content analysis.”
Important: Ensure that scoring is calibrated so that correct and complete responses are not undervalued. Verify that full correctness leads to full marks unless there are explicitly defined errors.
4. Output Format
For each evaluated question, provide a detailed breakdown:

Question Identifier:
Use the original question number if available, or a system-assigned identifier if matched by content.
Maximum Marks:
Display the total marks available for the question.
Awarded Marks:
Show the marks the student earned after evaluation.
Detailed Feedback:
Provide comments on the strengths of the answer and any specific errors or missing details.
Indicate if the answer was “Matched Based on Content” when applicable.
Final Summary Section:

Total Marks Awarded:
Sum the marks from all evaluated responses.
Percentage Score:
Calculate the percentage based on the total possible marks.
Overall Feedback:
Summarize overall performance, noting areas of strength and any specific improvement suggestions.
Example Output:

yaml
Copy
Edit
Question 1:
  - Maximum Marks: 10
  - Awarded Marks: 10
  - Feedback: "Fully correct answer with all calculation steps and correct final result. Response matched directly with reference answer."

Question 2 (Matched Based on Content):
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