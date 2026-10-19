const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const TARGET_HASH = 'b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba';
const home = 'C:\\Users\\voakh';

const targetDirs = [
  path.join(home, 'Downloads'),
  path.join(home, 'Desktop'),
  path.join(home, 'Pictures'),
  path.join(home, 'Documents'),
  path.join(home, 'OneDrive', 'Desktop'),
  path.join(home, 'OneDrive', 'Pictures'),
  path.join(home, 'OneDrive', 'Documents'),
  path.join(home, 'OneDrive', 'Attachments'),
  path.join(home, 'Downloads', 'voak health')
];

let totalFilesChecked = 0;
let matchFound = null;

function scanDir(dirPath, depth = 0) {
  if (depth > 3 || matchFound) return;
  try {
    const items = fs.readdirSync(dirPath, { withFileTypes: true });
    for (const item of items) {
      if (matchFound) return;
      if (['node_modules', '.git', 'scratch', 'AppData', '.gemini'].includes(item.name)) continue;
      const fullPath = path.join(dirPath, item.name);
      if (item.isDirectory()) {
        scanDir(fullPath, depth + 1);
      } else {
        try {
          const stat = fs.statSync(fullPath);
          // Only check files in reasonable image range: 10KB to 40MB
          if (stat.size >= 10000 && stat.size <= 40000000) {
            totalFilesChecked++;
            const buf = fs.readFileSync(fullPath);
            const hash = crypto.createHash('sha256').update(buf).digest('hex');
            if (hash === TARGET_HASH) {
              matchFound = {
                path: fullPath,
                size: buf.length,
                hash: hash
              };
              console.log('FOUND EXACT TARGET HASH MATCH:', fullPath);
              return;
            }
          }
        } catch (e) {}
      }
    }
  } catch (e) {}
}

console.log('Starting targeted search across project common download locations...');
for (const d of targetDirs) {
  if (fs.existsSync(d)) {
    console.log('Scanning directory:', d);
    scanDir(d, 0);
  } else {
    console.log('Directory does not exist:', d);
  }
}

console.log('Total files hashed & checked:', totalFilesChecked);
if (matchFound) {
  console.log('MATCH FOUND DETAILS:', JSON.stringify(matchFound, null, 2));
} else {
  console.log('RESULT: No file matching target hash found.');
}
