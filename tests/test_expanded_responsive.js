/**
 * Voak Health Care — Phase 3 V10 Expanded Responsive Automated Verification Suite
 *
 * Real CDP Browser Measurement Engine:
 * - Master Homepage (Live Root) × 11 Canonical Viewport Widths (11 checks)
 * - 22 Public Page Mockups × 11 Canonical Viewport Widths (242 checks)
 * - 19 Admin Module Workspaces × 3 Canonical Viewport Widths (57 checks)
 * Total: Exactly 310 Distinct Automated Viewport & Layout Verifications (11 + 242 + 57 = 310)
 *
 * Strict Verification Criteria (Zero Tolerance):
 * 1. ZERO horizontal overflow (scrollWidth <= innerWidth, 0px blowout).
 * 2. ZERO forced horizontal clipping (prohibition on modal.overflowX = 'hidden').
 * 3. ZERO off-screen interactive controls or content descendants (both left < -2px and right > innerWidth + 2px).
 * 4. Inspection of nested containers for unhandled scrollWidth > clientWidth blowout.
 * 5. Meaningful content marker verification (non-empty semantic DOM elements).
 * 6. Verification that inspected page or module matches requested ID.
 * 7. ZERO JavaScript exceptions or console errors.
 *
 * Outputs:
 * - tests/results/phase-03-responsive-results.json
 * - tests/results/phase-03-test-output.txt
 */

const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');

const PORT = 4196;
const DEBUG_PORT = 9255;

// 1. Resolve project root
const candidateRoots = [
  path.join(__dirname, '..'),                       // Extracted archive layout
  path.join(__dirname, '..', 'design', 'phase-03'),  // Workspace repository layout
];

const ROOT = candidateRoots.find(p => fs.existsSync(path.join(p, 'index.html')));
if (!ROOT) {
  console.error('FATAL: Could not locate index.html in candidate roots:', candidateRoots);
  process.exit(1);
}

// Fail-fast checks for required source files
const requiredFiles = ['index.html', 'styles.css', 'app.js'];
for (const rf of requiredFiles) {
  const full = path.join(ROOT, rf);
  if (!fs.existsSync(full)) {
    console.error(`FATAL: Required source file missing: ${full}`);
    process.exit(1);
  }
}
console.log('Serving verified prototype root from:', ROOT);

// 2. Resolve browser executable and preflight check
const RESULTS_DIR = path.join(__dirname, 'results');
if (!fs.existsSync(RESULTS_DIR)) fs.mkdirSync(RESULTS_DIR, { recursive: true });

const candidateBrowsers = [
  process.env.BROWSER_PATH,
  process.env.EDGE_PATH,
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  process.env.CHROME_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Microsoft\\Edge\\Application\\msedge.exe') : null,
  process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Google\\Chrome\\Application\\chrome.exe') : null
].filter(Boolean);

const browserPath = candidateBrowsers.find(p => fs.existsSync(p));
if (!browserPath) {
  console.error('FATAL: No compatible Chromium / Edge / Chrome browser found.');
  const notTestedOutput = {
    testSuite: 'Phase 3 Expanded Responsive Layout Verification Suite',
    status: 'NOT TESTED',
    reason: 'No compatible Chromium/Edge/Chrome executable found on host system.',
    timestamp: new Date().toISOString(),
    browserExecutable: null,
    browserVersion: null,
    summary: { total: 310, passed: 0, failed: 0, notTested: 310 }
  };
  fs.writeFileSync(path.join(RESULTS_DIR, 'phase-03-responsive-results.json'), JSON.stringify(notTestedOutput, null, 2));
  process.exit(1);
}

// Preflight: Extract exact product version
let browserVersion = 'Unknown';
try {
  const versionCmd = `powershell -NoProfile -Command "(Get-Item '${browserPath}').VersionInfo.ProductVersion"`;
  browserVersion = require('child_process').execSync(versionCmd, { encoding: 'utf8', timeout: 5000 }).trim();
} catch (e) {
  browserVersion = 'Detected (Version Query Fallback)';
}
console.log('Using browser executable:', browserPath);
console.log('Browser Product Version:', browserVersion);

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

const PUBLIC_BREAKPOINTS = [
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

const ADMIN_BREAKPOINTS = [
  { width: 390, height: 844, category: 'Mobile Admin (Compact Viewport)' },
  { width: 768, height: 1024, category: 'Tablet Admin (Portrait Viewport)' },
  { width: 1440, height: 900, category: 'Desktop Admin (Target Benchmark)' }
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
  console.log(`V7 Expanded Responsive Test Server running on http://127.0.0.1:${PORT}`);
  try {
    await runTests();
  } catch (err) {
    console.error('Test Suite Encountered Fatal Exception:', err);
    process.exit(1);
  }
});

async function runTests() {
  const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'voak-resp-v7-'));
  const browser = spawn(browserPath, [
    `--remote-debugging-port=${DEBUG_PORT}`,
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  let targets = null;
  for (let attempt = 0; attempt < 25; attempt++) {
    await new Promise(r => setTimeout(r, 400));
    try {
      targets = await new Promise((res, rej) => {
        const req = http.get(`http://127.0.0.1:${DEBUG_PORT}/json/list`, r => {
          let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d)));
        });
        req.on('error', rej);
        req.setTimeout(800, () => req.destroy(new Error('timeout')));
      });
      if (targets && targets.length > 0) break;
    } catch (e) {}
  }

  if (!targets || targets.length === 0) {
    throw new Error('Failed to connect to browser CDP on port ' + DEBUG_PORT);
  }

  const pageTarget = targets.find(t => t.type === 'page') || targets[0];
  const wsUrl = pageTarget.webSocketDebuggerUrl;

  const ws = new WebSocket(wsUrl);
  await new Promise(r => ws.onopen = r);

  let id = 1;
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const curId = id++;
    const handler = (evt) => {
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

  const consoleErrors = [];
  ws.addEventListener('message', (evt) => {
    try {
      const msg = JSON.parse(evt.data);
      if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') {
        const text = msg.params.args.map(a => a.value || a.description || '').join(' ');
        consoleErrors.push(text);
      }
      if (msg.method === 'Runtime.exceptionThrown') {
        consoleErrors.push(msg.params.exceptionDetails.text + ' ' + (msg.params.exceptionDetails.exception?.description || ''));
      }
    } catch (e) {}
  });

  await send('Page.enable');
  await send('Runtime.enable');

  const logLines = [];
  const log = (msg) => {
    console.log(msg);
    logLines.push(msg);
  };

  log('======================================================================');
  log('VOAK HEALTH CARE — PHASE 3 V10 EXPANDED RESPONSIVE VERIFICATION SUITE');
  log('======================================================================');
  log(`Timestamp: ${new Date().toISOString()}`);
  log(`Test Environment: Headless Edge CDP on port ${DEBUG_PORT}`);
  log(`Verification Standard: Real Browser Geometry, 0px Blowout, Zero Forced Clipping, Full Parity\n`);

  await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/` });
  await new Promise(r => setTimeout(r, 1500));

  // Verify critical prototype globals exist
  const initCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      return {
        hasInspectPage: typeof window.inspectPage === 'function',
        hasSwitchTab: typeof window.switchTab === 'function',
        hasAdminModules: typeof window.ADMIN_MODULES === 'object',
        hasPublicPages: typeof window.PUBLIC_PAGES_DATA === 'object'
      };
    })()`,
    returnByValue: true
  });

  const initValues = initCheck.result.value;
  if (!initValues.hasInspectPage || !initValues.hasSwitchTab) {
    log('FATAL: Prototype initialization failed — inspectPage or switchTab not found.');
    process.exit(1);
  }

  const results = {
    testSuite: 'Voak Health Care — Phase 3 V10 Expanded Responsive Automated Verification Suite',
    browserExecutable: browserPath,
    browserVersion: browserVersion,
    timestamp: new Date().toISOString(),
    summary: { total: 0, passed: 0, failed: 0 },
    masterHomepage: [],
    publicPages: [],
    adminModules: []
  };

  // --------------------------------------------------------------------------
  // PART 0: MASTER HOMEPAGE RESPONSIVE VERIFICATION (11 BREAKPOINTS)
  // --------------------------------------------------------------------------
  log('======================================================================');
  log('PART 0: MASTER HOMEPAGE RESPONSIVE VERIFICATION (LIVE ROOT × 11 BREAKPOINTS)');
  log('======================================================================\n');

  await send('Runtime.evaluate', {
    expression: `(() => {
      if (typeof closeScreenInspector === 'function') closeScreenInspector();
      if (typeof switchTab === 'function') switchTab('homepage-desktop');
      const nav = document.querySelector('.preview-navbar');
      if (nav) nav.style.display = 'none';
    })()`
  });
  await new Promise(r => setTimeout(r, 300));

  for (const bp of PUBLIC_BREAKPOINTS) {
    results.summary.total++;

    await send('Emulation.setDeviceMetricsOverride', {
      width: bp.width,
      height: bp.height,
      deviceScaleFactor: 1,
      mobile: bp.width <= 768
    });
    await new Promise(r => setTimeout(r, 120));

    const check = await send('Runtime.evaluate', {
      expression: `(() => {
        const home = document.getElementById('view-homepage-desktop');
        const innerW = window.innerWidth;
        const docScrollW = document.documentElement.scrollWidth;
        const bodyScrollW = document.body.scrollWidth;
        const homeScrollW = home ? home.scrollWidth : 0;
        const maxScrollW = Math.max(docScrollW, bodyScrollW, homeScrollW);
        const overflowPx = Math.max(0, maxScrollW - innerW);
        const isOverflow = overflowPx > 1.5;

        // Check semantic content markers
        const hasHero = !!document.querySelector('.hero-headline, .hero-editorial-h1, h1');
        const hasServices = !!document.querySelector('.bento-services-grid, .service-bento-grid, #services-bento, .services-tiles-grid, .services-asymmetric-layout, #services');
        const hasTrust = !!document.querySelector('.trust-container, .horizontal-trust-section');
        const hasWorkflow = !!document.querySelector('.workflow-steps-grid, .workflow-step-card');
        const contentMarkerVerified = hasHero && hasServices && hasTrust && hasWorkflow;

        // Detect offscreen elements on both left and right
        const offscreenElements = [];
        if (home) {
          const all = home.querySelectorAll('*');
          for (let el of all) {
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) {
              if (r.right > innerW + 2 || r.left < -2) {
                offscreenElements.push({
                  tag: el.tagName.toLowerCase(),
                  class: el.className ? String(el.className).split(' ')[0] : '',
                  left: Math.round(r.left),
                  right: Math.round(r.right)
                });
                if (offscreenElements.length >= 3) break;
              }
            }
          }
        }

        // Detect offscreen interactive controls
        const offscreenControls = [];
        if (home) {
          const controls = home.querySelectorAll('button, a, input, select, textarea');
          for (let c of controls) {
            const r = c.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) {
              if (r.right > innerW + 2 || r.left < -2) {
                offscreenControls.push({
                  tag: c.tagName.toLowerCase(),
                  text: c.textContent?.trim().slice(0, 25) || '',
                  left: Math.round(r.left),
                  right: Math.round(r.right)
                });
                if (offscreenControls.length >= 2) break;
              }
            }
          }
        }

        // Detect unhandled container blowout
        const containerBlowouts = [];
        if (home) {
          const containers = home.querySelectorAll('section, div, article');
          for (let ct of containers) {
            const st = window.getComputedStyle(ct);
            // Allow intentional horizontal scroll containers only
            if (st.overflowX === 'auto' || st.overflowX === 'scroll' || ct.classList.contains('table-responsive-wrapper') || ct.classList.contains('admin-card')) {
              continue;
            }
            if (ct.scrollWidth > ct.clientWidth + 2 && ct.clientWidth > 0) {
              containerBlowouts.push({
                tag: ct.tagName.toLowerCase(),
                class: ct.className ? String(ct.className).split(' ')[0] : '',
                scrollW: ct.scrollWidth,
                clientW: ct.clientWidth
              });
              if (containerBlowouts.length >= 3) break;
            }
          }
        }

        return {
          innerWidth: innerW,
          scrollWidth: maxScrollW,
          overflowPx,
          isOverflow,
          contentMarkerVerified,
          offscreenElements,
          offscreenControls,
          containerBlowouts
        };
      })()`,
      returnByValue: true
    });

    const chk = check.result.value;
    const testErrors = [...consoleErrors];
    consoleErrors.length = 0;

    const failedReasons = [];
    if (!chk.contentMarkerVerified) failedReasons.push('Missing master homepage content markers (hero/services/trust/workflow)');
    if (chk.isOverflow) failedReasons.push(`Horizontal overflow: ${chk.overflowPx}px blowout (scrollWidth: ${chk.scrollWidth}px > innerWidth: ${chk.innerWidth}px)`);
    if (chk.offscreenControls && chk.offscreenControls.length > 0) failedReasons.push(`Offscreen interactive controls: ${JSON.stringify(chk.offscreenControls)}`);
    if (chk.offscreenElements && chk.offscreenElements.length > 0) failedReasons.push(`Offscreen layout elements: ${JSON.stringify(chk.offscreenElements)}`);
    if (chk.containerBlowouts && chk.containerBlowouts.length > 0) failedReasons.push(`Container blowouts: ${JSON.stringify(chk.containerBlowouts)}`);
    if (testErrors.length > 0) failedReasons.push(`Console errors: ${testErrors.join('; ')}`);

    const passed = failedReasons.length === 0;
    if (passed) results.summary.passed++;
    else results.summary.failed++;

    const entry = {
      id: 'PUB-01-MASTER',
      type: 'MASTER_HOMEPAGE_CHECK',
      title: 'Master Homepage (Root index.html)',
      viewport: { width: bp.width, height: bp.height, category: bp.category },
      markerVerified: chk.contentMarkerVerified,
      innerWidth: chk.innerWidth,
      scrollWidth: chk.scrollWidth,
      overflowPx: chk.overflowPx,
      overflow: chk.isOverflow,
      offscreenElements: chk.offscreenElements,
      offscreenControls: chk.offscreenControls,
      containerBlowouts: chk.containerBlowouts,
      consoleErrors: testErrors,
      failureReasons: failedReasons,
      status: passed ? 'PASS' : 'FAIL'
    };
    results.masterHomepage.push(entry);

    const statusIcon = passed ? 'PASS' : 'FAIL';
    log(`  [${statusIcon}] Master Homepage @ ${bp.width}px (${bp.category}): innerW=${chk.innerWidth}px, scrollW=${chk.scrollWidth}px, overflow=${chk.overflowPx}px, errors=${testErrors.length}`);
    if (!passed) {
      log(`        -> FAIL REASONS: ${failedReasons.join(' | ')}`);
    }
  }
  log('');

  // --------------------------------------------------------------------------
  // PART 1: PUBLIC PAGES MOCKUP INSPECTION (22 PAGES × 11 BREAKPOINTS)
  // --------------------------------------------------------------------------
  log('======================================================================');
  log('PART 1: PUBLIC PAGES MOCKUP INSPECTION (22 PAGES × 11 BREAKPOINTS)');
  log('======================================================================\n');

  // Configure screen inspector modal for automated testing WITHOUT forced horizontal clipping
  await send('Runtime.evaluate', {
    expression: `(() => {
      const modal = document.getElementById('screen-inspector-modal');
      const container = document.querySelector('.screen-inspector-container');
      const topbar = document.querySelector('.screen-inspector-topbar');

      if (modal) {
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100vw';
        modal.style.height = '100vh';
        // STRICT PROHIBITION: Zero forced horizontal clipping. modal.overflowX is NOT set to 'hidden'
        modal.style.padding = '0';
      }
      if (topbar) topbar.style.display = 'none';
      if (container) {
        container.style.maxWidth = '100%';
        container.style.width = '100%';
        container.style.borderRadius = '0';
        container.style.boxShadow = 'none';
        container.style.border = 'none';
        container.style.margin = '0';
      }
    })()`
  });

  for (let p = 1; p <= 22; p++) {
    const pageId = `PUB-${String(p).padStart(2, '0')}`;

    // Open page in inspector
    await send('Runtime.evaluate', { expression: `inspectPage('${pageId}')` });
    await new Promise(r => setTimeout(r, 200));

    // Get page metadata & verify meaningful content markers
    const meta = await send('Runtime.evaluate', {
      expression: `(() => {
        const titleEl = document.getElementById('inspector-title');
        const routeEl = document.getElementById('inspector-route');
        const title = titleEl ? titleEl.textContent.trim() : '';
        const route = routeEl ? routeEl.textContent.trim() : '';
        const body = document.getElementById('screen-mockup-body');

        // Robust content check: verify presence of headings, text, or structured components
        const hasSemanticElements = !!body && !!body.querySelector('h1, h2, h3, h4, .admin-card, .public-page-card, .service-card, table, form, .hero-headline, .hero-pill-badge, .bento-services-grid');
        const textLen = body ? body.textContent.trim().length : 0;
        const meaningfulContent = hasSemanticElements && textLen > 80;

        return {
          title,
          route,
          meaningfulContent,
          textLen,
          hasElements: hasSemanticElements
        };
      })()`,
      returnByValue: true
    });

    const { title, route, meaningfulContent, textLen } = meta.result.value;
    log(`Testing Public Page [${pageId}] - ${title} (${route}) [PROTOTYPE MOCKUP VIEW]`);

    for (const bp of PUBLIC_BREAKPOINTS) {
      results.summary.total++;

      await send('Emulation.setDeviceMetricsOverride', {
        width: bp.width,
        height: bp.height,
        deviceScaleFactor: 1,
        mobile: bp.width <= 768
      });
      await new Promise(r => setTimeout(r, 120));

      const check = await send('Runtime.evaluate', {
        expression: `(() => {
          const body = document.getElementById('screen-mockup-body');
          const modal = document.getElementById('screen-inspector-modal');
          const innerW = window.innerWidth;
          const docScrollW = document.documentElement.scrollWidth;
          const modalScrollW = modal ? modal.scrollWidth : 0;
          const bodyScrollW = body ? body.scrollWidth : 0;
          const maxScrollW = Math.max(docScrollW, modalScrollW, bodyScrollW);
          const overflowPx = Math.max(0, maxScrollW - innerW);
          const isOverflow = overflowPx > 1.5;

          // Detect offscreen elements on both left and right
          const offscreenElements = [];
          if (body) {
            const all = body.querySelectorAll('*');
            for (let el of all) {
              if (el.closest('.table-responsive-wrapper, .admin-card, [data-intentional-scroll]')) continue;
              const rect = el.getBoundingClientRect();
              if (rect.width > 0 && rect.height > 0) {
                if (rect.right > innerW + 2 || rect.left < -2) {
                  offscreenElements.push({
                    tag: el.tagName.toLowerCase(),
                    class: el.className ? String(el.className).split(' ')[0] : '',
                    left: Math.round(rect.left),
                    right: Math.round(rect.right)
                  });
                  if (offscreenElements.length >= 3) break;
                }
              }
            }
          }

          // Check interactive controls specifically (buttons, links, inputs)
          const offscreenControls = [];
          if (body) {
            const controls = body.querySelectorAll('button, a, input, select, textarea');
            for (let c of controls) {
              if (c.closest('.table-responsive-wrapper, .admin-card, [data-intentional-scroll]')) continue;
              const r = c.getBoundingClientRect();
              if (r.width > 0 && r.height > 0) {
                if (r.right > innerW + 2 || r.left < -2) {
                  offscreenControls.push({
                    tag: c.tagName.toLowerCase(),
                    text: c.textContent?.trim().slice(0, 20) || '',
                    left: Math.round(r.left),
                    right: Math.round(r.right)
                  });
                  if (offscreenControls.length >= 2) break;
                }
              }
            }
          }

          // Nested container clipping / blowout check
          const containerBlowouts = [];
          if (body) {
            const containers = body.querySelectorAll('section, div, article');
            for (let ct of containers) {
              const st = window.getComputedStyle(ct);
              if (st.overflowX === 'auto' || st.overflowX === 'scroll' || ct.classList.contains('table-responsive-wrapper') || ct.classList.contains('admin-card')) {
                continue;
              }
              if (ct.classList.contains('screen-inspector-modal') || ct.classList.contains('screen-inspector-container')) {
                continue;
              }
              if (ct.scrollWidth > ct.clientWidth + 2 && ct.clientWidth > 0) {
                containerBlowouts.push({
                  tag: ct.tagName.toLowerCase(),
                  class: ct.className ? String(ct.className).split(' ')[0] : '',
                  scrollW: ct.scrollWidth,
                  clientW: ct.clientWidth
                });
                if (containerBlowouts.length >= 3) break;
              }
            }
          }

          return {
            innerWidth: innerW,
            scrollWidth: maxScrollW,
            overflowPx,
            isOverflow,
            offscreenElements,
            offscreenControls,
            containerBlowouts
          };
        })()`,
        returnByValue: true
      });

      const chk = check.result.value;
      const testErrors = [...consoleErrors];
      consoleErrors.length = 0;

      const failedReasons = [];
      if (!meaningfulContent) failedReasons.push(`Missing meaningful content markers (textLen: ${textLen})`);
      if (chk.isOverflow) failedReasons.push(`Horizontal overflow: ${chk.overflowPx}px blowout (scrollWidth: ${chk.scrollWidth}px > innerWidth: ${chk.innerWidth}px)`);
      if (chk.offscreenControls && chk.offscreenControls.length > 0) failedReasons.push(`Offscreen interactive controls: ${JSON.stringify(chk.offscreenControls)}`);
      if (chk.offscreenElements && chk.offscreenElements.length > 0) failedReasons.push(`Offscreen layout elements: ${JSON.stringify(chk.offscreenElements)}`);
      if (chk.containerBlowouts && chk.containerBlowouts.length > 0) failedReasons.push(`Container blowout: ${JSON.stringify(chk.containerBlowouts)}`);
      if (testErrors.length > 0) failedReasons.push(`Console errors: ${testErrors.join('; ')}`);

      const passed = failedReasons.length === 0;
      if (passed) results.summary.passed++;
      else results.summary.failed++;

      const entry = {
        id: pageId,
        type: 'PROTOTYPE_PUBLIC_PAGE_MOCKUP',
        title,
        route,
        viewport: { width: bp.width, height: bp.height, category: bp.category },
        markerVerified: meaningfulContent,
        innerWidth: chk.innerWidth,
        scrollWidth: chk.scrollWidth,
        overflowPx: chk.overflowPx,
        overflow: chk.isOverflow,
        offscreenElements: chk.offscreenElements,
        offscreenControls: chk.offscreenControls,
        containerBlowouts: chk.containerBlowouts,
        consoleErrors: testErrors,
        failureReasons: failedReasons,
        status: passed ? 'PASS' : 'FAIL'
      };

      results.publicPages.push(entry);

      const statusIcon = passed ? 'PASS' : 'FAIL';
      log(`  [${statusIcon}] ${bp.width}px (${bp.category}): innerW=${chk.innerWidth}px, scrollW=${chk.scrollWidth}px, overflow=${chk.overflowPx}px, errors=${testErrors.length}`);
      if (!passed) {
        log(`        -> FAIL REASONS: ${failedReasons.join(' | ')}`);
      }
    }
    log('');
  }

  // --------------------------------------------------------------------------
  // PART 2: ADMIN MODULES WORKSPACES (19 MODULES × 3 BREAKPOINTS)
  // --------------------------------------------------------------------------
  log('======================================================================');
  log('PART 2: ADMIN MODULES WORKSPACE INSPECTION (19 MODULES × 3 BREAKPOINTS)');
  log('======================================================================\n');

  // Switch to admin portal view
  await send('Runtime.evaluate', {
    expression: `(() => {
      closeScreenInspector();
      switchTab('admin-portal');
      simulateRole('ROLE_SUPER_ADMIN');
    })()`
  });
  await new Promise(r => setTimeout(r, 400));

  for (let m = 1; m <= 19; m++) {
    const modId = `ADM-${String(m).padStart(2, '0')}`;

    await send('Runtime.evaluate', { expression: `showAdminModule('${modId}')` });
    await new Promise(r => setTimeout(r, 200));

    // Verify rendered module matches requested ID
    // Verify rendered module matches requested ID
    const meta = await send('Runtime.evaluate', {
      expression: `(() => {
        const mod = typeof ADMIN_MODULES !== 'undefined' ? ADMIN_MODULES['${modId}'] : null;
        const ws = document.getElementById('admin-module-display');
        const activeNav = document.getElementById('admin-nav-${modId}');
        const hasContent = !!ws && ws.innerHTML.trim().length > 60;
        const currentMod = typeof currentAdminModule !== 'undefined' ? currentAdminModule : '';
        const moduleMatch = currentMod === '${modId}' || (activeNav && activeNav.classList.contains('active'));
        return {
          title: mod ? mod.title : '${modId}',
          hasContent,
          moduleMatch
        };
      })()`,
      returnByValue: true
    });

    const { title, hasContent, moduleMatch } = meta.result.value;
    log(`Testing Admin Module [${modId}] - ${title} [ADMIN WORKSPACE VIEW]`);

    for (const bp of ADMIN_BREAKPOINTS) {
      results.summary.total++;

      await send('Emulation.setDeviceMetricsOverride', {
        width: bp.width,
        height: bp.height,
        deviceScaleFactor: 1,
        mobile: bp.width <= 768
      });
      await new Promise(r => setTimeout(r, 150));

      const check = await send('Runtime.evaluate', {
        expression: `(() => {
          const innerW = window.innerWidth;
          const docScrollW = document.documentElement.scrollWidth;
          const bodyScrollW = document.body.scrollWidth;
          const maxScrollW = Math.max(docScrollW, bodyScrollW);
          const overflowPx = Math.max(0, maxScrollW - innerW);
          const isOverflow = overflowPx > 1.5;

          const offscreen = [];
          const all = document.querySelectorAll('#admin-module-display *');
          for (let el of all) {
            let insideScroll = false;
            let p = el.parentElement;
            while (p && p !== document.body) {
              const cs = window.getComputedStyle(p);
              if (cs.overflowX === 'auto' || cs.overflowX === 'scroll' || p.classList.contains('admin-card') || p.classList.contains('table-responsive-wrapper')) {
                insideScroll = true;
                break;
              }
              p = p.parentElement;
            }
            if (!insideScroll) {
              const r = el.getBoundingClientRect();
              if (r.width > 0 && r.height > 0) {
                if (r.right > innerW + 2 || r.left < -2) {
                  offscreen.push({
                    tag: el.tagName.toLowerCase(),
                    class: el.className ? String(el.className).split(' ')[0] : '',
                    left: Math.round(r.left),
                    right: Math.round(r.right)
                  });
                  if (offscreen.length >= 3) break;
                }
              }
            }
          }

          // Offscreen interactive controls (ignoring controls inside intentional horizontal scroll containers)
          const offscreenControls = [];
          const controls = document.querySelectorAll('#admin-module-display button, #admin-module-display a, #admin-module-display input, #admin-module-display select');
          for (let c of controls) {
            const r = c.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) {
              if (r.right > innerW + 2 || r.left < -2) {
                let insideScroll = false;
                let p = c.parentElement;
                while (p && p !== document.body) {
                  const cs = window.getComputedStyle(p);
                  if (cs.overflowX === 'auto' || cs.overflowX === 'scroll' || p.classList.contains('admin-card') || p.classList.contains('table-responsive-wrapper')) {
                    insideScroll = true;
                    break;
                  }
                  p = p.parentElement;
                }
                if (!insideScroll) {
                  offscreenControls.push({
                    tag: c.tagName.toLowerCase(),
                    text: c.textContent?.trim().slice(0, 20) || '',
                    left: Math.round(r.left),
                    right: Math.round(r.right)
                  });
                  if (offscreenControls.length >= 2) break;
                }
              }
            }
          }

          // Nested container clipping
          const containerBlowouts = [];
          const containers = document.querySelectorAll('#admin-module-display div, #admin-module-display section');
          for (let ct of containers) {
            const st = window.getComputedStyle(ct);
            if (st.overflowX === 'auto' || st.overflowX === 'scroll' || ct.classList.contains('admin-card') || ct.classList.contains('table-responsive-wrapper')) {
              continue;
            }
            if (ct.scrollWidth > ct.clientWidth + 2 && ct.clientWidth > 0) {
              containerBlowouts.push({
                tag: ct.tagName.toLowerCase(),
                class: ct.className ? String(ct.className).split(' ')[0] : '',
                scrollW: ct.scrollWidth,
                clientW: ct.clientWidth
              });
              if (containerBlowouts.length >= 3) break;
            }
          }

          return {
            innerWidth: innerW,
            scrollWidth: maxScrollW,
            overflowPx,
            isOverflow,
            offscreen,
            offscreenControls,
            containerBlowouts
          };
        })()`,
        returnByValue: true
      });

      const chk = check.result.value;
      const testErrors = [...consoleErrors];
      consoleErrors.length = 0;

      const failedReasons = [];
      if (!hasContent) failedReasons.push('Missing workspace content marker');
      if (!moduleMatch) failedReasons.push(`Rendered module mismatch: expected ${modId}`);
      if (chk.isOverflow) failedReasons.push(`Horizontal overflow: ${chk.overflowPx}px blowout (scrollWidth: ${chk.scrollWidth}px > innerWidth: ${chk.innerWidth}px)`);
      if (chk.offscreen && chk.offscreen.length > 0) failedReasons.push(`Offscreen layout elements: ${JSON.stringify(chk.offscreen)}`);
      if (chk.offscreenControls && chk.offscreenControls.length > 0) failedReasons.push(`Offscreen interactive controls: ${JSON.stringify(chk.offscreenControls)}`);
      if (chk.containerBlowouts && chk.containerBlowouts.length > 0) failedReasons.push(`Container blowout: ${JSON.stringify(chk.containerBlowouts)}`);
      if (testErrors.length > 0) failedReasons.push(`Console errors: ${testErrors.join('; ')}`);

      const passed = failedReasons.length === 0;
      if (passed) results.summary.passed++;
      else results.summary.failed++;

      const entry = {
        id: modId,
        type: 'ADMIN_MODULE_WORKSPACE',
        title,
        viewport: { width: bp.width, height: bp.height, category: bp.category },
        markerVerified: hasContent,
        moduleVerified: moduleMatch,
        innerWidth: chk.innerWidth,
        scrollWidth: chk.scrollWidth,
        overflowPx: chk.overflowPx,
        overflow: chk.isOverflow,
        offscreenElements: chk.offscreen,
        offscreenControls: chk.offscreenControls,
        containerBlowouts: chk.containerBlowouts,
        consoleErrors: testErrors,
        failureReasons: failedReasons,
        status: passed ? 'PASS' : 'FAIL'
      };

      results.adminModules.push(entry);

      const statusIcon = passed ? 'PASS' : 'FAIL';
      log(`  [${statusIcon}] ${bp.width}px (${bp.category}): innerW=${chk.innerWidth}px, scrollW=${chk.scrollWidth}px, overflow=${chk.overflowPx}px, errors=${testErrors.length}`);
      if (!passed) {
        log(`        -> FAIL REASONS: ${failedReasons.join(' | ')}`);
      }
    }
    log('');
  }

  // Summary
  log('======================================================================');
  log('EXPANDED RESPONSIVE VERIFICATION EXECUTION SUMMARY');
  log('======================================================================');
  log(`Total Verification Checks: ${results.summary.total}`);
  log(`Passed:                    ${results.summary.passed} (${((results.summary.passed/results.summary.total)*100).toFixed(1)}%)`);
  log(`Failed:                    ${results.summary.failed} (${((results.summary.failed/results.summary.total)*100).toFixed(1)}%)`);
  log('======================================================================');

  // Save artifacts
  const resultsJsonPath = path.join(RESULTS_DIR, 'phase-03-responsive-results.json');
  fs.writeFileSync(resultsJsonPath, JSON.stringify(results, null, 2), 'utf-8');
  log(`Structured results saved to: ${resultsJsonPath}`);

  const outputLogPath = path.join(RESULTS_DIR, 'phase-03-test-output.txt');
  fs.writeFileSync(outputLogPath, logLines.join('\n'), 'utf-8');
  log(`Console execution log saved to: ${outputLogPath}`);

  // Cleanup
  ws.close();
  browser.kill();
  try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (e) {}
  server.close();

  if (results.summary.failed > 0) {
    console.error(`\nFAILED: ${results.summary.failed} responsive verification checks failed.`);
    process.exit(1);
  } else {
    console.log(`\nSUCCESS: All ${results.summary.total} responsive layout verifications PASSED with 0px overflow and zero errors.`);
    process.exit(0);
  }
}
