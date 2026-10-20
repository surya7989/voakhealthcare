const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const PORT = 4199;
const DEBUG_PORT = 9259;
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
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-diagnose-all');
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
    await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
    
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
    
    await send('Page.navigate', { url: `http://localhost:${PORT}/index.html` });
    await new Promise(r => setTimeout(r, 2000));
    
    // Inject style that strips ALL overflow-x: hidden from body and admin classes
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
    
    const viewports = [320, 360, 375, 390, 414, 428, 768, 1024, 1280, 1440];
    const blowoutResults = [];
    
    // 1. Check all 22 public pages at 320, 375, 768, 1440
    console.log('Testing 22 Public Pages with overflow-x: hidden removed...');
    for (let i = 1; i <= 22; i++) {
      const pageId = `PUB-${i < 10 ? '0' + i : i}`;
      for (const w of [320, 375, 768, 1440]) {
        await send('Emulation.setDeviceMetricsOverride', { width: w, height: 800, deviceScaleFactor: 1, mobile: w <= 768 });
        await send('Runtime.evaluate', { expression: `window.switchTab('public-pages'); window.inspectPage('${pageId}');` });
        await new Promise(r => setTimeout(r, 100));
        
        const evalRes = await send('Runtime.evaluate', {
          expression: `
            (() => {
              const innerW = window.innerWidth;
              const scrollW = document.documentElement.scrollWidth;
              const modal = document.querySelector('.screen-inspector-modal') || document.querySelector('.mockup-modal');
              const modalScrollW = modal ? modal.scrollWidth : 0;
              const modalClientW = modal ? modal.clientWidth : 0;
              return { innerW, scrollW, diff: scrollW - innerW, modalDiff: modalScrollW - modalClientW };
            })()
          `,
          returnByValue: true
        });
        const v = evalRes.result.value;
        if (v.diff > 0 || v.modalDiff > 0) {
          blowoutResults.push({ target: `Public ${pageId}`, width: w, diff: v.diff, modalDiff: v.modalDiff });
        }
      }
    }
    
    // 2. Check all 19 Admin Modules at 320, 390, 768, 1440
    console.log('Testing 19 Admin Modules with overflow-x: hidden removed...');
    for (let i = 1; i <= 19; i++) {
      const modId = `ADM-${i < 10 ? '0' + i : i}`;
      for (const w of [320, 390, 768, 1440]) {
        await send('Emulation.setDeviceMetricsOverride', { width: w, height: 800, deviceScaleFactor: 1, mobile: w <= 768 });
        await send('Runtime.evaluate', { expression: `window.switchTab('admin-portal'); window.showAdminModule('${modId}');` });
        await new Promise(r => setTimeout(r, 100));
        
        const evalRes = await send('Runtime.evaluate', {
          expression: `
            (() => {
              const innerW = window.innerWidth;
              const scrollW = document.documentElement.scrollWidth;
              const diff = scrollW - innerW;
              
              // Find any elements sticking out past innerW (except inside legitimate horizontally scrollable containers)
              const offenders = [];
              const all = document.querySelectorAll('*');
              for (const el of all) {
                const rect = el.getBoundingClientRect();
                if (rect.right > innerW + 1) {
                  const style = window.getComputedStyle(el);
                  if (style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0) {
                    // Check if inside intentional scroll container
                    let p = el.parentElement;
                    let insideScroll = false;
                    while (p && p !== document.body) {
                      const ps = window.getComputedStyle(p);
                      if (ps.overflowX === 'auto' || ps.overflowX === 'scroll') {
                        insideScroll = true;
                        break;
                      }
                      p = p.parentElement;
                    }
                    if (!insideScroll) {
                      offenders.push({ tag: el.tagName, id: el.id, className: el.className, right: Math.round(rect.right), overflow: Math.round(rect.right - innerW) });
                    }
                  }
                }
              }
              return { innerW, scrollW, diff, unhandledOffenders: offenders.length, top: offenders.slice(0, 2) };
            })()
          `,
          returnByValue: true
        });
        const v = evalRes.result.value;
        if (v.diff > 0 || v.unhandledOffenders > 0) {
          blowoutResults.push({ target: `Admin ${modId}`, width: w, diff: v.diff, unhandled: v.unhandledOffenders, top: v.top });
        }
      }
    }
    
    console.log(`\n======================================================`);
    console.log(`TOTAL BLOWOUTS FOUND: ${blowoutResults.length}`);
    if (blowoutResults.length > 0) {
      for (const b of blowoutResults) {
        console.log(`  BLOWOUT: ${b.target} at ${b.width}px -> page blowout: ${b.diff}px, details:`, b.top || b.modalDiff);
      }
    } else {
      console.log('ALL PUBLIC PAGES AND ADMIN MODULES REMAIN 100% 0px BLOWOUT!');
    }
    console.log(`======================================================`);
    
    ws.close();
  } catch (err) {
    console.error('Error:', err);
  } finally {
    browser.kill();
    server.close();
    process.exit(0);
  }
});
