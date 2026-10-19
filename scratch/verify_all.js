// scratch/verify_all.js
const fs = require('fs');

console.log('=== VERIFYING MASTER FUNCTIONAL REQUIREMENTS ===');
const mfrContent = fs.readFileSync('docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md', 'utf8');
const mfrIds = [];
mfrContent.split('\n').forEach(l => {
  const m = l.match(/\|\s*\*\*(FR-[A-Z0-9]+-[0-9]+)\*\*\s*\|/);
  if (m) mfrIds.push(m[1]);
});
console.log('Total MFR IDs:', mfrIds.length);
console.log('Unique MFR IDs:', new Set(mfrIds).size);

console.log('\n=== VERIFYING REQUIREMENTS TRACEABILITY MATRIX ===');
const rtmContent = fs.readFileSync('docs/phase-01/15_REQUIREMENTS_TRACEABILITY_MATRIX.md', 'utf8');
const rtmReqIds = [];
const rtmTestIds = [];
rtmContent.split('\n').forEach(l => {
  const mReq = l.match(/\|\s*\*\*(FR-[A-Z0-9]+-[0-9]+)\*\*\s*\|/);
  if (mReq) rtmReqIds.push(mReq[1]);
  const mTest = l.match(/`(TEST-[A-Z0-9]+-[0-9]+)`/);
  if (mTest) rtmTestIds.push(mTest[1]);
});
console.log('Total RTM Req IDs:', rtmReqIds.length);
console.log('Unique RTM Req IDs:', new Set(rtmReqIds).size);
console.log('Total RTM Test IDs:', rtmTestIds.length);
console.log('Unique RTM Test IDs:', new Set(rtmTestIds).size);

console.log('\n=== VERIFYING ACCEPTANCE TEST CATALOG ===');
const catalogContent = fs.readFileSync('docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md', 'utf8');
const catalogTestIds = [];
const catalogReqIds = [];
catalogContent.split('\n').forEach(l => {
  const mTest = l.match(/^- \*\*Canonical Test ID:\*\* `(TEST-[A-Z0-9]+-[0-9]+)`/);
  if (mTest) catalogTestIds.push(mTest[1]);
  const mReq = l.match(/^- \*\*Related Requirement ID:\*\* \[`(FR-[A-Z0-9]+-[0-9]+)`\]/);
  if (mReq) catalogReqIds.push(mReq[1]);
});
console.log('Total Detailed Tests in Catalog:', catalogTestIds.length);
console.log('Unique Detailed Tests:', new Set(catalogTestIds).size);
console.log('Total Mapped Requirements in Catalog:', catalogReqIds.length);

console.log('\n=== CROSS-DOCUMENT RECONCILIATION AUDIT ===');
const mfrSet = new Set(mfrIds);
const rtmSet = new Set(rtmReqIds);
const catalogTestSet = new Set(catalogTestIds);
const rtmTestSet = new Set(rtmTestIds);

const missingInRtm = [...mfrSet].filter(id => !rtmSet.has(id));
const orphanInRtm = [...rtmSet].filter(id => !mfrSet.has(id));
const missingTestsInCatalog = [...rtmTestSet].filter(id => !catalogTestSet.has(id));
const orphanTestsInCatalog = [...catalogTestSet].filter(id => !rtmTestSet.has(id));

console.log('Missing MFR in RTM:', missingInRtm.length === 0 ? '0 (PERFECT)' : missingInRtm);
console.log('Orphan in RTM:', orphanInRtm.length === 0 ? '0 (PERFECT)' : orphanInRtm);
console.log('Missing Tests in Catalog:', missingTestsInCatalog.length === 0 ? '0 (PERFECT)' : missingTestsInCatalog);
console.log('Orphan Tests in Catalog:', orphanTestsInCatalog.length === 0 ? '0 (PERFECT)' : orphanTestsInCatalog);

console.log('\n=== UNVERIFIED PATTERNS SCAN ===');
const dir = 'docs/phase-01';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.md'));
let violations = 0;
files.forEach(f => {
  const content = fs.readFileSync(`${dir}/${f}`, 'utf8');
  // check for unverified hardcoded entity as fact (excluding decision register and correction audit report which discuss the original issue)
  if (f !== '16_BUSINESS_INPUTS_AND_OPEN_DECISIONS.md' && f !== '19_PHASE_1_CORRECTION_AND_ACCEPTANCE_REPORT.md') {
    if (content.includes('Voak Health Care Pty Ltd')) {
      console.log(`[VIOLATION] Hardcoded Pty Ltd in ${f}`);
      violations++;
    }
    if (content.includes('^04\\d{8}$')) {
      console.log(`[VIOLATION] Australia-only phone regex in ${f}`);
      violations++;
    }
    if (content.match(/progressive hydration/i)) {
      console.log(`[VIOLATION] Progressive hydration in ${f}`);
      violations++;
    }
  }
});

console.log('Total Pattern Violations:', violations === 0 ? '0 (CLEAN)' : violations);
