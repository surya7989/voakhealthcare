import os, zipfile, hashlib

zip_name = "VOAK_PHASE_3_DESIGN_REVIEW_v5.zip"

# Create a mapping of (disk_path, archive_path)
files_to_pack = [
    ("design/phase-03/index.html", "index.html"),
    ("design/phase-03/styles.css", "styles.css"),
    ("design/phase-03/app.js", "app.js"),
    ("design/phase-03/serve.js", "serve.js"),
    ("design/phase-03/README_PREVIEW.md", "README_PREVIEW.md"),
    ("design/phase-03/references/approved_homepage_reference_image_2.png", "references/approved_homepage_reference_image_2.png"),
]

# Assets
assets_dir = "design/phase-03/assets/images"
for f in os.listdir(assets_dir):
    files_to_pack.append((os.path.join(assets_dir, f), f"assets/images/{f}"))

# Screenshots
screenshots_dir = "design/phase-03/screenshots"
for f in os.listdir(screenshots_dir):
    p = os.path.join(screenshots_dir, f)
    if os.path.isfile(p):
        files_to_pack.append((p, f"screenshots/{f}"))

admin_dir = "design/phase-03/screenshots/admin_modules"
for f in os.listdir(admin_dir):
    files_to_pack.append((os.path.join(admin_dir, f), f"screenshots/admin_modules/{f}"))

pub_dir = "design/phase-03/screenshots/public_pages"
for f in os.listdir(pub_dir):
    files_to_pack.append((os.path.join(pub_dir, f), f"screenshots/public_pages/{f}"))

# Tests
files_to_pack.append(("tests/test_expanded_responsive.js", "tests/test_expanded_responsive.js"))
files_to_pack.append(("tests/test_responsive_overflow.js", "tests/test_responsive_overflow.js"))
files_to_pack.append(("tests/screenshot_metrics.json", "tests/screenshot_metrics.json"))
files_to_pack.append(("tests/responsive_test_results.json", "tests/responsive_test_results.json"))
files_to_pack.append(("tests/results/phase-03-responsive-results.json", "tests/results/phase-03-responsive-results.json"))
files_to_pack.append(("tests/results/phase-03-test-output.txt", "tests/results/phase-03-test-output.txt"))

# Docs
files_to_pack.append(("docs/phase-03/20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md", "docs/20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md"))
files_to_pack.append(("docs/phase-03/GATE_1_REFERENCE_IMAGE_AUDIT_REPORT.md", "docs/GATE_1_REFERENCE_IMAGE_AUDIT_REPORT.md"))
files_to_pack.append(("docs/phase-03/GATE_2_BASELINE_RECONCILIATION_REPORT.md", "docs/GATE_2_BASELINE_RECONCILIATION_REPORT.md"))

print(f"Total files to pack: {len(files_to_pack)}")

# Build zip
if os.path.exists(zip_name):
    os.remove(zip_name)

with zipfile.ZipFile(zip_name, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for disk_path, arc_path in sorted(files_to_pack, key=lambda x: x[1]):
        # Ensure forward slash
        arc_path = arc_path.replace('\\', '/')
        if not os.path.exists(disk_path):
            raise FileNotFoundError(f"Missing required file: {disk_path}")
        z.write(disk_path, arc_path)

print(f"Archive {zip_name} built successfully!")
print(f"Archive Size: {os.path.getsize(zip_name):,} bytes")

# Verify the archive
with zipfile.ZipFile(zip_name, 'r') as z:
    names = z.namelist()
    backslash_entries = [n for n in names if '\\' in n]
    print(f"Total entries in archive: {len(names)}")
    print(f"Backslash entries: {len(backslash_entries)}")
    assert len(backslash_entries) == 0, "Backslashes detected in zip archive!"

    # Check reference hash
    ref_data = z.read("references/approved_homepage_reference_image_2.png")
    ref_hash = hashlib.sha256(ref_data).hexdigest()
    print(f"Archived Image 2 SHA-256: {ref_hash}")
    assert ref_hash == "b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba", "SHA-256 mismatch in archive!"
    print("ALL ARCHIVE VERIFICATIONS PASSED!")
