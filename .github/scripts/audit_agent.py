import os
import sys
import requests
from google import genai
from google.genai import types

def get_codebase_context() -> str:
    """Traverses repository files and concatenates code context."""
    included_exts = ('.py', '.js', '.ts', '.php', '.cs', '.rs', '.go', '.c', '.cpp', '.sql')
    excluded_dirs = {'.git', 'node_modules', 'vendor', 'bin', 'obj', 'dist', 'build', '.github'}
    
    context_chunks = []
    
    for root, dirs, files in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in excluded_dirs]
        for file in files:
            if file.endswith(included_exts):
                path = os.path.join(root, file)
                try:
                    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                        context_chunks.append(f"--- START FILE: {path} ---\n{content}\n--- END FILE: {path} ---")
                except Exception as err:
                    print(f"Skipping {path}: {err}", file=sys.stderr)
                    
    return "\n\n".join(context_chunks)

def run_agent():
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("Error: Missing GEMINI_API_KEY environment variable.", file=sys.stderr)
        sys.exit(1)

    client = genai.Client(api_key=api_key)
    codebase = get_codebase_context()

    if not codebase.strip():
        print("No matching source files found to audit.")
        return

    prompt = f"""
You are a senior Application Security Engineer performing a strict vulnerability audit.
Analyze the following source files for security risks (e.g., OWASP Top 10, CWE violations, authentication flaws, injection, insecure deserialization, memory safety bugs).

Rules:
1. If no actionable vulnerabilities are found, respond ONLY with "NO_VULNERABILITIES_FOUND".
2. If vulnerabilities are found, organize by severity (High/Medium/Low).
3. For EVERY vulnerability, you MUST provide the fix in unified diff format:
   - Provide standard diff headers:
     --- a/path/to/file
     +++ b/path/to/file
   - Include 3 lines of context around the changes.
   - Use '-' for removed code and '+' for added code.
   - If the fix requires modifying a large routine, include the full routine in the diff context.
4. Do not include unsolicited stylistic edits.

Codebase context:
{codebase}
"""

    response = client.models.generate_content(
        model="gemini-2.5-flash",
        contents=prompt,
        config=types.GenerateContentConfig(
            temperature=0.1
        )
    )

    report = response.text.strip()
    if report == "NO_VULNERABILITIES_FOUND":
        print("Audit complete: No issues identified.")
        return

    notify_privately(report)

def notify_privately(report: str):
    """Submits the report as a private Security Advisory draft."""
    token = os.environ.get("REPO_TOKEN")
    repo = os.environ.get("GITHUB_REPOSITORY")
    
    if not token or not repo:
        print("Error: Missing REPO_TOKEN or GITHUB_REPOSITORY.", file=sys.stderr)
        sys.exit(1)

    url = f"https://api.github.com/repos/{repo}/security-advisories"
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28"
    }
    
    payload = {
        "summary": "Automated Security Vulnerability Report (Gemini Agent)",
        "description": report,
        "severity": "medium"
    }
    
    res = requests.post(url, headers=headers, json=payload)
    if res.status_code in (200, 201):
        print("Private security advisory draft successfully published.")
    else:
        print(f"Failed to post security advisory ({res.status_code}): {res.text}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    run_agent()