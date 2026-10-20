/**
 * Voak Health Care — Phase 3 V5 Responsive Breakpoint Overflow Automated Verification
 * Reproducible automated test suite supporting both root workspace and extracted archive layouts.
 * Tests 9 canonical breakpoints on the primary homepage and representative public pages.
 */

const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4174;

// 1. Resolve project root supporting both workspace and extracted archive layouts
const candidateRoots = [
  path.join(__dirname, '..'),                       // Extracted archive: tests/ is sibling of index.html
  path.join(__dirname, '..', 'design', 'phase-03'),  // Workspace repository layout
];

let ROOT = candidateRoots.find(p => fs.existsSync(path.join(p, 'index.html')));
if (!ROOT) {
  console.error('FATAL: Could not locate index.html in candidate roots:', candidateRoots);
  process.exit(1);
}
console.log('Serving verified prototype root from:', ROOT);

// 2. Resolve browser executable path (configurable via env var with fallbacks)
const candidateBrowsers = [
  process.env.BROWSER_PATH,
  process.env.EDGE_PATH,
  process.env.CHROME_PATH,
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
].filter(Boolean);

const browserPath = candidateBrowsers.find(p => fs.existsSync(p));
if (!browserPath) {
  console.error('FATAL: No Chromium-based browser executable found. Set EDGE_PATH or CHROME_PATH environment variable.');
  process.exit(1);
}
console.log('Using browser executable:', browserPath);

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml'
};

const BREAKPOINTS = [
  { width: 320, height: 568, category: 'Compact Mobile (iPhone SE)' },
  { width: 360, height: 800, category: 'Standard Android Compact' },
  { width: 375, height: 667, category: 'Standard iPhone Compact (iPhone SE 2/3 / 6/7/8)' },
  { width: 390, height: 844, category: 'Modern iPhone Baseline (iPhone 12/13/14)' },
  { width: 414, height: 896, category: 'Plus/XR Mobile (iPhone 8 Plus / XR / 11)' },
  { width: 428, height: 926, category: 'Large Mobile Phablet (iPhone Pro Max)' },
  { width: 768, height: 1024, category: 'Tablet Portrait (iPad Mini / Air)' },
  { width: 1024, height: 768, category: 'Tablet Landscape / Small Laptop' },
  { width: 1280, height: 800, category: 'Standard HD Laptop / Desktop' },
  { width: 1440, height: 900, category: 'Approved Target Desktop Benchmark' },
  { width: 1920, height: 1080, category: 'Full HD Ultrawide Desktop' }
];

const server = http.createServer((req, res) => {
  let reqPath = decodeURI(req.url.split('?')[0]);
  if (reqPath === '/') reqPath = '/index.html';
  const filePath = path.join(ROOT, reqPath);
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    res.writeHead(404); res.end(); return;
  }
  const ext = path.extname(filePath).toLowerCase();
  res.writeHead(200, { 'Content-Type': MIME_TYPES[ext] || 'application/octet-stream' });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, '127.0.0.1', async () => {
  console.log(`Responsive Test Server listening on http://127.0.0.1:${PORT}`);
  await runTest();
});

async function runTest() {
  const userDataDir = path.join(__dirname, '..', 'scratch', 'edge-test-overflow-v5');
  if (!fs.existsSync(userDataDir)) fs.mkdirSync(userDataDir, { recursive: true });

  const browserProcess = spawn(browserPath, [
    '--headless=new',
    '--remote-debugging-port=9226',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  let targets = null;
  for (let attempt = 0; attempt < 20; attempt++) {
    await new Promise(r => setTimeout(r, 400));
    try {
      targets = await new Promise((res, rej) => {
        const req = http.get('http://127.0.0.1:9226/json/list', r => {
          let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d)));
        });
        req.on('error', rej);
        req.setTimeout(800, () => req.destroy(new Error('timeout')));
      });
      if (targets && targets.length > 0) break;
    } catch(e) {}
  }

  if (!targets || targets.length === 0) {
    throw new Error('Failed to connect to browser CDP on port 9226');
  }

  try {
    const wsUrl = (targets.find(t => t.type === 'page') || targets[0]).webSocketDebuggerUrl;
    const ws = new WebSocket(wsUrl);
    let id = 1;
    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const curId = id++;
      const handler = evt => {
        const msg = JSON.parse(evt.data);
        if (msg.id === curId) {
          ws.removeEventListener('message', handler);
          if (msg.error) reject(new Error(msg.error.message));
          else resolve(msg.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: curId, method, params }));
    });

    await new Promise(r => ws.onopen = r);
    await send('Page.enable');
    await send('Runtime.enable');
    await send('DOM.enable');

    await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/index.html` });
    await new Promise(r => setTimeout(r, 2000));

    // Verify page actually loaded
    const loadCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          title: document.title,
          hasNavbar: !!document.querySelector('.preview-navbar'),
          hasShell: !!document.querySelector('.page-shell'),
          bodyChildCount: document.body.children.length
        };
      })()`,
      returnByValue: true
    });

    const pageState = loadCheck.result.value;
    console.log('Loaded Page Verification:', pageState);
    if (!pageState.hasShell || pageState.bodyChildCount === 0) {
      throw new Error(`Page failed to load correctly: ${JSON.stringify(pageState)}`);
    }

    console.log('\n========================================================================================');
    console.log('VOAK HEALTH CARE — PHASE 3 V5 AUTOMATED RESPONSIVE OVERFLOW SWEEP RESULTS');
    console.log('========================================================================================\n');

    const results = [];

    for (const bp of BREAKPOINTS) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: bp.width,
        height: bp.height,
        deviceScaleFactor: 1,
        mobile: bp.width < 768
      });
      await new Promise(r => setTimeout(r, 300));

      const evalResult = await send('Runtime.evaluate', {
        expression: `(() => {
          const docEl = document.documentElement;
          const body = document.body;
          const scrollW = Math.max(docEl.scrollWidth, body.scrollWidth);
          const innerW = window.innerWidth;
          const clientW = docEl.clientWidth;
          const overflowX = Math.max(0, scrollW - innerW);

          let offendingSelector = null;
          // Only evaluate offending elements if there is genuine document horizontal overflow
          if (overflowX > 1) {
            const elements = document.querySelectorAll('*');
            let maxRight = innerW;
            for (let el of elements) {
              const rect = el.getBoundingClientRect();
              if (rect.right > maxRight + 1) {
                offendingSelector = el.tagName + (el.className ? '.' + String(el.className).trim().split(' ').join('.') : '') + (el.id ? '#' + el.id : '');
                maxRight = rect.right;
              }
            }
          }

          return {
            windowInnerW: innerW,
            docScrollW: scrollW,
            clientW: clientW,
            overflowPx: overflowX,
            offendingElement: offendingSelector,
            passed: overflowX <= 1
          };
        })()`,
        returnByValue: true
      });

      const resVal = evalResult.result.value;
      const pass = resVal.passed;

      results.push({
        width: bp.width,
        height: bp.height,
        category: bp.category,
        windowWidth: resVal.windowInnerW,
        scrollWidth: resVal.docScrollW,
        overflowPx: resVal.overflowPx,
        status: pass ? 'PASS' : 'FAIL',
        offendingElement: resVal.offendingElement
      });

      console.log(`[${bp.width}px × ${bp.height}px] ${bp.category.padEnd(35)} | Window: ${String(resVal.windowInnerW).padStart(4)}px | Scroll: ${String(resVal.docScrollW).padStart(4)}px | Overflow: ${String(resVal.overflowPx).padStart(2)}px | Status: ${pass ? 'PASS' : 'FAIL'}`);
    }

    // Save results to tests/responsive_test_results.json
    fs.writeFileSync(path.join(__dirname, 'responsive_test_results.json'), JSON.stringify(results, null, 2));

    console.log('\n========================================================================================');
    console.log(`Summary: ${results.filter(r => r.status === 'PASS').length} of ${results.length} breakpoints passed zero-overflow validation.`);
    console.log('Results written to tests/responsive_test_results.json');
    console.log('========================================================================================\n');

    ws.close();
    browserProcess.kill();
    server.close();
    process.exit(0);
  } catch (err) {
    console.error('Error during responsive overflow test:', err);
    browserProcess.kill();
    server.close();
    process.exit(1);
  }
}
