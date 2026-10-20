import json
import os
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

with open('tests/screenshot_capture_manifest.json', 'r') as f:
    manifest = json.load(f)

with open('tests/screenshot_metrics.json', 'r') as f:
    metrics = json.load(f)

base_dir = 'design/phase-03/screenshots'
count_diff = 0

print(f"{'File':45} | {'Disk PNG':12} | {'Metrics':12} | {'Manifest IHDR':15} | Match?")
print("-" * 95)

for k, man in manifest.items():
    full_path = os.path.join(base_dir, k)
    w_disk, h_disk, sz_disk, sha_disk = get_png_dimensions(full_path)
    
    met = metrics.get(k, {})
    w_met = met.get('width')
    h_met = met.get('height')
    
    man_ihdr = man.get('ihdr', {})
    w_man = man_ihdr.get('width')
    h_man = man_ihdr.get('height')
    
    match = (w_disk == w_met == w_man) and (h_disk == h_met == h_man)
    if not match:
        count_diff += 1
        print(f"{k:45} | {w_disk}x{h_disk:<6} | {w_met}x{h_met:<6} | {w_man}x{h_man:<8} | DISAGREE")

print("-" * 95)
print(f"Total Disagreements: {count_diff} / {len(manifest)}")
