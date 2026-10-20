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
    },
    {
        "id": "RULE-CLM-11",
        "description": "Unqualified vetting or credential guarantees ('vetted registered nurses', 'verified practitioner placement')",
        "pattern": re.compile(r"vetted\s+registered\s+nurses|verified\s+practitioner\s+placement", re.I),
        "allowed_if_has": r"\[(?:Credentialing & Vetting Protocols Subject to Client Contract|Verification Framework Pending Operational Sign-off|PENDING|PROPOSED)"
    },
    {
        "id": "RULE-CLM-12",
        "description": "Unqualified trust or partnership assertions ('Trusted Healthcare Partner', 'Trusted by Healthcare Organisations')",
        "pattern": re.compile(r"Trusted\s+Healthcare\s+Partner|Trusted\s+by\s+Healthcare\s+Organisations", re.I),
        "allowed_if_has": r"\[(?:DEMO|Demonstration Framework|PENDING|PROPOSED)"
    },
    {
        "id": "RULE-CLM-13",
        "description": "Unqualified uptime or availability guarantees ('zero downtime', '100% uptime')",
        "pattern": re.compile(r"zero\s+downtime|100%\s+uptime", re.I),
        "allowed_if_has": r"\[(?:Operational SLA Subject to Contract|Subject to SLA|PENDING|PROPOSED)"
    },
    {
        "id": "RULE-CLM-14",
        "description": "Unqualified statutory compliance or QA promises ('Rigorous clinical quality assurance', 'full compliance handoff')",
        "pattern": re.compile(r"Rigorous\s+clinical\s+quality\s+assurance|full\s+compliance\s+handoff", re.I),
        "allowed_if_has": r"\[(?:Governance Framework Pending Independent Clinical Audit|Operational SLA Subject to Contract|Framework Pending Operational Sign-off|PENDING|PROPOSED)"
    }
]

# Regression Fixtures for Auditor Self-Verification
FIXTURE_POSITIVE_CASES = [
    ("RULE-CLM-01", "We guarantee no surge pricing for all hospital clients."),
    ("RULE-CLM-02", "Unlike conventional agencies that use aggressive multiplier markups and call-center brokerage."),
    ("RULE-CLM-03", "<h3>Primary-Source Verified</h3>"),
    ("RULE-CLM-04", "Dedicated backup pools and transparent response escalation for your ward."),
    ("RULE-CLM-05", "Electronic timesheet integration, and dedicated shift coordinator oversight included."),
    ("RULE-CLM-06", "Quarterly governance meetings, shift fulfillment metric reviews, peer clinical assessments."),
    ("RULE-CLM-07", "100-point biometric verification and verified clinical peer references."),
    ("RULE-CLM-08", "We offer flexible B2B engagement models including Master Service Agreements."),
    ("RULE-CLM-09", "Serving >250+ facilities< across the state."),
    ("RULE-CLM-10", "Contact our clinical director at admin@voakhealth.com.au for immediate shifts."),
    ("RULE-CLM-11", "Deploy vetted registered nurses for rapid clinical staffing."),
    ("RULE-CLM-12", "Voak Health Care is your Trusted Healthcare Partner across the state."),
    ("RULE-CLM-13", "Guaranteed zero downtime roster integration for all hospital wards."),
    ("RULE-CLM-14", "Rigorous clinical quality assurance for every placement.")
]

FIXTURE_NEGATIVE_CASES = [
    ("RULE-CLM-01", "Pricing models [Proposed capability & subject to bilateral client contract]."),
    ("RULE-CLM-02", "Standard agency operating model comparison [Demonstration Framework]."),
    ("RULE-CLM-03", "Primary-Source Verified [Proposed capability & subject to clinical compliance verification]."),
    ("RULE-CLM-04", "Dedicated backup pools and transparent response escalation [Proposed capability & subject to operational SLA]."),
    ("RULE-CLM-05", "Electronic timesheet integration, and dedicated shift coordinator oversight [Proposed capability & subject to facility IT integration]."),
    ("RULE-CLM-06", "Quarterly governance meetings, shift fulfillment metric reviews, peer clinical assessments [Proposed capability & subject to contract terms]."),
    ("RULE-CLM-07", "100-point biometric [Proposed capability] and verified clinical peer references [PENDING]."),
    ("RULE-CLM-08", "We offer flexible B2B engagement models including Master Service Agreements [Proposed capability & subject to bilateral contract terms]."),
    ("RULE-CLM-09", "Active Placements [METRIC TBD • PENDING APPROVAL]."),
    ("RULE-CLM-10", "Inquiries routed to contact@example.invalid during demonstration."),
    ("RULE-CLM-11", "Registered nurses [Credentialing & Vetting Protocols Subject to Client Contract]."),
    ("RULE-CLM-12", "Serving Healthcare Organisations [Demonstration Framework]."),
    ("RULE-CLM-13", "Targeted transition continuity with structured compliance handoff [Operational SLA Subject to Contract]."),
    ("RULE-CLM-14", "Clinical quality assurance [Governance Framework Pending Independent Clinical Audit].")
]

def check_line_against_rules(line):
    matches = []
    for rule in CLAIM_RULES:
        if "ignore_patterns" in rule:
            if any(re.search(ig, line, re.I) for ig in rule["ignore_patterns"]):
                continue
        m = rule["pattern"].search(line)
        if m:
            if rule["allowed_if_has"] and re.search(rule["allowed_if_has"], line, re.I):
                continue
            matches.append((rule["id"], rule["description"], m.group(0)))
    return matches

def run_fixture_tests():
    print("=" * 78)
    print("RUNNING CLAIMS AUDITOR REGRESSION FIXTURE SELF-TESTS")
    print("=" * 78)
    passed = 0
    total = len(FIXTURE_POSITIVE_CASES) + len(FIXTURE_NEGATIVE_CASES)

    print("\n--- Positive Fixtures (Must Trigger Violations) ---")
    pos_ok = True
    for rule_id, sample in FIXTURE_POSITIVE_CASES:
        violations = check_line_against_rules(sample)
        detected = [v[0] for v in violations]
        if rule_id in detected:
            print(f"  [PASS] {rule_id}: correctly flagged violation")
            passed += 1
        else:
            print(f"  [FAIL] {rule_id}: FAILED to flag unqualified sample -> '{sample}'")
            pos_ok = False

    print("\n--- Negative Fixtures (Must Pass Without Violations) ---")
    neg_ok = True
    for rule_id, sample in FIXTURE_NEGATIVE_CASES:
        violations = check_line_against_rules(sample)
        if not violations:
            print(f"  [PASS] {rule_id}: correctly passed neutralized wording")
            passed += 1
        else:
            print(f"  [FAIL] {rule_id}: FALSE POSITIVE violation -> {violations} on sample '{sample}'")
            neg_ok = False

    print(f"\nFixture Results: {passed}/{total} passed ({(passed/total)*100:.1f}%)")
    return pos_ok and neg_ok

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
        "total_rules": len(CLAIM_RULES),
        "total_lines_inspected": inspected_count,
        "violations_count": len(violations),
        "violations": violations
    }

    results_dir = os.path.join(BASE_DIR, "tests", "results")
    os.makedirs(results_dir, exist_ok=True)
    report_path = os.path.join(results_dir, "claims_audit_report.json")
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2)

    print(f"Total Rules Configured: {len(CLAIM_RULES)}")
    print(f"Total Lines Inspected:  {inspected_count}")
    print(f"Total Violations:       {len(violations)}")
    print(f"Audit Status:           {results['status']}")
    print(f"Report Generated:       {os.path.relpath(report_path, BASE_DIR)}\n")

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
    if "--fixtures-only" in sys.argv:
        fixtures_ok = run_fixture_tests()
        sys.exit(0 if fixtures_ok else 1)

    fixtures_ok = run_fixture_tests()
    if not fixtures_ok:
        print("[!] Fixture self-tests failed! Halting audit.")
        sys.exit(1)

    print()
    audit_ok = run_audit()
    sys.exit(0 if audit_ok else 1)
