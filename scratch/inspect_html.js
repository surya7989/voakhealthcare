const fs = require('fs');
const html = fs.readFileSync('design/phase-03/index.html', 'utf8');
const lines = html.split('\n');
console.log('Total lines in index.html:', lines.length);

lines.forEach((l, idx) => {
  if (l.includes('<section') || l.includes('id="hero"') || l.includes('id="services"') || l.includes('id="about"') || l.includes('id="view-reference-comparison"')) {
    console.log(`Line ${idx+1}: ${l.trim()}`);
  }
});
