import re

with open("docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md", "r", encoding="utf-8") as f:
    text = f.read()

# Match test ID and related req ID
pattern = re.compile(r'\*\*Canonical Test ID:\*\*\s*`([^`]+)`\s*-\s*\*\*Related Requirement ID:\*\*\s*\[?`?([^`\]\r\n]+)', re.MULTILINE)
matches = pattern.findall(text)
print(f"Total test-to-req matches in 17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md: {len(matches)}")
for t, r in matches:
    print(f"  {t} -> {r}")

with open("docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md", "r", encoding="utf-8") as f:
    req_text = f.read()

req_ids = sorted(list(set(re.findall(r'FR-[A-Z0-9]+-\d{3}', req_text))))
print(f"\nTotal FR IDs in 01_MASTER_FUNCTIONAL_REQUIREMENTS.md: {len(req_ids)}")
print(req_ids)

mapped_reqs = [r for t, r in matches]
print(f"\nUnique mapped reqs in test catalog: {len(set(mapped_reqs))}")
missing_reqs = set(req_ids) - set(mapped_reqs)
print(f"FRs without test mapping: {missing_reqs}")
extra_mapped = set(mapped_reqs) - set(req_ids)
print(f"Mapped reqs not in FR list: {extra_mapped}")
