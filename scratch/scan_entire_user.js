const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const TARGET = 'b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba';

let found = null;
let scannedCount = 0;

function scan(dir, depth = 0) {
  if (found || depth > 6) return;
  try {
    const list = fs.readdirSync(dir, { withFileTypes: true });
    for (const e of list) {
      if (found) return;
      if (['AppData', '.gemini', '.cache', 'node_modules', '.git', '.rustup', '.cargo'].includes(e.name)) continue;
      const full = path.join(dir, e.name);
      if (e.isDirectory()) {
        scan(full, depth + 1);
      } else {
        try {
          const stat = fs.statSync(full);
          // Image files of typical size between 50KB and 50MB
          if (stat.size > 50000 && stat.size < 50000000) {
            scannedCount++;
            const buf = fs.readFileSync(full);
            const hash = crypto.createHash('sha256').update(buf).digest('hex');
            if (hash === TARGET) {
              found = full;
              console.log('!!! EXACT SHA-256 MATCH FOUND !!!:', full);
              return;
            }
            if (buf.subarray(0, 8).toString('hex') === '89504e470d0a1a0a') {
              const w = buf.readUInt32BE(16);
              const h = buf.readUInt32BE(20);
              if (w === 1214 && h === 1295) {
                console.log('!!! 1214x1295 MATCH FOUND (Different hash?):', full, hash);
              }
            }
          }
        } catch(err) {}
      }
    }
  } catch(err) {}
}

console.log('Deep scanning C:\\Users\\voakh...');
scan('C:\\Users\\voakh');
console.log('Scanned file count:', scannedCount);
if (!found) {
  console.log('Search completed: TARGET NOT FOUND.');
}
