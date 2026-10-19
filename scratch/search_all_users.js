const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const TARGET = 'b0483fffc8e2719771c9aa3a587710b67b389b940c21b56d247ace4a2bc971ba';

function scan(dir, depth = 0) {
  if (depth > 5) return;
  try {
    const list = fs.readdirSync(dir, { withFileTypes: true });
    for (const e of list) {
      if (['AppData', '.gemini', '.cache', 'node_modules', '.git'].includes(e.name)) continue;
      const full = path.join(dir, e.name);
      if (e.isDirectory()) {
        scan(full, depth + 1);
      } else {
        try {
          const stat = fs.statSync(full);
          const diffHours = (Date.now() - stat.mtimeMs) / (1000 * 60 * 60);
          if (diffHours < 24) {
            if (stat.size > 20000 && stat.size < 50000000) {
              const buf = fs.readFileSync(full);
              const hash = crypto.createHash('sha256').update(buf).digest('hex');
              if (hash === TARGET) {
                console.log('!!! MATCH FOUND !!!', full);
              }
              if (buf.subarray(0, 8).toString('hex') === '89504e470d0a1a0a') {
                const w = buf.readUInt32BE(16);
                const h = buf.readUInt32BE(20);
                if (w === 1214 && h === 1295) {
                  console.log('!!! 1214x1295 FOUND !!!', full, hash);
                }
              }
            }
          }
        } catch(err) {}
      }
    }
  } catch(err) {}
}

console.log('Scanning C:\\Users for any files modified in last 24 hours...');
scan('C:\\Users');
console.log('Scan completed.');
