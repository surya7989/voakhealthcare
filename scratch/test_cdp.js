const { spawn } = require('child_process');
const http = require('http');

const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const userDataDir = 'c:\\Users\\voakh\\Downloads\\voak health\\scratch\\edge-user-data';

console.log('Testing Edge CDP launch...');
const edgeProcess = spawn(edgePath, [
  '--headless=new',
  '--remote-debugging-port=9222',
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  `--user-data-dir=${userDataDir}`,
  'about:blank'
]);

setTimeout(async () => {
  try {
    const list = await new Promise((resolve, reject) => {
      http.get('http://127.0.0.1:9222/json/version', res => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => resolve(JSON.parse(data)));
      }).on('error', reject);
    });
    console.log('CDP Version Info:', list.Browser, 'WebSocket URL:', list.webSocketDebuggerUrl);

    // Clean up
    edgeProcess.kill();
    console.log('Edge launched and exited cleanly!');
  } catch (err) {
    console.error('Error connecting to CDP:', err);
    edgeProcess.kill();
  }
}, 1500);
