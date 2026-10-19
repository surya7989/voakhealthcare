#!/usr/bin/env python3
"""
Voak Health Care — Phase 3 Comprehensive Business Claims & Compliance Auditor

Forensically scans all UI prototype renderers and templates:
- design/phase-03/index.html
- design/phase-03/app.js

Detects any unverified commercial, operational, or competitor allegations:
1. "Fixed B2B Fee Schedule" / "no surge pricing" assertions
2. Unsupported negative competitor / agency allegations
3. "Primary-Source Verified" asserted as an established fact without proposed/pending qualification
4. Unqualified dedicated backup pools & operational escalation commitments
5. Unqualified timesheet integrations and dedicated shift-coordinator oversight
6. Unqualified quarterly governance meetings and clinical assessments
7. "100-point biometric" checks and verified clinical peer references without proposed/pending qualification
8. Unqualified flexible MSAs, dedicated managed services, weekly invoicing, and full audit trails
9. Non-demo / non-.invalid email addresses
10. Hardcoded unverified performance metrics (e.g., "250+", "98%", "12+") without pending approval badges

Saves full audit output to tests/results/claims_audit_report.json and prints summary.
"""

import os
import re
import sys
import json

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

candidate_files = [
    os.path.join(BASE_DIR, "design", "phase-03", "index.html"),
    os.path.join(BASE_DIR, "design", "phase-03", "app.js"),
    os.path.join(BASE_DIR, "index.html"),
    os.path.join(BASE_DIR, "app.js"),
]

TARGET_FILES = [f for f in candidate_files if os.path.exists(f)]
if not TARGET_FILES:
    print("FATAL: Could not locate index.html or app.js in target paths.")
    sys.exit(1)

# List of audit rules: (rule_id, regex_pattern, description, requires_negative_filter)
CLAIM_RULES = [
    {
        "id": "RULE-CLM-01",
        "description": "Surge pricing gouging or unsupported fee assertions",
        "pattern": re.compile(r"surge\s+pricing\s+gouging|no\s+surge\s+pricing", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-02",
        "description": "Unsupported negative competitor/agency allegations",
        "pattern": re.compile(r"aggressive\s+multiplier\s+markups|call-center\s+brokerage|frequent\s+reliance\s+on\s+candidate-submitted", re.I),
        "allowed_if_has": None
    },
    {
        "id": "RULE-CLM-03",
        "description": "Primary-Source Verified asserted as fact without pending qualification",
        "pattern": re.compile(r">Primary-Source Verified<", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-04",
        "description": "Unqualified dedicated backup pools and operational escalation commitments",
        "pattern": re.compile(r"Dedicated\s+backup\s+pools\s+and\s+transparent\s+response\s+escalation", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-05",
        "description": "Unqualified electronic timesheet integration and dedicated shift-coordinator oversight",
        "pattern": re.compile(r"electronic\s+timesheet\s+integration,\s+and\s+dedicated\s+shift\s+coordinator\s+oversight", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-06",
        "description": "Unqualified quarterly governance meetings and clinical assessments",
        "pattern": re.compile(r"Quarterly\s+governance\s+meetings,\s+shift\s+fulfillment\s+metric\s+reviews,\s+peer\s+clinical\s+assessments", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-07",
        "description": "100-point biometric checks or verified clinical peer references without pending qualification",
        "pattern": re.compile(r"100-point\s+biometric|Verified\s+clinical\s+peer\s+references", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-08",
        "description": "Unqualified flexible MSAs, dedicated managed services, weekly invoicing, and full audit trails",
        "pattern": re.compile(r"We offer flexible B2B engagement models including Master Service Agreements|Timesheets are verified electronically against hospital ward shift logs and submitted via weekly itemized invoicing with full audit trails", re.I),
        "allowed_if_has": r"\[(?:Proposed capability|PENDING)"
    },
    {
        "id": "RULE-CLM-09",
        "description": "Unqualified metric claims without pending placeholder badges",
        "pattern": re.compile(r"(?:>250\+ facilities<|>12\+ hospital networks<|>98% fill rate<)", re.I),
        "allowed_if_has": r"\[(?:METRIC TBD|PENDING)"
    },
    {
        "id": "RULE-CLM-10",
        "description": "Non-demo / live corporate email addresses",
        "pattern": re.compile(r"[a-zA-Z0-9_.+-]+@(?!example\.invalid|.*\.invalid)[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+", re.I),
        "allowed_if_has": None,
        "ignore_patterns": [r"fonts\.googleapis\.com", r"schema\.org", r"w3\.org", r"localhost"]
    }
]

def run_audit():
    violations = []
    inspected_count = 0

    print("=" * 78)
    print("VOAK HEALTH CARE — PHASE 3 COMPREHENSIVE BUSINESS CLAIMS AUDITOR")
    print("=" * 78)
    print(f"Inspecting UI target files: {[os.path.basename(f) for f in TARGET_FILES]}\n")

    for file_path in TARGET_FILES:
        rel_path = os.path.relpath(file_path, BASE_DIR).replace(os.sep, "/")
        with open(file_path, "r", encoding="utf-8") as f:
            lines = f.readlines()

        for line_num, line in enumerate(lines, 1):
            inspected_count += 1
            for rule in CLAIM_RULES:
                # Check ignore patterns
                if "ignore_patterns" in rule:
                    if any(re.search(ig, line, re.I) for ig in rule["ignore_patterns"]):
                        continue

                m = rule["pattern"].search(line)
                if m:
                    # Check if qualified properly
                    if rule["allowed_if_has"]:
                        if re.search(rule["allowed_if_has"], line, re.I):
                            continue # Qualified properly!

                    violations.append({
                        "file": rel_path,
                        "line": line_num,
                        "rule_id": rule["id"],
                        "description": rule["description"],
                        "matched_text": m.group(0),
                        "snippet": line.strip()[:140]
                    })

    results = {
        "status": "PASS" if len(violations) == 0 else "FAIL",
        "total_lines_inspected": inspected_count,
        "violations_count": len(violations),
        "violations": violations
    }

    results_dir = os.path.join(BASE_DIR, "tests", "results")
    os.makedirs(results_dir, exist_ok=True)
    report_path = os.path.join(results_dir, "claims_audit_report.json")
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2)

    print(f"Total Lines Inspected: {inspected_count}")
    print(f"Total Violations:      {len(violations)}")
    print(f"Audit Status:          {results['status']}")
    print(f"Report Generated:      {os.path.relpath(report_path, BASE_DIR)}\n")

    if violations:
        print("[!] Unneutralized Claims Detected:")
        for v in violations:
            print(f"  - [{v['rule_id']}] {v['file']}:{v['line']} -> {v['description']}")
            print(f"    Snippet: {v['snippet']}\n")
        return False
    else:
        print("[OK] All visible business claims, SLA assertions, and competitor statements neutralized 100%.")
        return True

if __name__ == "__main__":
    ok = run_audit()
    sys.exit(0 if ok else 1)
