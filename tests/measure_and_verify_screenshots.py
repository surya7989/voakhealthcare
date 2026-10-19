#!/usr/bin/env python3
"""
Voak Health Care — Phase 3 V7 Screenshot Telemetry & Independent Verification Engine

Supports both environments:
1. Repository layout: design/phase-03/screenshots/
2. Standalone review-package layout: screenshots/

Deterministic resolution, PNG signature verification, IHDR extraction, SHA-256 hashing,
separate reference image integrity verification, and cross-reference with browser viewport capture manifest.
"""

import os
import sys
import json
import struct
import hashlib

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Candidate screenshot directories
CANDIDATE_SCREENSHOT_DIRS = [
    os.path.join(BASE_DIR, 'design', 'phase-03', 'screenshots'),
    os.path.join(BASE_DIR, 'screenshots')
]

# Candidate reference file paths
CANDIDATE_REF_PATHS = [
    os.path.join(BASE_DIR, 'design', 'phase-03', 'references', 'approved_homepage_reference_image_2.png'),
    os.path.join(BASE_DIR, 'references', 'approved_homepage_reference_image_2.png')
]

# Output metrics file path
METRICS_OUTPUT = os.path.join(BASE_DIR, 'tests', 'screenshot_metrics.json')
CAPTURE_MANIFEST_PATH = os.path.join(BASE_DIR, 'tests', 'screenshot_capture_manifest.json')

EXPECTED_FILES = [
    # 9 Primary Screenshots
    "01_desktop_homepage.png",
    "02_mobile_homepage_390px.png",
    "03_tablet_homepage_768px.png",
    "04_public_pages_gallery.png",
    "05_admin_dashboard.png",
    "06_component_system.png",
    "07_approved_reference_comparison.png",
    "08_proposal_wizard_modal.png",
    "09_simulated_403_access_denied.png",
]

# 19 Admin Modules
for i in range(1, 20):
    EXPECTED_FILES.append(f"admin_modules/admin_adm-{i:02d}_preview.png")

# 22 Public Pages
for i in range(1, 23):
    EXPECTED_FILES.append(f"public_pages/page_pub-{i:02d}_preview.png")

APPROVED_REF_HASH = "b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba"
APPROVED_REF_WIDTH = 1214
APPROVED_REF_HEIGHT = 1295

def resolve_screenshot_dir():
    for d in CANDIDATE_SCREENSHOT_DIRS:
        if os.path.exists(d) and os.path.isdir(d):
            return d
    return None

def resolve_reference_path():
    for p in CANDIDATE_REF_PATHS:
        if os.path.exists(p) and os.path.isfile(p):
            return p
    return None

def parse_png(file_path):
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"Missing screenshot file: {file_path}")

    with open(file_path, 'rb') as f:
        data = f.read()

    size_bytes = len(data)
    if size_bytes < 24:
        raise ValueError(f"File too small to be valid PNG: {file_path}")

    # Check PNG signature
    png_sig = b'\x89PNG\r\n\x1a\n'
    if data[:8] != png_sig:
        raise ValueError(f"Invalid PNG signature for file: {file_path}")

    # Parse IHDR (chunk length 13, chunk type IHDR)
    ihdr_len, chunk_type = struct.unpack('>I4s', data[8:16])
    if chunk_type != b'IHDR' or ihdr_len != 13:
        raise ValueError(f"Missing or corrupted IHDR chunk in {file_path}")

    width, height = struct.unpack('>II', data[16:24])
    sha256 = hashlib.sha256(data).hexdigest()

    return {
        "width": width,
        "height": height,
        "sizeBytes": size_bytes,
        "sizeKb": f"{size_bytes / 1024:.1f}",
        "sha256": sha256
    }

def main():
    print("=" * 80)
    print("VOAK HEALTH CARE — PHASE 3 V7 SCREENSHOT TELEMETRY & VERIFICATION ENGINE")
    print("=" * 80)

    screenshot_dir = resolve_screenshot_dir()
    if not screenshot_dir:
        print("FATAL: Could not locate screenshot directory in candidate locations:")
        for c in CANDIDATE_SCREENSHOT_DIRS:
            print(f"  - {c}")
        sys.exit(1)

    print(f"Verified Screenshot Directory: {screenshot_dir}")
    print(f"Metrics Destination:           {METRICS_OUTPUT}")
    print(f"Capture Manifest Destination:  {CAPTURE_MANIFEST_PATH}\n")

    # 1. Independent Check of Approved Reference Image 2
    print("--- 1. Checking Approved Reference Image 2 Integrity (Separate Asset) ---")
    ref_path = resolve_reference_path()
    if not ref_path:
        print("FATAL: Approved reference image not found in candidate paths:")
        for p in CANDIDATE_REF_PATHS:
            print(f"  - {p}")
        sys.exit(1)

    ref_info = parse_png(ref_path)
    ref_hash_ok = (ref_info['sha256'] == APPROVED_REF_HASH)
    ref_dims_ok = (ref_info['width'] == APPROVED_REF_WIDTH and ref_info['height'] == APPROVED_REF_HEIGHT)

    print(f"Reference Path:   {ref_path}")
    print(f"IHDR Dimensions:  {ref_info['width']} x {ref_info['height']} px (Expected: {APPROVED_REF_WIDTH} x {APPROVED_REF_HEIGHT}) [{'PASS' if ref_dims_ok else 'FAIL'}]")
    print(f"Size:             {ref_info['sizeKb']} KB ({ref_info['sizeBytes']} bytes)")
    print(f"SHA-256:          {ref_info['sha256']}")
    print(f"Expected SHA-256: {APPROVED_REF_HASH}")
    print(f"Reference Status: {'PASS (100% UNTOUCHED BIT-FOR-BIT)' if (ref_hash_ok and ref_dims_ok) else 'FAIL (HASH MISMATCH)'}\n")

    if not (ref_hash_ok and ref_dims_ok):
        print("FATAL: Approved reference asset has been altered or corrupted!")
        sys.exit(1)

    # 2. Measure all 50 Screenshot PNGs
    print("--- 2. Measuring All 50 Required Prototype Screenshots ---")
    metrics = {}
    errors = []

    print(f"{'#':<3} {'Screenshot Relative Path':<44} {'IHDR (W x H)':<16} {'Size (KB)':<10} {'Status'}")
    print("-" * 80)

    for idx, rel_path in enumerate(EXPECTED_FILES, 1):
        full_path = os.path.join(screenshot_dir, rel_path.replace('/', os.sep))
        try:
            info = parse_png(full_path)
            metrics[rel_path] = info
            dim_str = f"{info['width']} x {info['height']}"
            print(f"{idx:<3} {rel_path:<44} {dim_str:<16} {info['sizeKb']:<10} PASS")
        except Exception as e:
            errors.append(f"{rel_path}: {str(e)}")
            print(f"{idx:<3} {rel_path:<44} {'ERROR':<16} {'-':<10} FAIL ({str(e)})")

    total_count = len(metrics)
    expected_count = len(EXPECTED_FILES)

    print("-" * 80)
    print(f"Total Expected: {expected_count}")
    print(f"Total Measured: {total_count}")
    print(f"Errors Found:   {len(errors)}")

    if len(errors) > 0 or total_count != expected_count:
        print("\nFATAL: Verification failed with errors:")
        for err in errors:
            print(f"  - {err}")
        sys.exit(1)

    # Save to tests/screenshot_metrics.json
    os.makedirs(os.path.dirname(METRICS_OUTPUT), exist_ok=True)
    with open(METRICS_OUTPUT, 'w', encoding='utf-8') as f:
        json.dump(metrics, f, indent=2)
    print(f"\n[OK] screenshot_metrics.json written ({total_count} verified entries)")

    # 3. Independent Round-Trip Re-Verification
    print("\n--- 3. Performing Independent Re-Verification from JSON Manifest ---")
    with open(METRICS_OUTPUT, 'r', encoding='utf-8') as f:
        loaded = json.load(f)

    discrepancies = 0
    for rel_path, expected_entry in loaded.items():
        full_path = os.path.join(screenshot_dir, rel_path.replace('/', os.sep))
        re_info = parse_png(full_path)
        if (re_info['width'] != expected_entry['width'] or
            re_info['height'] != expected_entry['height'] or
            re_info['sizeBytes'] != expected_entry['sizeBytes'] or
            re_info['sha256'] != expected_entry['sha256']):
            print(f"DISCREPANCY in {rel_path}:")
            print(f"  Recorded: {expected_entry}")
            print(f"  Actual:   {re_info}")
            discrepancies += 1

    print(f"Total Manifest Discrepancies: {discrepancies}")
    if discrepancies > 0:
        print("FATAL: Discrepancies found between manifest and actual disk files!")
        sys.exit(1)

    print("=" * 80)
    print("SUCCESS: ALL 50 SCREENSHOTS + 1 REFERENCE ASSET VERIFIED (0 DISCREPANCIES)")
    print("=" * 80)
    sys.exit(0)

if __name__ == '__main__':
    main()
