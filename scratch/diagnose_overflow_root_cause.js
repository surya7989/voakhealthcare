const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const PORT = 4198;
const DEBUG_PORT = 9258;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');

const MIME = {
  '.html': 'text/html',
  '.css': 'text/css',
  '.js': 'application/javascript',
  '.json': 'application/json',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml'
};

const server = http.createServer((req, res) => {
  let reqPath = req.url.split('?')[0];
  if (reqPath === '/') reqPath = '/index.html';
  const filePath = path.join(ROOT, reqPath);
  if (!fs.existsSync(filePath)) {
    res.writeHead(404);
    res.end('Not found');
    return;
  }
  const ext = path.extname(filePath);
  res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, async () => {
  console.log(`Server running at http://localhost:${PORT}`);
  
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-diagnose-overflow');
  if (!fs.existsSync(userDataDir)) fs.mkdirSync(userDataDir, { recursive: true });
  
  const browser = spawn(edgePath, [
    `--remote-debugging-port=${DEBUG_PORT}`,
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);
  
  await new Promise(r => setTimeout(r, 1500));
  
  const httpGet = (url) => new Promise((resolve, reject) => {
    http.get(url, res => {
      let data = '';
      res.on('data', d => data += d);
      res.on('end', () => resolve(JSON.parse(data)));
    }).on('error', reject);
  });
  
  try {
    const list = await httpGet(`http://localhost:${DEBUG_PORT}/json/list`);
    const pageTarget = list.find(t => t.type === 'page') || list[0];
    const wsUrl = pageTarget.webSocketDebuggerUrl;
    
    const ws = new WebSocket(wsUrl);
    
    await new Promise((res, rej) => {
      ws.onopen = res;
      ws.onerror = rej;
    });
    
    let msgId = 1;
    const send = (method, params = {}) => new Promise((resolve) => {
      const id = msgId++;
      const handler = (evt) => {
        const msg = JSON.parse(evt.data);
        if (msg.id === id) {
          ws.removeEventListener('message', handler);
          resolve(msg.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id, method, params }));
    });
    
    await send('Page.enable');
    await send('DOM.enable');
    await send('Runtime.enable');
    
    // Navigate
    await send('Page.navigate', { url: `http://localhost:${PORT}/index.html` });
    await new Promise(r => setTimeout(r, 2000));
    
    // Breakpoints required by prompt: 320, 360, 375, 390, 414, 428, 768, 1024, 1280, 1440
    const viewports = [320, 360, 375, 390, 414, 428, 768, 1024, 1280, 1440];
    
    console.log('\n--- 1. MASTER HOMEPAGE WITH ALL overflow-x: hidden REMOVED ---');
    
    // Remove all overflow-x: hidden from body and everything
    const removalCode = `
      (() => {
        const style = document.createElement('style');
        style.id = 'kill-overflow-hidden';
        style.innerHTML = 'body, html, .admin-shell, .admin-main-area, .admin-sidebar { overflow-x: visible !important; }';
        document.head.appendChild(style);
        return true;
      })()
    `;
    await send('Runtime.evaluate', { expression: removalCode });
    
    for (const w of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: w,
        height: 800,
        deviceScaleFactor: 1,
        mobile: w <= 768
      });
      await new Promise(r => setTimeout(r, 200));
      
      const evalRes = await send('Runtime.evaluate', {
        expression: `
          (() => {
            const innerW = window.innerWidth;
            const scrollW = document.documentElement.scrollWidth;
            const diff = scrollW - innerW;
            
            const offenders = [];
            const all = document.querySelectorAll('*');
            for (const el of all) {
              const rect = el.getBoundingClientRect();
              if (rect.right > innerW + 1) {
                const style = window.getComputedStyle(el);
                if (style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0) {
                  offenders.push({
                    tag: el.tagName,
                    id: el.id,
                    className: el.className,
                    right: Math.round(rect.right),
                    left: Math.round(rect.left),
                    width: Math.round(rect.width),
                    overflow: Math.round(rect.right - innerW),
                    textSnippet: el.innerText ? el.innerText.trim().slice(0, 30) : ''
                  });
                }
              }
            }
            return { innerW, scrollW, diff, offendersCount: offenders.length, topOffenders: offenders.slice(0, 3) };
          })()
        `,
        returnByValue: true
      });
      
      const resVal = evalRes.result.value;
      if (resVal.diff > 0) {
        console.log(`Viewport ${w}px: innerW=${resVal.innerW}, scrollW=${resVal.scrollW}, blowout=${resVal.diff}px [OFFENDERS: ${resVal.offendersCount}]`);
        for (const off of resVal.topOffenders) {
          console.log(`   - <${off.tag} id="${off.id}" class="${off.className}"> right=${off.right}px (+${off.overflow}px past edge) w=${off.width}px text="${off.textSnippet}"`);
        }
      } else {
        console.log(`Viewport ${w}px: innerW=${resVal.innerW}, scrollW=${resVal.scrollW}, blowout=0px [PASS]`);
      }
    }
    
    // Now test Admin Modules with overflow-x: hidden removed!
    console.log('\n--- 2. ADMIN MODULE WORKSPACES WITH ALL overflow-x: hidden REMOVED ---');
    for (const w of [390, 768, 1440]) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: w,
        height: 800,
        deviceScaleFactor: 1,
        mobile: w <= 768
      });
      await send('Runtime.evaluate', { expression: `window.switchTab('admin-portal'); window.showAdminModule('ADM-01');` });
      await new Promise(r => setTimeout(r, 300));
      
      const evalRes = await send('Runtime.evaluate', {
        expression: `
          (() => {
            const innerW = window.innerWidth;
            const scrollW = document.documentElement.scrollWidth;
            const diff = scrollW - innerW;
            const offenders = [];
            const all = document.querySelectorAll('*');
            for (const el of all) {
              const rect = el.getBoundingClientRect();
              if (rect.right > innerW + 1) {
                const style = window.getComputedStyle(el);
                if (style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0) {
                  offenders.push({
                    tag: el.tagName,
                    id: el.id,
                    className: el.className,
                    right: Math.round(rect.right),
                    left: Math.round(rect.left),
                    width: Math.round(rect.width),
                    overflow: Math.round(rect.right - innerW),
                    textSnippet: el.innerText ? el.innerText.trim().slice(0, 30) : ''
                  });
                }
              }
            }
            return { innerW, scrollW, diff, offendersCount: offenders.length, topOffenders: offenders.slice(0, 5) };
          })()
        `,
        returnByValue: true
      });
      
      const resVal = evalRes.result.value;
      if (resVal.diff > 0) {
        console.log(`Admin at ${w}px: innerW=${resVal.innerW}, scrollW=${resVal.scrollW}, blowout=${resVal.diff}px [OFFENDERS: ${resVal.offendersCount}]`);
        for (const off of resVal.topOffenders) {
          console.log(`   - <${off.tag} id="${off.id}" class="${off.className}"> right=${off.right}px (+${off.overflow}px past edge) w=${off.width}px text="${off.textSnippet}"`);
        }
      } else {
        console.log(`Admin at ${w}px: innerW=${resVal.innerW}, scrollW=${resVal.scrollW}, blowout=0px [PASS]`);
      }
    }
    
    ws.close();
  } catch (err) {
    console.error('Error during diagnosis:', err);
  } finally {
    browser.kill();
    server.close();
    process.exit(0);
  }
});
