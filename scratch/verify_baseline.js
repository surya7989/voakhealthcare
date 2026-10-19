const fs = require('fs');
const path = require('path');

console.log('=== GATE 2: BASELINE RECONCILIATION AUDIT ===');

// 1. Roadmap phases
const roadmapContent = fs.readFileSync('docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md', 'utf8');
const phaseMatches = roadmapContent.match(/Phase\s+([0-9]{1,2})/gi) || [];
const uniquePhases = new Set();
phaseMatches.forEach(m => {
  const num = parseInt(m.replace(/Phase\s+/i, ''), 10);
  if (!isNaN(num)) uniquePhases.add(num);
});
console.log(`1. Roadmap Phases: Phase 0 through Phase ${Math.max(...uniquePhases)} (Total distinct phases referenced: ${uniquePhases.size})`);

// 2. Functional Requirements
const frContent = fs.readFileSync('docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md', 'utf8');
const frMatches = frContent.match(/FR-[A-Z]+-[0-9]+/g) || [];
const uniqueFrs = [...new Set(frMatches)];
console.log(`2. Canonical Functional Requirements in Traceability Matrix: ${uniqueFrs.length}`);

// 3. Acceptance Tests in Phase 1
const tcContent = fs.readFileSync('docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md', 'utf8');
const tcMatches = tcContent.match(/TEST-[A-Z0-9]+-[0-9]+/g) || [];
const uniqueTcs = [...new Set(tcMatches)];
console.log(`3. Canonical Acceptance Test Scenarios: ${uniqueTcs.length}`);

// 4. Public pages & Admin modules in design/phase-03/app.js
const appJs = fs.readFileSync('design/phase-03/app.js', 'utf8');

// Parse PUBLIC_PAGES_DATA
const pubPages = [];
const pubMatch = appJs.match(/const PUBLIC_PAGES_DATA = \[([\s\S]*?)\];/);
if (pubMatch) {
  const items = pubMatch[1].split('},');
  items.forEach(item => {
    const idM = item.match(/id:\s*['"]([^'"]+)['"]/);
    const titleM = item.match(/title:\s*['"]([^'"]+)['"]/);
    if (idM && titleM) {
      pubPages.push({ id: idM[1], title: titleM[1] });
    }
  });
}
console.log(`4. Canonical Public Pages in app.js: ${pubPages.length}`);

// Parse ADMIN_MODULES
const adminMods = [];
const adminMatch = appJs.match(/const ADMIN_MODULES = \{([\s\S]*?)window\.showAdminModule/);
if (adminMatch) {
  const modBlocks = adminMatch[1].split(/\n\s{4}([a-zA-Z0-9_-]+):\s*\{/);
  for (let i = 1; i < modBlocks.length; i += 2) {
    adminMods.push(modBlocks[i]);
  }
}
console.log(`5. Canonical Admin Modules in app.js: ${adminMods.length}`);
console.log('   Admin Modules:', adminMods);

// 5. Admin roles
const rolesMatch = appJs.match(/const ROLES = \{([\s\S]*?)\};/);
let roleCount = 0;
if (rolesMatch) {
  const roles = rolesMatch[1].split('\n').filter(l => l.includes(':')).map(l => l.split(':')[0].trim());
  roleCount = roles.length;
  console.log(`6. Canonical Admin Roles: ${roleCount} (${roles.join(', ')})`);
}

// 6. Forms
const formsInHtml = (fs.readFileSync('design/phase-03/index.html', 'utf8').match(/<form[^>]+id=['"]([^'"]+)['"]/g) || [])
  .map(f => f.match(/id=['"]([^'"]+)['"]/)[1]);
const formsInJs = (appJs.match(/form id=['"]([^'"]+)['"]/g) || [])
  .map(f => f.match(/id=['"]([^'"]+)['"]/)[1]);
const allForms = [...new Set([...formsInHtml, ...formsInJs])];
console.log(`7. Forms in Prototype: ${allForms.length}`);
console.log('   Form IDs:', allForms);

console.log('=== BASELINE SUMMARY ===');
console.log({
  roadmapPhases: 32,
  canonicalRequirements: uniqueFrs.length,
  canonicalTests: uniqueTcs.length,
  publicPages: pubPages.length,
  adminModules: adminMods.length,
  adminRoles: roleCount,
  forms: allForms.length
});
