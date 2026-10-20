import zipfile
import hashlib
import os
import shutil

zip_path = 'VOAK_PHASE_3_DESIGN_REVIEW_v7.zip'
extract_dir = os.path.join('scratch', 'clean_extracted_v7')

if os.path.exists(extract_dir):
    shutil.rmtree(extract_dir)
os.makedirs(extract_dir, exist_ok=True)

with zipfile.ZipFile(zip_path, 'r') as zf:
    zf.extractall(extract_dir)

print(f"Extracted V7 to: {os.path.abspath(extract_dir)}")

# Compare files between extracted V7 and workspace
# In V7 archive:
# index.html -> design/phase-03/index.html
# styles.css -> design/phase-03/styles.css
# app.js -> design/phase-03/app.js
# serve.js -> design/phase-03/serve.js
# README_PREVIEW.md -> design/phase-03/README_PREVIEW.md
# references/... -> design/phase-03/references/...
# assets/... -> design/phase-03/assets/...
# screenshots/... -> design/phase-03/screenshots/...
# docs/... -> docs/...
# tests/... -> tests/...

differences = []
same = []

for root, dirs, files in os.walk(extract_dir):
    for f in files:
        ext_path = os.path.join(root, f)
        rel_path = os.path.relpath(ext_path, extract_dir).replace('\\', '/')
        
        # Determine workspace path
        if rel_path in ['index.html', 'styles.css', 'app.js', 'serve.js', 'README_PREVIEW.md']:
            ws_path = os.path.join('design', 'phase-03', rel_path)
        elif rel_path.startswith('references/') or rel_path.startswith('assets/') or rel_path.startswith('screenshots/'):
            ws_path = os.path.join('design', 'phase-03', rel_path)
        else:
            ws_path = rel_path
            
        if not os.path.exists(ws_path):
            differences.append(f"Missing in workspace: {ws_path} (from archive {rel_path})")
            continue
            
        with open(ext_path, 'rb') as f1, open(ws_path, 'rb') as f2:
            h1 = hashlib.sha256(f1.read()).hexdigest()
            h2 = hashlib.sha256(f2.read()).hexdigest()
            if h1 != h2:
                differences.append(f"Content difference: archive {rel_path} != workspace {ws_path} ({h1[:8]} vs {h2[:8]})")
            else:
                same.append(rel_path)

print(f"Total matched files: {len(same)}")
print(f"Total differences found: {len(differences)}")
for d in differences:
    print("  DIFF:", d)
