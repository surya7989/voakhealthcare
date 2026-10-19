#!/usr/bin/env python3
"""
Voak Health Care — Phase 3 V6 Packaging & Independent Verification Script

Builds VOAK_PHASE_3_DESIGN_REVIEW_v6.zip with standard forward-slash archive paths,
verifies ZIP CRC, extracts into a clean sandbox directory, and re-verifies:
1. Reference image SHA-256 and dimensions.
2. All 50 screenshots IHDR width, height, size, and hash against screenshot_metrics.json.
3. Syntax verification of JS and Python files in extracted package.
4. Independent execution of baseline reconciliation test from extracted package.
"""

import os
import sys
import zipfile
import shutil
import hashlib
import struct
import subprocess
import json

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ZIP_OUTPUT = os.path.join(BASE_DIR, 'VOAK_PHASE_3_DESIGN_REVIEW_v6.zip')
EXTRACT_DIR = os.path.join(BASE_DIR, 'scratch', 'extracted_test_v6')

REF_EXPECTED_HASH = "b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba"

def parse_png(file_path):
    with open(file_path, 'rb') as f:
        data = f.read()
    if data[:8] != b'\x89PNG\r\n\x1a\n':
        raise ValueError(f"Invalid PNG magic: {file_path}")
    width, height = struct.unpack('>II', data[16:24])
    sha256 = hashlib.sha256(data).hexdigest()
    return width, height, len(data), sha256

def main():
    print("=" * 78)
    print("VOAK HEALTH CARE — PHASE 3 V6 ARCHIVE BUILD & INDEPENDENT TEST ENGINE")
    print("=" * 78)

    # 1. Collect files to include
    print("\n--- Step 1: Collecting Canonical Files for V6 Review Package ---")
    files_to_pack = [] # tuple of (abs_path, archive_rel_path)

    p3_root = os.path.join(BASE_DIR, 'design', 'phase-03')
    
    # Core prototype files
    for fname in ['index.html', 'styles.css', 'app.js', 'serve.js', 'README_PREVIEW.md']:
        fpath = os.path.join(p3_root, fname)
        if os.path.exists(fpath):
            files_to_pack.append((fpath, fname))
        else:
            print(f"FATAL: Missing root file: {fpath}")
            sys.exit(1)

    # Assets
    assets_dir = os.path.join(p3_root, 'assets', 'images')
    for root, dirs, files in os.walk(assets_dir):
        for f in files:
            abs_p = os.path.join(root, f)
            rel_p = 'assets/images/' + f
            files_to_pack.append((abs_p, rel_p))

    # Reference Image
    ref_file = os.path.join(p3_root, 'references', 'approved_homepage_reference_image_2.png')
    if not os.path.exists(ref_file):
        print(f"FATAL: Missing authentic reference image: {ref_file}")
        sys.exit(1)
    files_to_pack.append((ref_file, 'references/approved_homepage_reference_image_2.png'))

    # All 50 Screenshots
    screenshots_root = os.path.join(p3_root, 'screenshots')
    for root, dirs, files in os.walk(screenshots_root):
        for f in files:
            if f.endswith('.png'):
                abs_p = os.path.join(root, f)
                rel_to_screenshots = os.path.relpath(abs_p, screenshots_root).replace('\\', '/')
                archive_path = f"screenshots/{rel_to_screenshots}"
                files_to_pack.append((abs_p, archive_path))

    # Test suite & telemetry
    test_files = [
        (os.path.join(BASE_DIR, 'tests', 'test_expanded_responsive.js'), 'tests/test_expanded_responsive.js'),
        (os.path.join(BASE_DIR, 'tests', 'test_responsive_overflow.js'), 'tests/test_responsive_overflow.js'),
        (os.path.join(BASE_DIR, 'tests', 'reconcile_baseline.py'), 'tests/reconcile_baseline.py'),
        (os.path.join(BASE_DIR, 'tests', 'measure_and_verify_screenshots.py'), 'tests/measure_and_verify_screenshots.py'),
        (os.path.join(BASE_DIR, 'tests', 'screenshot_metrics.json'), 'tests/screenshot_metrics.json'),
        (os.path.join(BASE_DIR, 'tests', 'results', 'baseline_reconciliation.json'), 'tests/results/baseline_reconciliation.json'),
        (os.path.join(BASE_DIR, 'tests', 'results', 'phase-03-responsive-results.json'), 'tests/results/phase-03-responsive-results.json'),
        (os.path.join(BASE_DIR, 'tests', 'results', 'phase-03-test-output.txt'), 'tests/results/phase-03-test-output.txt')
    ]
    for abs_p, arc_p in test_files:
        if os.path.exists(abs_p):
            files_to_pack.append((abs_p, arc_p))
        else:
            print(f"FATAL: Missing test file: {abs_p}")
            sys.exit(1)

    # Documentation & Audit reports
    doc_files = [
        (os.path.join(BASE_DIR, 'docs', 'phase-03', '20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md'), 'docs/20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md'),
        (os.path.join(BASE_DIR, 'docs', 'phase-03', 'GATE_1_REFERENCE_IMAGE_AUDIT_REPORT.md'), 'docs/GATE_1_REFERENCE_IMAGE_AUDIT_REPORT.md'),
        (os.path.join(BASE_DIR, 'docs', 'phase-03', 'GATE_2_BASELINE_RECONCILIATION_REPORT.md'), 'docs/GATE_2_BASELINE_RECONCILIATION_REPORT.md'),
        (os.path.join(BASE_DIR, 'docs', 'phase-03', 'GATE_4_CLAIM_AUDIT_REPORT.md'), 'docs/GATE_4_CLAIM_AUDIT_REPORT.md')
    ]
    for abs_p, arc_p in doc_files:
        if os.path.exists(abs_p):
            files_to_pack.append((abs_p, arc_p))
        else:
            print(f"FATAL: Missing doc file: {abs_p}")
            sys.exit(1)

    # Also include accepted Phase 0-2 baseline specs for self-contained reconciliation auditing
    for p_num in ['00', '01']:
        p_dir = os.path.join(BASE_DIR, 'docs', f'phase-{p_num}')
        if os.path.exists(p_dir):
            for f in os.listdir(p_dir):
                if f.endswith('.md'):
                    files_to_pack.append((os.path.join(p_dir, f), f"docs/phase-{p_num}/{f}"))

    print(f"Total files cataloged for archive: {len(files_to_pack)}")

    # 2. Build ZIP archive with forward slashes
    print(f"\n--- Step 2: Creating ZIP Archive: {ZIP_OUTPUT} ---")
    if os.path.exists(ZIP_OUTPUT):
        os.remove(ZIP_OUTPUT)

    with zipfile.ZipFile(ZIP_OUTPUT, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for abs_p, arc_p in files_to_pack:
            # Force standard forward slashes
            clean_arc_path = arc_p.replace('\\', '/')
            zipf.write(abs_p, clean_arc_path)

    zip_size_bytes = os.path.getsize(ZIP_OUTPUT)
    print(f"[OK] Archive successfully created. Size: {zip_size_bytes:,} bytes ({zip_size_bytes / (1024*1024):.2f} MB)")

    # 3. Test ZIP CRC integrity
    print("\n--- Step 3: Verifying ZIP Archive CRC Integrity ---")
    with zipfile.ZipFile(ZIP_OUTPUT, 'r') as zipf:
        test_res = zipf.testzip()
        if test_res is not None:
            print(f"FATAL: Corrupted file in archive: {test_res}")
            sys.exit(1)
        print("[OK] ZIP CRC test passed with 0 corruptions.")

        # Check path separators
        for name in zipf.namelist():
            if '\\' in name:
                print(f"FATAL: Backslash found in archive path: {name}")
                sys.exit(1)
        print("[OK] All archive entry paths use standard forward slashes.")

    # 4. Extract into clean sandbox directory
    print(f"\n--- Step 4: Extracting to Clean Temporary Sandbox: {EXTRACT_DIR} ---")
    if os.path.exists(EXTRACT_DIR):
        shutil.rmtree(EXTRACT_DIR)
    os.makedirs(EXTRACT_DIR, exist_ok=True)

    with zipfile.ZipFile(ZIP_OUTPUT, 'r') as zipf:
        zipf.extractall(EXTRACT_DIR)
    print(f"[OK] All files extracted cleanly into {EXTRACT_DIR}")

    # 5. Re-verify reference image in extracted package
    print("\n--- Step 5: Re-verifying Reference Image in Extracted Package ---")
    ext_ref = os.path.join(EXTRACT_DIR, 'references', 'approved_homepage_reference_image_2.png')
    if not os.path.exists(ext_ref):
        print(f"FATAL: Extracted reference missing: {ext_ref}")
        sys.exit(1)
    
    ext_w, ext_h, ext_sz, ext_hash = parse_png(ext_ref)
    print(f"Extracted Reference: {ext_w} x {ext_h} px, {ext_sz:,} B, SHA-256: {ext_hash}")
    assert ext_hash == REF_EXPECTED_HASH, f"Hash mismatch: {ext_hash} != {REF_EXPECTED_HASH}"
    assert ext_w == 1214 and ext_h == 1295, f"Dimensions mismatch: {ext_w}x{ext_h}"
    print("[PASS] Authentic Reference Image in extracted package matches required cryptographic baseline 100%.")

    # 6. Re-verify all 50 screenshots against telemetry in extracted package
    print("\n--- Step 6: Re-verifying All 50 Screenshots in Extracted Package Against Telemetry ---")
    ext_metrics_file = os.path.join(EXTRACT_DIR, 'tests', 'screenshot_metrics.json')
    with open(ext_metrics_file, 'r', encoding='utf-8') as f:
        ext_metrics = json.load(f)

    discrepancies = 0
    checked_count = 0
    for rel_path, expected in ext_metrics.items():
        ext_png = os.path.join(EXTRACT_DIR, 'screenshots', rel_path.replace('/', os.sep))
        if not os.path.exists(ext_png):
            print(f"ERROR: Missing extracted screenshot: {ext_png}")
            discrepancies += 1
            continue
        
        w, h, sz, sha = parse_png(ext_png)
        checked_count += 1
        if (w != expected['width'] or h != expected['height'] or sz != expected['sizeBytes'] or sha != expected['sha256']):
            print(f"DISCREPANCY in extracted screenshot {rel_path}:")
            print(f"  Expected: {expected}")
            print(f"  Actual:   w={w}, h={h}, sz={sz}, sha={sha}")
            discrepancies += 1

    print(f"Checked extracted screenshots: {checked_count} / {len(ext_metrics)}")
    print(f"Discrepancies found: {discrepancies}")
    if discrepancies > 0 or checked_count != 50:
        print("FATAL: Discrepancies detected between extracted screenshots and telemetry manifest!")
        sys.exit(1)
    print("[PASS] All 50 extracted screenshots match telemetry manifest with exactly 0 discrepancies.")

    # 7. Run syntax checks on extracted code
    print("\n--- Step 7: Executing Syntax Checks on Extracted Package ---")
    node_res1 = subprocess.run(['node', '--check', os.path.join(EXTRACT_DIR, 'app.js')], capture_output=True, text=True)
    if node_res1.returncode != 0:
        print(f"FATAL: Syntax error in extracted app.js: {node_res1.stderr}")
        sys.exit(1)
    print("[PASS] node --check app.js passed cleanly.")

    node_res2 = subprocess.run(['node', '--check', os.path.join(EXTRACT_DIR, 'serve.js')], capture_output=True, text=True)
    if node_res2.returncode != 0:
        print(f"FATAL: Syntax error in extracted serve.js: {node_res2.stderr}")
        sys.exit(1)
    print("[PASS] node --check serve.js passed cleanly.")

    # 8. Run baseline reconciliation from extracted package
    print("\n--- Step 8: Running Baseline Reconciliation from Extracted Package ---")
    rec_script = os.path.join(EXTRACT_DIR, 'tests', 'reconcile_baseline.py')
    py_res = subprocess.run([sys.executable, rec_script], cwd=EXTRACT_DIR, capture_output=True, text=True)
    if py_res.returncode != 0:
        print(f"FATAL: Baseline reconciliation failed in extracted package:\n{py_res.stderr}\n{py_res.stdout}")
        sys.exit(1)
    print(py_res.stdout.strip())
    print("[PASS] Baseline reconciliation script executed successfully from extracted package.")

    print("\n" + "=" * 78)
    print("SUCCESS: VOAK_PHASE_3_DESIGN_REVIEW_v6.zip IS FULLY VERIFIED AND INTEGRITY-CERTIFIED")
    print("=" * 78)

if __name__ == '__main__':
    main()
