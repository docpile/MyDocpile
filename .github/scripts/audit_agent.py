import os
import sys
import time
import requests
from google import genai
from google.genai import types

def collect_source_files() -> list[str]:
    """Collects repository source files, excluding minified and vendor assets."""
    included_exts = ('.py', '.js', '.ts', '.php', '.cs', '.rs', '.go', '.c', '.cpp', '.sql', '.html')
    excluded_dirs = {'.git', 'node_modules', 'vendor', 'bin', 'obj', 'dist', 'build', '.github', 'assets', 'lib', 'static'}
    
    matched_files = []
    
    for root, dirs, files in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in excluded_dirs]
        for file in files:
            if file.endswith(('.min.js', '.min.css', 'pdf.worker.js', 'ace.js')):
                continue
            if file.endswith(included_exts):
                matched_files.append(os.path.join(root, file))
                
    return matched_files

def chunk_files(file_paths: list[str], max_chars: int = 120_000):
    """Yields batches of files that keep input size well below the 250k token limit."""
    batch = []
    batch_chars = 0
    for path in file_paths:
        try:
            size = os.path.getsize(path)
            if size > max_chars:
                continue  # Skip unusually large compiled/blob files
            if batch_chars + size > max_chars and batch:
                yield batch
                batch = []
                batch_chars = 0
            batch.append(path)
            batch_chars += size
        except OSError:
            continue
    if batch:
        yield batch

def run_agent():
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("Error: Missing GEMINI_API_KEY environment variable.", file=sys.stderr)
        sys.exit(1)

    client = genai.Client(api_key=api_key)
    file_paths = collect_source_files()

    if not file_paths:
        print("No matching source files found to audit.")
        return

    all_reports = []
    batches = list(chunk_files(file_paths))
    print(f"Processing {len(file_paths)} files across {len(batches)} batch(es)...")

    for idx, batch in enumerate(batches, start=1):
        print(f"Auditing batch {idx}/{len(batches)} ({len(batch)} files)...")
        context_chunks = []
        for path in batch:
            try:
                with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                    context_chunks.append(f"--- START FILE: {path} ---\n{f.read()}\n--- END FILE: {path} ---")
            except Exception as err:
                print(f"Skipping {path}: {err}", file=sys.stderr)

        batch_context = "\n\n".join(context_chunks)
        if not batch_context.strip():
            continue

        prompt = f"""
You are a senior Application Security Engineer performing a strict vulnerability audit.
Analyze the source files for security risks (OWASP Top 10, SQL/XSS injection, path traversal, authentication bypass, CSRF, insecure deserialization).

Rules:
1. If no actionable vulnerabilities are found, reply ONLY with 'NO_VULNERABILITIES_FOUND'.
2. For EVERY vulnerability found, provide the remediation strictly in unified diff format:
   --- a/path/to/file
   +++ b/path/to/file
   Include 3 lines of surrounding context.
   Use '-' for removed lines and '+' for added lines.
3. Do not include stylistic or cosmetic refactors.

Files:
{batch_context}
"""
        try:
            response = client.models.generate_content(
                model="gemini-2.5-flash",
                contents=prompt,
                config=types.GenerateContentConfig(temperature=0.1)
            )
            output = response.text.strip()
            if output and output != "NO_VULNERABILITIES_FOUND":
                all_reports.append(output)
        except Exception as api_err:
            print(f"Batch {idx} failed: {api_err}", file=sys.stderr)

        # Brief pause between batches to respect free-tier per-minute quotas
        if idx < len(batches):
            time.sleep(15)

    if not all_reports:
        print("Audit complete: No vulnerabilities identified.")
        return

    notify_privately("\n\n---\n\n".join(all_reports))

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