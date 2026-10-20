import os
import json
import struct
import hashlib

def get_png_dimensions(file_path):
    with open(file_path, 'rb') as f:
        data = f.read()
    if data[:8] != b'\x89PNG\r\n\x1a\n':
        return None, None, None, None
    width, height = struct.unpack('>II', data[16:24])
    sha256 = hashlib.sha256(data).hexdigest()
    return width, height, len(data), sha256

manifest_path = "tests/screenshot_capture_manifest.json"
metrics_path = "tests/screenshot_metrics.json"

with open(manifest_path, "r", encoding="utf-8") as f:
    manifest_data = json.load(f)

with open(metrics_path, "r", encoding="utf-8") as f:
    metrics_data = json.load(f)

print(f"Manifest type: {type(manifest_data)}")
print(f"Metrics type: {type(metrics_data)}")

# Let's inspect manifest keys
if isinstance(manifest_data, dict):
    print("Manifest keys:", list(manifest_data.keys()))
    captures = manifest_data.get("captures", manifest_data.get("screenshots", []))
elif isinstance(manifest_data, list):
    captures = manifest_data

print(f"Total captures in manifest: {len(captures)}")

# Compare with physical files on disk
base_dir = "design/phase-03/screenshots"
disagreements = []

for c in captures:
    # Look for rel path
    rel_path = c.get("file") or c.get("path") or c.get("filename") or c.get("name")
    full_path = os.path.join(base_dir, rel_path)
    if not os.path.exists(full_path):
        print(f"File not found: {full_path}")
        continue
    w, h, sz, hsh = get_png_dimensions(full_path)
    
    # Check what manifest recorded
    man_w = c.get("measured_width_px") or c.get("actual_width") or c.get("width")
    man_h = c.get("measured_height_px") or c.get("actual_height") or c.get("height")
    man_viewport_w = c.get("viewport_width")
    man_viewport_h = c.get("viewport_height")
    
    print(f"{rel_path:45} | Disk: {w}x{h} ({sz}B) | Manifest: {man_w}x{man_h} | Viewport: {man_viewport_w}x{man_viewport_h}")
    if (man_w != w) or (man_h != h):
        disagreements.append((rel_path, f"Disk {w}x{h} vs Manifest {man_w}x{man_h}"))

print(f"\nTotal disagreements: {len(disagreements)}")
for d in disagreements:
    print("  DISAGREEMENT:", d)
