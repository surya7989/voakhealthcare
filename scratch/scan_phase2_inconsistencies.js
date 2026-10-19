const fs = require('fs');
const path = require('path');

const dir = 'docs/phase-02';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.md'));

console.log('Scanning ' + files.length + ' markdown files in ' + dir + '...\n');

const checks = [
  { name: 'Table count 26', regex: /\b26\s+tables?\b|\b26\s+entities\b|\b26\s+relational\b/gi },
  { name: 'Table count 28', regex: /\b28\s+tables?\b|\b28\s+entities\b|\b28\s+relational\b/gi },
  { name: 'Endpoint count 44', regex: /\b44\s+endpoints?\b|\b44\s+operations?\b|\b44\s+rest\b/gi },
  { name: 'Permission count 42', regex: /\b42\s+permissions?\b|\b42\s+granular\b|\b42-permission\b/gi },
  { name: 'Idle timeout 30m', regex: /30\s*min(?:utes?)?\s*(?:inactivity|idle|timeout)/gi },
  { name: 'Idle timeout 15m', regex: /15\s*min(?:utes?)?\s*(?:inactivity|idle|timeout)/gi },
  { name: 'DOMPurify in PHP', regex: /DOMPurify/gi },
  { name: 'X-XSS-Protection', regex: /X-XSS-Protection/gi },
  { name: 'Sample u123456789 path', regex: /u123456789/gi }
];

files.forEach(f => {
  const content = fs.readFileSync(path.join(dir, f), 'utf8');
  const lines = content.split('\n');
  checks.forEach(c => {
    lines.forEach((l, i) => {
      if (l.match(c.regex)) {
        console.log(`[${c.name}] ${f}:${i+1}: ${l.trim()}`);
      }
    });
  });
});
