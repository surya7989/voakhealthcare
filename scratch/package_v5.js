const fs = require('fs');
const path = require('path');
const { execSync, spawn } = require('child_process');
const http = require('http');

const ROOT = path.join(__dirname, '..');
const DESIGN_DIR = path.join(ROOT, 'design', 'phase-03');
const STAGING_DIR = path.join(__dirname, 'staging_v5');
const ZIP_OUT = path.join(ROOT, 'VOAK_PHASE_3_DESIGN_REVIEW_v5.zip');
const TEST_EXTRACT_DIR = path.join(__dirname, 'test_extracted_v5');

console.log('Preparing Staging Directory for V5 Review Package...');
if (fs.existsSync(STAGING_DIR)) {
  fs.rmSync(STAGING_DIR, { recursive: true, force: true });
}
fs.mkdirSync(STAGING_DIR, { recursive: true });

// Copy core prototype files
const coreFiles = ['index.html', 'styles.css', 'app.js', 'serve.js', 'README_PREVIEW.md'];
coreFiles.forEach(f => {
  fs.copyFileSync(path.join(DESIGN_DIR, f), path.join(STAGING_DIR, f));
});

// Copy assets recursively
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

// Ensure legacy admin reference is NOT present in references
const legacyAdminRef = path.join(STAGING_DIR, 'references', 'approved_admin_reference_portal.png');
if (fs.existsSync(legacyAdminRef)) {
  fs.unlinkSync(legacyAdminRef);
  console.log('Removed legacy admin reference from staging references.');
}

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
fs.copyFileSync(path.join(ROOT, 'tests', 'test_responsive_overflow.js'), path.join(testsDest, 'test_responsive_overflow.js'));
fs.copyFileSync(path.join(ROOT, 'tests', 'responsive_test_results.json'), path.join(testsDest, 'responsive_test_results.json'));

console.log('Staging populated. Creating zip archive...');
if (fs.existsSync(ZIP_OUT)) {
  fs.unlinkSync(ZIP_OUT);
}

// Use powershell Compress-Archive
const psCommand = `Compress-Archive -Path '${STAGING_DIR}\\*' -DestinationPath '${ZIP_OUT}' -Force`;
execSync(`powershell.exe -NoProfile -Command "${psCommand}"`, { stdio: 'inherit' });

console.log(`Zip archive created at: ${ZIP_OUT}`);
const stats = fs.statSync(ZIP_OUT);
console.log(`Archive file size: ${(stats.size / 1024 / 1024).toFixed(2)} MB (${stats.size} bytes)`);

// Inspect and verify archive extraction
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

// Verify that all 50 screenshots exist in extracted archive
const extractedScreenshotsDir = path.join(TEST_EXTRACT_DIR, 'screenshots');
const extractedPublicDir = path.join(extractedScreenshotsDir, 'public_pages');
const extractedAdminDir = path.join(extractedScreenshotsDir, 'admin_modules');

let screenshotCount = 0;
['01_desktop_homepage.png', '02_mobile_homepage_390px.png', '03_tablet_homepage_768px.png',
 '04_public_pages_gallery.png', '05_admin_dashboard.png', '06_component_system.png',
 '07_approved_reference_comparison.png', '08_proposal_wizard_modal.png', '09_simulated_403_access_denied.png'
].forEach(f => {
  if (fs.existsSync(path.join(extractedScreenshotsDir, f))) screenshotCount++;
  else console.error(`Missing primary screenshot: ${f}`);
});

for (let i = 1; i <= 19; i++) {
  const pad = String(i).padStart(2, '0');
  const f = `admin_adm-${pad}_preview.png`;
  if (fs.existsSync(path.join(extractedAdminDir, f))) screenshotCount++;
  else console.error(`Missing admin screenshot: ${f}`);
}

for (let i = 1; i <= 22; i++) {
  const pad = String(i).padStart(2, '0');
  const f = `page_pub-${pad}_preview.png`;
  if (fs.existsSync(path.join(extractedPublicDir, f))) screenshotCount++;
  else console.error(`Missing public screenshot: ${f}`);
}

console.log(`Verified screenshot count in extracted archive: ${screenshotCount}/50`);
if (screenshotCount !== 50) {
  console.error('FAILED: Not all 50 screenshots are present in extracted archive!');
  process.exit(1);
}

// Verify legacy admin reference is absent from extracted archive
if (fs.existsSync(path.join(TEST_EXTRACT_DIR, 'references', 'approved_admin_reference_portal.png'))) {
  console.error('FAILED: Legacy admin reference found in extracted archive!');
  process.exit(1);
} else {
  console.log('Verified: Legacy admin reference is completely absent from extracted archive.');
}

// Test responsive test execution from extracted archive
console.log('\nRunning responsive test suite against extracted archive...');
try {
  const testOutput = execSync('node tests/test_responsive_overflow.js', {
    cwd: TEST_EXTRACT_DIR,
    stdio: 'pipe'
  }).toString();
  console.log(testOutput);
  console.log('ALL TESTS PASSED AGAINST EXTRACTED ARCHIVE!');
} catch (err) {
  console.error('Error running responsive test from extracted archive:', err.stdout ? err.stdout.toString() : err.message);
  process.exit(1);
}
