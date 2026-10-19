const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const outDir = path.join(__dirname, 'screenshots');
fs.mkdirSync(outDir, { recursive: true });

const captures = [
  { name: '01_desktop_homepage.png', width: 1440, height: 1100, url: 'http://127.0.0.1:4173/' },
  { name: '02_mobile_homepage_390px.png', width: 420, height: 900, url: 'http://127.0.0.1:4173/#mobile' },
  { name: '03_tablet_homepage_768px.png', width: 768, height: 1024, url: 'http://127.0.0.1:4173/' },
  { name: '04_public_pages_gallery.png', width: 1440, height: 1100, url: 'http://127.0.0.1:4173/#pages' },
  { name: '05_admin_dashboard.png', width: 1440, height: 1000, url: 'http://127.0.0.1:4173/#admin' },
  { name: '06_approved_reference_comparison.png', width: 1440, height: 1000, url: 'http://127.0.0.1:4173/#refs' },
  { name: '07_component_system.png', width: 1440, height: 1100, url: 'http://127.0.0.1:4173/#components' }
];

console.log('Beginning headless visual screenshot capture via Microsoft Edge...');
captures.forEach(c => {
  const dest = path.join(outDir, c.name);
  const cmd = `"${edgePath}" --headless --disable-gpu --virtual-time-budget=2000 --window-size=${c.width},${c.height} --screenshot="${dest}" "${c.url}"`;
  console.log(`Capturing ${c.name} (${c.width}x${c.height})...`);
  try {
    execSync(cmd, { stdio: 'pipe', timeout: 20000 });
    const stat = fs.statSync(dest);
    console.log(`Successfully saved ${c.name} (${stat.size} bytes)`);
  } catch (err) {
    console.error(`Failed ${c.name}:`, err.message);
  }
});
