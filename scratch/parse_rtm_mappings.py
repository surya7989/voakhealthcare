with open("docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md", "r", encoding="utf-8") as f:
    lines = f.readlines()

table_rows = []
for line in lines:
    line = line.strip()
    if line.startswith("|") and "**FR-" in line:
        parts = [p.strip() for p in line.split("|")[1:-1]]
        if len(parts) >= 8:
            goal = parts[0]
            page_mod = parts[1]
            req_id = parts[2]
            user_story = parts[3]
            test_id = parts[7]
            phase = parts[8] if len(parts) > 8 else ""
            table_rows.append({
                "goal": goal,
                "page_mod": page_mod,
                "req_id": req_id,
                "test_id": test_id,
                "phase": phase
            })

print(f"Total parsed table rows: {len(table_rows)}")
for r in table_rows:
    print(f"{r['req_id']:15} | {r['page_mod']:30} | {r['test_id']:15} | {r['phase']}")
