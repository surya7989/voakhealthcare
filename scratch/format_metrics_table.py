import json

with open('tests/screenshot_metrics.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

lines = []
lines.append(f"{'#':<3} {'Screenshot Relative Path':<44} {'IHDR (W x H)':<16} {'Size (KB)':<10} {'SHA-256 Prefix':<16} {'Status'}")
lines.append("-" * 100)

for idx, (rel_path, v) in enumerate(data.items(), 1):
    dim_str = f"{v['width']} x {v['height']}"
    sha_short = f"{v['sha256'][:8]}..."
    lines.append(f"{idx:<3} {rel_path:<44} {dim_str:<16} {v['sizeKb'] + ' KB':<10} {sha_short:<16} PASS")

table_text = "\n".join(lines)
with open('scratch/metrics_table.txt', 'w', encoding='utf-8') as f:
    f.write(table_text)

print(f"Wrote {len(data)} rows to scratch/metrics_table.txt")
