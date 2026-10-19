// Apache Rewrite Rule Simulator for Hostinger PHP/HTML Deployment

const rules = [
  // 1. Canonical HTTPS Enforcement (Cloudflare & Hostinger Reverse Proxy Safe)
  // RewriteCond %{HTTPS} off
  // RewriteCond %{HTTP:X-Forwarded-Proto} !https
  // RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

  // 2. Deny direct access to sensitive files
  {
    name: 'Deny Sensitive Files',
    pattern: /^(\.env|\.git|\.agents|config\/|storage\/|vendor\/|cli\/|composer\.(json|lock)|package(-lock)?\.json)/,
    action: (uri) => ({ status: 403, target: 'Forbidden' })
  },
  {
    name: 'Deny Sensitive Extensions',
    pattern: /\.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist|md|json)$/,
    action: (uri) => ({ status: 403, target: 'Forbidden' })
  },

  // 3. Stop immediately for real physical files & directories
  // Handled via isPhysicalFile check

  // 4. Prevent self-rewriting / recursion for target entry points
  {
    name: 'Passthrough Target Entry Points',
    pattern: /^(index\.php|api\/index\.php|admin\/index\.html)$/,
    action: (uri) => ({ status: 200, target: uri, passthrough: true })
  },

  // 5. Route API requests
  {
    name: 'Route API Requests',
    pattern: /^api(\/.*)?$/,
    action: (uri) => ({ status: 200, target: 'api/index.php', rewritten: true })
  },

  // 6. Route Admin SPA requests
  {
    name: 'Route Admin SPA Requests',
    pattern: /^admin(\/.*)?$/,
    action: (uri) => ({ status: 200, target: 'admin/index.html', rewritten: true })
  },

  // 7. Route all other requests to Public PHP Front Controller
  {
    name: 'Route Public Front Controller',
    pattern: /^.*$/,
    action: (uri) => ({ status: 200, target: 'index.php', rewritten: true })
  }
];

// Physical files mock
const physicalFiles = new Set([
  'assets/app-example.js',
  'assets/example.css',
  'favicon.ico',
  'robots.txt',
  'sitemap.xml',
  'index.php',
  'api/index.php',
  'admin/index.html'
]);

function simulateRequest(rawUri) {
  // Strip leading slash
  let uri = rawUri.replace(/^\//, '');
  if (uri === '') uri = '/';

  // Check physical file match first (RewriteCond %{REQUEST_FILENAME} -f [OR] -d)
  if (physicalFiles.has(uri) && !uri.startsWith('.env') && !uri.startsWith('storage/')) {
    return { input: rawUri, status: 200, target: uri, rule: 'Physical File Served Directly', loopCount: 1 };
  }

  let currentUri = uri;
  let loops = 0;
  const maxLoops = 10;

  while (loops < maxLoops) {
    loops++;
    let matched = false;

    for (const rule of rules) {
      if (rule.pattern.test(currentUri)) {
        matched = true;
        const res = rule.action(currentUri);
        if (res.status === 403) {
          return { input: rawUri, status: 403, target: '403 Forbidden', rule: rule.name, loopCount: loops };
        }
        if (res.passthrough) {
          return { input: rawUri, status: 200, target: res.target, rule: rule.name, loopCount: loops };
        }
        if (res.rewritten) {
          // In Apache, after rewrite, a sub-request loop occurs with the new internal URI
          currentUri = res.target;
          break; // break inner rule loop, restart with new currentUri
        }
      }
    }

    if (!matched) {
      return { input: rawUri, status: 404, target: 'Not Found', rule: 'Default', loopCount: loops };
    }
  }

  return { input: rawUri, status: 500, target: 'Redirect Loop Exceeded', rule: 'LOOP_ERROR', loopCount: loops };
}

const testRoutes = [
  '/',
  '/about',
  '/services',
  '/services/example-slug',
  '/insights/example-article',
  '/api/v1/health',
  '/api/v1/admin/auth/login',
  '/api/index.php',
  '/admin',
  '/admin/',
  '/admin/login',
  '/admin/dashboard',
  '/admin/index.html',
  '/index.php',
  '/assets/app-example.js',
  '/assets/example.css',
  '/favicon.ico',
  '/robots.txt',
  '/sitemap.xml',
  '/.env',
  '/storage/private/resume.pdf'
];

console.log('=== Apache Rewrite Simulator Execution ===\n');
let allPassed = true;

testRoutes.forEach(r => {
  const res = simulateRequest(r);
  const isLoop = res.loopCount >= 10;
  if (isLoop) allPassed = false;
  console.log(`${r.padEnd(30)} -> [HTTP ${res.status}] ${res.target.padEnd(20)} (Loops: ${res.loopCount}, Rule: ${res.rule})`);
});

console.log('\nAll routes handled safely without infinite loops:', allPassed);
