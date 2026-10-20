import re

def inspect_ids():
    # 1. 01_MASTER_FUNCTIONAL_REQUIREMENTS.md
    with open("docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md", "r", encoding="utf-8") as f:
        mfr_text = f.read()
    fr_mfr = sorted(list(set(re.findall(r'\| \*\*(FR-[A-Z0-9]+-\d{3})\*\*', mfr_text))))
    
    # 2. 15_REQUIREMENTS_TRACEABILITY_MATRIX.md
    with open("docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md", "r", encoding="utf-8") as f:
        rtm_text = f.read()
    fr_rtm = sorted(list(set(re.findall(r'\| \*\*(FR-[A-Z0-9]+-\d{3})\*\*', rtm_text))))
    tests_rtm = sorted(list(set(re.findall(r'`(TEST-[A-Z0-9]+-\d{3})`', rtm_text))))
    
    # 3. 17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md
    with open("docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md", "r", encoding="utf-8") as f:
        atc_text = f.read()
    tests_atc = sorted(list(set(re.findall(r'#### \d+\.\s+(TEST-[A-Z0-9]+-\d{3})', atc_text))))
    
    print(f"FR in 01_MASTER_FUNCTIONAL_REQUIREMENTS.md: count = {len(fr_mfr)}")
    print(f"FR in 15_REQUIREMENTS_TRACEABILITY_MATRIX.md: count = {len(fr_rtm)}")
    print(f"Diff (MFR vs RTM): {set(fr_mfr) ^ set(fr_rtm)}")
    print(f"TEST in 15_REQUIREMENTS_TRACEABILITY_MATRIX.md: count = {len(tests_rtm)}")
    print(f"TEST in 17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md: count = {len(tests_atc)}")
    print(f"Diff (RTM vs ATC): {set(tests_rtm) ^ set(tests_atc)}")

inspect_ids()
