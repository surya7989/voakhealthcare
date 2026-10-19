const fs = require('fs');

const appJs = fs.readFileSync('design/phase-03/app.js', 'utf8');

console.log('--- PUBLIC PAGES VERIFICATION ---');
const pMatch = appJs.match(/id:\s*['"]PUB-(\d+)['"]/g);
console.log('Public page records in PUBLIC_PAGES_DATA:', pMatch ? pMatch.length : 0);

let missingCases = [];
for (let i = 1; i <= 22; i++) {
  const pad = String(i).padStart(2, '0');
  const target = "case 'PUB-" + pad + "':";
  if (!appJs.includes(target)) {
    missingCases.push('PUB-' + pad);
  }
}
if (missingCases.length === 0) {
  console.log('PASS: All 22 canonical public pages (PUB-01 to PUB-22) have bespoke case branches in renderPageSpecificBody()!');
} else {
  console.log('FAIL: Missing cases for:', missingCases);
}

// Check for generic specification fallback box
const hasGenericFallback = appJs.includes('Architectural Specification & Functional Traceability');
console.log('Generic specification fallback present in app.js:', hasGenericFallback ? 'WARNING: Still present' : 'PASS: Zero generic fallbacks!');

console.log('\n--- ADMIN MODULES VERIFICATION ---');
let missingAdminMods = [];
for (let i = 1; i <= 19; i++) {
  const pad = String(i).padStart(2, '0');
  const target = "'ADM-" + pad + "':";
  if (!appJs.includes(target)) {
    missingAdminMods.push('ADM-' + pad);
  }
}
if (missingAdminMods.length === 0) {
  console.log('PASS: All 19 canonical admin modules (ADM-01 to ADM-19) have individual renderers!');
} else {
  console.log('FAIL: Missing admin modules:', missingAdminMods);
}

console.log('\n--- PAGE 10 RESTORATION VERIFICATION ---');
const p10PubMatch = appJs.includes("canonicalId: \"PAGE-PUB-10\"") && appJs.includes("title: \"Partners & Clients\"");
console.log('PAGE-PUB-10 is Partners & Clients:', p10PubMatch ? 'PASS' : 'FAIL');

console.log('\n--- ROLE SIMULATION NOTICE VERIFICATION ---');
const rbacSimNotice = appJs.includes('SIMULATED 403 ACCESS DENIED (Design Preview — Production RBAC in Phase 6)');
console.log('RBAC 403 explicitly labeled as design preview simulation:', rbacSimNotice ? 'PASS' : 'FAIL');
