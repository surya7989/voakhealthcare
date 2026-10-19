const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = 'c:\\Users\\voakh\\Downloads\\voak health';
const stagingDir = path.join(rootDir, 'scratch', 'staging_v3');
const designDir = path.join(rootDir, 'design', 'phase-03');
const zipOutput = path.join(rootDir, 'VOAK_PHASE_3_DESIGN_REVIEW_v3.zip');

if (fs.existsSync(stagingDir)) {
  fs.rmSync(stagingDir, { recursive: true, force: true });
}
fs.mkdirSync(stagingDir, { recursive: true });

function copyRecursive(src, dest) {
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
    fs.readdirSync(src).forEach(child => {
      copyRecursive(path.join(src, child), path.join(dest, child));
    });
  } else {
    fs.copyFileSync(src, dest);
  }
}

// 1. Core prototype files
['index.html', 'styles.css', 'app.js', 'serve.js', 'README_PREVIEW.md'].forEach(f => {
  fs.copyFileSync(path.join(designDir, f), path.join(stagingDir, f));
});

// 2. Assets, References, and Verified Screenshots
['assets', 'references', 'screenshots'].forEach(d => {
  copyRecursive(path.join(designDir, d), path.join(stagingDir, d));
});

// 3. Final Reconciled Verification Report in docs/
const docsDest = path.join(stagingDir, 'docs');
fs.mkdirSync(docsDest, { recursive: true });
fs.copyFileSync(
  path.join(rootDir, 'docs', 'phase-03', '20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md'),
  path.join(docsDest, '20_FINAL_VISUAL_EVIDENCE_VERIFICATION.md')
);

console.log('Staging v3 completed.');
let totalFiles = 0;
function countFiles(dir) {
  fs.readdirSync(dir).forEach(f => {
    const p = path.join(dir, f);
    if (fs.statSync(p).isDirectory()) countFiles(p);
    else totalFiles++;
  });
}
countFiles(stagingDir);
console.log(`Total files staged for v3: ${totalFiles}`);

// Create zip with tar
if (fs.existsSync(zipOutput)) fs.unlinkSync(zipOutput);

console.log('Compressing with tar into VOAK_PHASE_3_DESIGN_REVIEW_v3.zip...');
execSync(`tar -a -cf "${zipOutput}" *`, { cwd: stagingDir, stdio: 'inherit' });

const stat = fs.statSync(zipOutput);
console.log(`VOAK_PHASE_3_DESIGN_REVIEW_v3.zip created successfully! Size: ${(stat.size / (1024 * 1024)).toFixed(2)} MB`);
