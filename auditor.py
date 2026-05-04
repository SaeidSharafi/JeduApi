import os
import subprocess
import json
from crewai import Agent, Task, Crew, Process, LLM

def get_windows_host_ip():
    """Detects the Windows Host IP from WSL2."""
    try:
        route = subprocess.check_output("ip route show | grep default", shell=True).decode()
        return route.split()[2]
    except Exception:
        return "127.0.0.1"

# --- CONFIGURATION ---
WINDOWS_IP = get_windows_host_ip()
API_URL = f"http://{WINDOWS_IP}:8080/v1"
custom_llm = LLM(
    model="openai/local-model",
    base_url=API_URL,
    api_key="NA"
)
print(f"Connecting to AI Server at: {API_URL}")

# --- PRE-PROCESSING SONAR DATA ---
def get_top_security_issues(filepath):
    if not os.path.exists(filepath):
        raise FileNotFoundError(f"Error: {filepath} not found.")
    with open(filepath, 'r', encoding='utf-8') as f:
        data = json.load(f)
    issues = data.get('issues', [])
    
    # Define security-related rules
    security_rules = ['S2077', 'S2245', 'S3649', 'S3776', 'S5332', 'S5333', 'S5334', 'S5335', 'S5336', 'S5337', 'S5338', 'S5339', 'S5340', 'S5341', 'S5342', 'S5343', 'S5344', 'S5345', 'S5346', 'S5347', 'S5348', 'S5349', 'S5350', 'S5351', 'S5352', 'S5353', 'S5354', 'S5355', 'S5356', 'S5357', 'S5358', 'S5359', 'S5360', 'S5361', 'S5362', 'S5363', 'S5364', 'S5365', 'S5366', 'S5367', 'S5368', 'S5369', 'S5370', 'S5371', 'S5372', 'S5373', 'S5374', 'S5375', 'S5376', 'S5377', 'S5378', 'S5379', 'S5380', 'S5381', 'S5382', 'S5383', 'S5384', 'S5385', 'S5386', 'S5387', 'S5388', 'S5389', 'S5390', 'S5391', 'S5392', 'S5393', 'S5394', 'S5395', 'S5396', 'S5397', 'S5398', 'S5399', 'S5400']
    
    # Filter for security rules and high severity
    filtered_issues = [
        issue for issue in issues
        if issue.get('severity') in ['CRITICAL', 'BLOCKER', 'MAJOR']
        and any(rule in issue.get('rule', '') for rule in security_rules)
    ]
    
    # Sort by severity
    severity_order = {'BLOCKER': 1, 'CRITICAL': 2, 'MAJOR': 3}
    filtered_issues.sort(key=lambda x: severity_order.get(x.get('severity'), 4))
    
    # Take top 5
    top_5 = filtered_issues[:5]
    
    # Format nicely
    formatted_issues = []
    for i, issue in enumerate(top_5, 1):
        formatted_issues.append({
            "index": i,
            "severity": issue['severity'],
            "rule": issue['rule'],
            "file": issue['component'].split(':')[-1],
            "line": issue['line'],
            "message": issue.get('message', 'No message provided')
        })
    return json.dumps(formatted_issues, indent=2)

# Load Sonar Data
try:
    sonar_data_json = get_top_security_issues("sonar_report.json")
    print("Top 5 Security Issues Extracted from Sonar Report.")
except Exception as e:
    print(f"Error processing sonar_report.json: {e}")
    exit(1)

# --- LOAD AND CHUNK CODEBASE SUMMARY ---
codebase_md_path = "codebase_summary.md"
if not os.path.exists(codebase_md_path):
    raise FileNotFoundError(f"Error: {codebase_md_path} not found.")

with open(codebase_md_path, 'r', encoding='utf-8') as f:
    raw_summary = f.read()

# Simple chunking: Split by file headers if possible, or just split into smaller strings
# Since it's markdown, we can split by '##' headers or just take the first N lines per file
# For simplicity, let's assume the summary is structured by file. 
# We will create a list of chunks. If the file is huge, we might need a smarter splitter.
# Here, we'll just split the text into chunks of ~4000 tokens (approx 3000 words) to stay safe.
# Note: You may need to adjust the split logic based on how codebase_summary.md is formatted.

def split_text_by_size(text, max_size=3000):
    # Simple word-based split
    words = text.split()
    chunks = []
    current_chunk = []
    current_size = 0
    
    for word in words:
        current_chunk.append(word)
        current_size += 1
        if current_size >= max_size:
            chunks.append(" ".join(current_chunk))
            current_chunk = []
            current_size = 0
            
    if current_chunk:
        chunks.append(" ".join(current_chunk))
    return chunks

codebase_chunks = split_text_by_size(raw_summary, max_size=2500) # ~2500 words per chunk
print(f"Codebase summary split into {len(codebase_chunks)} chunks.")

# --- AGENTS ---
scout = Agent(
    role='Laravel Security Expert',
    goal='Analyze pre-extracted SonarQube security issues.',
    backstory="""You are a specialist in Laravel security. You understand Eloquent and PHP pitfalls.""",
    llm=custom_llm,
    verbose=True
)

# Updated Librarian to take a specific chunk
librarian = Agent(
    role='Laravel Architect',
    goal='Extract relevant code context from the codebase summary chunk.',
    backstory="""You know the Laravel directory structure. You find code in the provided codebase summary chunk.""",
    llm=custom_llm,
    verbose=True
)

validator = Agent(
    role='Laravel Security Validator',
    goal='Validate whether SonarQube issues are real or false positives in Laravel context.',
    backstory="""You deeply understand Laravel internals.
You reject:
- False SQL injection warnings on Eloquent
- CSRF issues when middleware is present
- Any incorrect framework assumptions
You output:
- VALID or FALSE POSITIVE
- Short reasoning
""",
    llm=custom_llm,
    verbose=True
)

reviewer = Agent(
    role='Senior Laravel Developer',
    goal='Write ONLY Laravel-aware, correct security fixes.',
    backstory="""You are a Laravel expert.
Rules you MUST follow:
- Eloquent queries are safe from SQL injection unless raw queries are used.
- Laravel includes CSRF protection via middleware by default.
- Do NOT suggest generic PHP fixes if Laravel already handles it.
- If SonarQube is wrong, explicitly say it's a false positive.
- Prefer Laravel-native solutions (validation, policies, storage, etc).
You must validate each issue before proposing a fix.
""",
    llm=custom_llm,
    verbose=True
)

# --- TASKS ---

# Task 1: Scout identifies issues (Unchanged)
task_sonar_analysis = Task(
    description=f"""
    Here are the top 5 pre-extracted security issues from the SonarQube report.
    Analyze them and confirm their severity.
    ISSUES:
    {sonar_data_json}
    Task: List these 5 issues with their file paths and line numbers.
    """,
    expected_output="A list of 5 prioritized security issues with file paths and line numbers.",
    agent=scout
)

# Task 2: Librarian retrieves context for ONE chunk at a time
# We will iterate over chunks later in the crew execution
def get_context_for_issue(issue_index, chunk_text):
    return f"""
    Search the following codebase summary chunk for the code at the file paths identified in the previous step.
    ISSUE TO FIND: {issue_index}
    CODEBASE SUMMARY CHUNK:
    {chunk_text}
    Task: Find and extract the relevant code snippets for the problematic areas. If not found in this chunk, return 'NOT FOUND'.
    """

# Task 3: Validate (Unchanged)
task_validate = Task(
    description="""
    Validate each Sonar issue for Laravel correctness.
    Check if the issue is a real vulnerability or a false positive in the context of Laravel.
    """,
    expected_output="Each issue marked VALID or FALSE POSITIVE with reasoning.",
    agent=validator
)

# Task 4: Final Audit (Unchanged)
task_final_audit = Task(
    description="""
    Analyze snippets and Sonar errors. Write a detailed report with 'Before' and 'After' code.
    """,
    expected_output="A comprehensive Markdown report.",
    agent=reviewer,
    output_file='LARAVEL_AUDIT_REPORT.md'
)

# --- CREW ---
# Note: CrewAI doesn't natively support "looping" tasks easily in sequential process without custom code.
# To keep it simple, we will use the first chunk for demonstration. 
# In a real scenario, you might want to use a 'hierarchical' process or custom tooling.

# For now, let's just use the first chunk to avoid the context error.
# If you have many chunks, you might need to split the crew into multiple runs.

first_chunk = codebase_chunks[0]

task_context_retrieval = Task(
    description=get_context_for_issue("All Issues", first_chunk),
    expected_output="Full code snippets for each problematic area.",
    agent=librarian
)

laravel_audit_crew = Crew(
    agents=[scout, librarian, validator, reviewer],
    tasks=[task_sonar_analysis, task_context_retrieval, task_validate, task_final_audit],
    process=Process.sequential,
    verbose=True,
    telemetry_enabled=False
)

if __name__ == "__main__":
    print("Starting Laravel Agentic Audit...")
    try:
        laravel_audit_crew.kickoff()
    except Exception as e:
        print(f"Audit failed: {e}")