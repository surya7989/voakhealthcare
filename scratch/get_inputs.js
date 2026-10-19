const fs = require('fs');
const p = 'C:\\Users\\voakh\\.gemini\\antigravity-ide\\brain\\6c3b6396-7568-4eaf-a908-5afcfe7fdd38\\.system_generated\\logs\\transcript.jsonl';
const lines = fs.readFileSync(p, 'utf8').split('\n');
for (const line of lines) {
  if (line.includes('"type":"USER_INPUT"')) {
    try {
      const obj = JSON.parse(line);
      console.log('--- USER INPUT ---');
      console.log(obj.content ? obj.content.substring(0, 500) : 'no content');
    } catch(e) {}
  }
}
