const fs = require('fs');
const path = require('path');

const historyPaths = [
  'C:\\Users\\voakh\\AppData\\Local\\Microsoft\\Edge\\User Data\\Default\\History',
  'C:\\Users\\voakh\\AppData\\Local\\Google\\Chrome\\User Data\\Default\\History',
  'C:\\Users\\voakh\\AppData\\Local\\BraveSoftware\\Brave-Browser\\User Data\\Default\\History'
];

for (const hp of historyPaths) {
  if (fs.existsSync(hp)) {
    console.log('Checking history at:', hp);
    try {
      // Copy to temp to avoid lock
      const tmp = path.join(__dirname, 'hist_tmp');
      fs.copyFileSync(hp, tmp);
      const content = fs.readFileSync(tmp, 'binary');
      fs.unlinkSync(tmp);

      // Search for target hash or recently downloaded paths
      const matches = content.match(/[A-Z]:\\[^\x00-\x1f"<>|*?]+\.(png|jpg|jpeg|webp)/gi);
      if (matches) {
        console.log('Found recent download targets:');
        const unique = [...new Set(matches)];
        console.log(unique.slice(-25));
      }
    } catch(err) {
      console.log('Error reading history:', err.message);
    }
  }
}
