const fs = require('fs');

const doc2 = fs.readFileSync('docs/phase-02/02_PHP_BACKEND_MODULE_ARCHITECTURE.md', 'utf8');
const doc4 = fs.readFileSync('docs/phase-02/04_API_CONTRACTS_AND_OPENAPI.md', 'utf8');
const openapi = JSON.parse(fs.readFileSync('docs/phase-02/openapi/openapi.json', 'utf8'));

// Extract endpoints from doc 4 table
const extractDoc4 = (text) => {
  const ops = [];
  const lines = text.split('\n');
  lines.forEach(l => {
    const m = l.match(/\|\s*\*\*(GET|POST|PUT|PATCH|DELETE)\*\*\s*\|\s*`([^`]+)`/);
    if (m) ops.push({ method: m[1], path: m[2] });
  });
  return ops;
};

// Extract endpoints from doc 2
const extractDoc2 = (text) => {
  const ops = [];
  const matches = [...text.matchAll(/(GET|POST|PUT|PATCH|DELETE)\s+(`?\/api\/v1\/[a-zA-Z0-9_\-\/{}:]+`?)/g)];
  matches.forEach(m => {
    ops.push({ method: m[1], path: m[2].replace(/`/g, '') });
  });
  return ops;
};

// Extract openapi
const extractOpenAPI = (spec) => {
  const ops = [];
  for (const p of Object.keys(spec.paths || {})) {
    for (const m of Object.keys(spec.paths[p])) {
      if (['get','post','put','patch','delete'].includes(m.toLowerCase())) {
        const fullPath = p.startsWith('/api/v1') ? p : '/api/v1' + p;
        ops.push({ method: m.toUpperCase(), path: fullPath });
      }
    }
  }
  return ops;
};

const d2Ops = extractDoc2(doc2);
const d4Ops = extractDoc4(doc4);
const oaOps = extractOpenAPI(openapi);

console.log('Doc 2 total ops matched:', d2Ops.length);
console.log('Doc 4 total ops matched:', d4Ops.length);
console.log('OpenAPI total ops matched:', oaOps.length);

const d2Set = new Set(d2Ops.map(o => o.method + ' ' + o.path));
const d4Set = new Set(d4Ops.map(o => o.method + ' ' + o.path));
const oaSet = new Set(oaOps.map(o => o.method + ' ' + o.path));

console.log('Unique Doc 2 ops:', d2Set.size);
console.log('Unique Doc 4 ops:', d4Set.size);
console.log('Unique OpenAPI ops:', oaSet.size);

console.log('\n--- In Doc 2 but NOT in Doc 4 (' + [...d2Set].filter(x => !d4Set.has(x)).length + ') ---');
for (const op of d2Set) {
  if (!d4Set.has(op)) console.log('  ' + op);
}

console.log('\n--- In Doc 4 but NOT in Doc 2 (' + [...d4Set].filter(x => !d2Set.has(x)).length + ') ---');
for (const op of d4Set) {
  if (!d2Set.has(op)) console.log('  ' + op);
}

console.log('\n--- In Doc 4 but NOT in OpenAPI (' + [...d4Set].filter(x => !oaSet.has(x)).length + ') ---');
for (const op of d4Set) {
  if (!oaSet.has(op)) console.log('  ' + op);
}
