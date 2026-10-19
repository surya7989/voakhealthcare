const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const DESIGN_DIR = path.join(ROOT, 'design', 'phase-03');
const STAGING_DIR = path.join(__dirname, 'staging_v4');
const ZIP_OUT = path.join(ROOT, 'VOAK_PHASE_3_DESIGN_REVIEW_v4.zip');

console.log('Preparing Staging Directory for V4 Review Package...');
if (fs.existsSync(STAGING_DIR)) {
  fs.rmSync(STAGING_DIR, { recursive: true, force: true });
}
fs.mkdirSync(STAGING_DIR, { recursive: true });

// Copy core prototype files
const coreFiles = ['index.html', 'styles.css', 'app.js', 'serve.js', 'README_PREVIEW.md'];
coreFiles.forEach(f => {
  fs.copyFileSync(path.join(DESIGN_DIR, f), path.join(STAGING_DIR, f));
});

// Copy assets
function copyFolderRecursive(src, dest) {
  if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (let entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyFolderRecursive(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

copyFolderRecursive(path.join(DESIGN_DIR, 'assets'), path.join(STAGING_DIR, 'assets'));
copyFolderRecursive(path.join(DESIGN_DIR, 'references'), path.join(STAGING_DIR, 'references'));
copyFolderRecursive(path.join(DESIGN_DIR, 'screenshots'), path.join(STAGING_DIR, 'screenshots'));

// Copy docs
const docsDest = path.join(STAGING_DIR, 'docs');
fs.mkdirSync(docsDest, { recursive: true });
fs.copyFileSync(
  path.join(ROOT, 'docs', 'phase-03', '20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md'),
  path.join(docsDest, '20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md')
);

// Copy tests and verification data
const testsDest = path.join(STAGING_DIR, 'tests');
fs.mkdirSync(testsDest, { recursive: true });
if (fs.existsSync(path.join(ROOT, 'tests', 'test_responsive_overflow.js'))) {
  fs.copyFileSync(path.join(ROOT, 'tests', 'test_responsive_overflow.js'), path.join(testsDest, 'test_responsive_overflow.js'));
}
if (fs.existsSync(path.join(ROOT, 'tests', 'responsive_test_results.json'))) {
  fs.copyFileSync(path.join(ROOT, 'tests', 'responsive_test_results.json'), path.join(testsDest, 'responsive_test_results.json'));
}

console.log('Staging populated. Creating zip archive...');
if (fs.existsSync(ZIP_OUT)) {
  fs.unlinkSync(ZIP_OUT);
}

// Use powershell Compress-Archive with single quotes
const psCommand = `Compress-Archive -Path '${STAGING_DIR}\\*' -DestinationPath '${ZIP_OUT}' -Force`;
execSync(`powershell.exe -NoProfile -Command "${psCommand}"`, { stdio: 'inherit' });

console.log(`Zip archive created at: ${ZIP_OUT}`);
const stats = fs.statSync(ZIP_OUT);
console.log(`Archive file size: ${(stats.size / 1024 / 1024).toFixed(2)} MB (${stats.size} bytes)`);

// Inspect and verify archive extraction
const TEST_EXTRACT_DIR = path.join(__dirname, 'test_extracted_v4');
if (fs.existsSync(TEST_EXTRACT_DIR)) {
  fs.rmSync(TEST_EXTRACT_DIR, { recursive: true, force: true });
}
fs.mkdirSync(TEST_EXTRACT_DIR, { recursive: true });

console.log('Verifying archive by test extraction...');
const extractPs = `Expand-Archive -Path '${ZIP_OUT}' -DestinationPath '${TEST_EXTRACT_DIR}' -Force`;
execSync(`powershell.exe -NoProfile -Command "${extractPs}"`, { stdio: 'inherit' });

function countFiles(dir) {
  let count = 0;
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (let entry of entries) {
    if (entry.isDirectory()) {
      count += countFiles(path.join(dir, entry.name));
    } else {
      count++;
    }
  }
  return count;
}

const totalExtracted = countFiles(TEST_EXTRACT_DIR);
console.log(`Test extraction verified! Total files in extracted archive: ${totalExtracted}`);
