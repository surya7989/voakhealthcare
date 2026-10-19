import re, json

files_to_scan = [
    "design/phase-03/index.html",
    "design/phase-03/app.js"
]

keywords = [
    r"250\+",
    r"12\+",
    r"98%",
    r"dedicated\s+account",
    r"dedicated\s+clinical",
    r"clinical\s+advisor",
    r"clinical\s+advisers",
    r"assigned\s+to\s+each\s+facility",
    r"response\s+commitment",
    r"replacement\s+protocol",
    r"emergency\s+replacement",
    r"2-hour",
    r"100%\s+credential",
    r"100%\s+placement",
    r"NDIS",
    r"AHPRA",
    r"NSW",
    r"Sydney",
    r"ABN"
]

results = []

for file_path in files_to_scan:
    with open(file_path, "r", encoding="utf-8") as f:
        lines = f.readlines()
    
    for line_num, line in enumerate(lines, 1):
        for kw in keywords:
            if re.search(kw, line, re.IGNORECASE):
                results.append({
                    "file": file_path,
                    "line": line_num,
                    "pattern": kw,
                    "content": line.strip()
                })

print(f"Total keyword occurrences found: {len(results)}")
for r in results[:40]:
    print(f"{r['file']}:{r['line']} [{r['pattern']}] -> {r['content'][:100]}")

with open("scratch/claims_found.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2)
