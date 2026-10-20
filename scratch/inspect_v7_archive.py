import zipfile
import hashlib
import os

zip_path = 'VOAK_PHASE_3_DESIGN_REVIEW_v7.zip'
with open(zip_path, 'rb') as f:
    content = f.read()
    sha256 = hashlib.sha256(content).hexdigest()

zf = zipfile.ZipFile(zip_path, 'r')
entries = zf.infolist()
bad_file = zf.testzip()

print(f"Archive Path: {os.path.abspath(zip_path)}")
print(f"File Size: {len(content)} bytes")
print(f"SHA-256: {sha256}")
print(f"Total Entries: {len(entries)}")
print(f"Integrity Test (testzip): {'PASS (No corruptions)' if bad_file is None else f'FAIL: Corrupt file {bad_file}'}")
