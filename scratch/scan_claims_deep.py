import re

target_files = ['design/phase-03/index.html', 'design/phase-03/app.js']

patterns = [
    (r'\btrusted\b', 'trusted'),
    (r'\bvetted\b', 'vetted'),
    (r'\bverified\b', 'verified'),
    (r'\bzero\s+downtime\b', 'zero downtime'),
    (r'\bguarantee\b', 'guarantee'),
    (r'\baccredited\b', 'accredited'),
    (r'\bproven\b', 'proven'),
    (r'\bseamless\b', 'seamless'),
    (r'\bcompliance\s+handoff\b', 'compliance handoff'),
    (r'\brigorous\b', 'rigorous')
]

for tf in target_files:
    print(f"\n=================== {tf} ===================")
    with open(tf, 'r', encoding='utf-8') as f:
        for idx, line in enumerate(f, 1):
            for pat, name in patterns:
                if re.search(pat, line, re.I):
                    # Check if qualified
                    if '[PROPOSED' in line.upper() or '[PENDING' in line.upper() or '[DEMO' in line.upper() or '[SIMULATED' in line.upper() or '[SUBJECT' in line.upper() or 'DEMO ONLY' in line.upper():
                        continue
                    print(f"Line {idx} [{name}]: {line.strip()[:120]}")
