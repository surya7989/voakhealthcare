import re

with open('design/phase-03/app.js', 'r', encoding='utf-8') as f:
    lines = f.readlines()

print("Searching app.js for grid-template-columns:")
for idx, line in enumerate(lines, 1):
    if 'grid-template-columns' in line:
        print(f"Line {idx}: {line.strip()[:100]}")

print("\nSearching index.html for grid-template-columns:")
with open('design/phase-03/index.html', 'r', encoding='utf-8') as f:
    for idx, line in enumerate(f, 1):
        if 'grid-template-columns' in line:
            print(f"Line {idx}: {line.strip()[:100]}")
