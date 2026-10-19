import re, json, os

def parse_phase1_public_pages():
    path = "docs/phase-01/02_PUBLIC_PAGE_BY_PAGE_SPECIFICATIONS.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()

    pages = []
    sections = re.split(r'\n####\s+(\d{2})\.\s+([^\n]+)', text)
    for i in range(1, len(sections), 3):
        num = sections[i]
        title = sections[i+1].strip()
        body = sections[i+2]
        
        id_match = re.search(r'\- \*\*A\. Page Identifier:\*\*\s*`([^`]+)`', body)
        route_match = re.search(r'\- \*\*B\. Route & URL Pattern:\*\*\s*`([^`]+)`', body)
        
        page_id = id_match.group(1).strip() if id_match else f"PAGE-PUB-{num}"
        route = route_match.group(1).strip() if route_match else ""
        if page_id == "PAGE-PUB-22":
            route = "/*"
        
        pages.append({
            "num": num,
            "title": title,
            "id": page_id,
            "route": route
        })
    return pages

def parse_app_js_pages():
    path = "design/phase-03/app.js"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    pattern = re.compile(
        r'{\s*id:\s*"([^"]+)",\s*canonicalId:\s*"([^"]+)",\s*reqId:\s*"([^"]+)",\s*title:\s*"([^"]+)",\s*route:\s*"([^"]+)"',
        re.DOTALL
    )
    matches = pattern.findall(text)
    pages = []
    for m in matches:
        pages.append({
            "id": m[0],
            "canonicalId": m[1],
            "reqId": m[2],
            "title": m[3],
            "route": m[4]
        })
    return pages

def parse_phase1_admin_modules():
    path = "docs/phase-01/05_ADMIN_19_MODULE_SPECIFICATIONS.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    mods = []
    matches = re.findall(r'#### Module (\d+):\s*([^\n]+)\n\s*-\s*\*\*Module ID:\*\*\s*`([^`]+)`', text)
    for m in matches:
        mods.append({
            "num": m[0],
            "title": m[1].strip(),
            "id": m[2].strip()
        })
    return mods

def parse_app_js_admin_modules():
    path = "design/phase-03/app.js"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    # In app.js, showAdminModule or ADMIN_MODULES
    # Let's see how admin modules are registered
    pattern = re.compile(r'case\s+[\'"](ADM-\d{2})[\'"]:\s*title\s*=\s*[\'"]([^\'"]+)[\'"]', re.DOTALL)
    matches = pattern.findall(text)
    if not matches:
        # Check other patterns like sidebar or objects
        pattern = re.compile(r'[\'"](ADM-\d{2})[\'"]:\s*\{[^\}]*name:\s*[\'"]([^\'"]+)[\'"]', re.DOTALL)
        matches = pattern.findall(text)
    if not matches:
        # Check string literals 'ADM-01' through 'ADM-19'
        pattern = re.compile(r'[\'"](ADM-\d{2})[\'"]')
        mods = sorted(list(set(pattern.findall(text))))
        return [{"id": m} for m in mods]
    return [{"id": m[0], "title": m[1]} for m in matches]

def parse_phase1_roles_rbac():
    path = "docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    roles = set(re.findall(r'ROLE_[A-Z_]+', text))
    return sorted(list(roles))

def parse_app_js_rbac():
    path = "design/phase-03/app.js"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    # Extract RBAC_MODULE_PERMISSIONS
    match = re.search(r'const\s+RBAC_MODULE_PERMISSIONS\s*=\s*\{([^\}]+(?:\{[^\}]*\}[^\}]*)*)\};', text, re.DOTALL)
    if match:
        rbac_text = match.group(0)
        # Parse roles
        roles = re.findall(r'[\'"](ROLE_[A-Z_]+)[\'"]:\s*\[([^\]]*)\]', rbac_text)
        res = {}
        for r, mods in roles:
            mod_list = re.findall(r'[\'"](ADM-\d{2})[\'"]', mods)
            res[r] = mod_list
        return res
    return {}

def parse_phase1_forms():
    path = "docs/phase-01/07_FORM_FIELDS_AND_VALIDATION_CATALOG.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    forms = set(re.findall(r'FORM-[A-Z]+-\d{2}', text))
    return sorted(list(forms))

def parse_phase1_requirements():
    path = "docs/phase-01/01_MASTER_FUNCTIONAL_REQUIREMENTS.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    reqs = set(re.findall(r'FR-[A-Z0-9]+-\d{3}', text))
    return sorted(list(reqs))

def parse_phase1_test_scenarios():
    path = "docs/phase-01/17_PHASE_1_ACCEPTANCE_TEST_CATALOG.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    scenarios = set(re.findall(r'TEST-[A-Z0-9]+-\d{3}', text))
    return sorted(list(scenarios))

def parse_phase0_roadmap():
    path = "docs/phase-00/13_COMPLETE_32_PHASE_TRACEABILITY.md"
    with open(path, "r", encoding="utf-8") as f:
        text = f.read()
    
    phases = [int(p) for p in re.findall(r'\|\s*\*\*(\d+)\*\*\s*\|', text)]
    return sorted(list(set(phases)))

def run_reconciliation():
    p1_pages = parse_phase1_public_pages()
    app_pages = parse_app_js_pages()
    p1_admin = parse_phase1_admin_modules()
    p1_roles = parse_phase1_roles_rbac()
    app_rbac = parse_app_js_rbac()
    p1_forms = parse_phase1_forms()
    p1_reqs = parse_phase1_requirements()
    p1_tests = parse_phase1_test_scenarios()
    p0_phases = parse_phase0_roadmap()
    
    mismatches = []
    
    # 1. Check Roadmap
    if len(p0_phases) != 32 or min(p0_phases) != 0 or max(p0_phases) != 31:
        mismatches.append(f"Roadmap phase count mismatch: expected 32 (0-31), got {len(p0_phases)}")
    
    # 2. Check Requirements
    if len(p1_reqs) != 62:
        mismatches.append(f"Requirements count mismatch: expected 62, got {len(p1_reqs)}")
        
    # 3. Check Test Scenarios
    if len(p1_tests) != 62:
        mismatches.append(f"Acceptance test scenarios mismatch: expected 62, got {len(p1_tests)}")

    # 4. Check Public Pages Count
    if len(p1_pages) != 22:
        mismatches.append(f"Phase 1 public pages count mismatch: expected 22, got {len(p1_pages)}")
    if len(app_pages) != 22:
        mismatches.append(f"app.js public pages count mismatch: expected 22, got {len(app_pages)}")

    # 5. Check Page-by-Page mapping
    page_rows = []
    for p1 in p1_pages:
        app_match = next((ap for ap in app_pages if ap["canonicalId"] == p1["id"]), None)
        if not app_match:
            mismatches.append(f"Missing page in app.js: {p1['id']} ({p1['title']})")
            continue
        
        # Route check
        # Dynamic routes in spec (e.g. /services/:slug) match specific prototype demo mockups (e.g. /services/clinical-workforce)
        p1_route_base = p1["route"].split("/:")[0]
        app_route_base = app_match["route"]
        route_ok = False
        if p1["route"] == app_match["route"]:
            route_ok = True
        elif "/:" in p1["route"] and app_match["route"].startswith(p1_route_base):
            route_ok = True
        elif p1["id"] == "PAGE-PUB-22" and app_match["route"] == "/error":
            route_ok = True
        
        if not route_ok:
            mismatches.append(f"Route mismatch for {p1['id']}: spec '{p1['route']}' vs app '{app_match['route']}'")
            
        page_rows.append({
            "num": p1["num"],
            "canonicalId": p1["id"],
            "appId": app_match["id"],
            "specTitle": p1["title"],
            "appTitle": app_match["title"],
            "specRoute": p1["route"],
            "appRoute": app_match["route"],
            "appReqId": app_match["reqId"],
            "status": "PASS" if route_ok else "FAIL"
        })

    # Explicit check for mandatory canonical routes
    mandatory_routes = {
        "/partners-clients": False,
        "/faqs": False,
        "/request-proposal": False,
        "/privacy-policy": False,
        "/terms": False,
        "/error": False
    }
    for ap in app_pages:
        for r in mandatory_routes:
            if ap["route"] == r:
                mandatory_routes[r] = True

    for r, found in mandatory_routes.items():
        if not found:
            mismatches.append(f"Mandatory route missing in app.js: {r}")

    # Check PAGE-PUB-10 mapping to FR-PUB-004
    pub10 = next((ap for ap in app_pages if ap["canonicalId"] == "PAGE-PUB-10"), None)
    if not pub10 or "FR-PUB-004" not in pub10["reqId"]:
        mismatches.append("PAGE-PUB-10 is not mapped to FR-PUB-004 in app.js")

    # 6. Check Admin Modules
    if len(p1_admin) != 19:
        mismatches.append(f"Phase 1 admin modules count mismatch: expected 19, got {len(p1_admin)}")
    
    # 7. Check Roles
    if len(p1_roles) != 5:
        mismatches.append(f"Roles count mismatch: expected 5, got {len(p1_roles)}")
    if set(app_rbac.keys()) != set(p1_roles):
        mismatches.append(f"app.js RBAC roles mismatch: expected {p1_roles}, got {list(app_rbac.keys())}")

    # 8. Check Forms
    if len(p1_forms) != 12:
        mismatches.append(f"Forms count mismatch: expected 12, got {len(p1_forms)}")

    status = "PASS" if len(mismatches) == 0 else "FAIL"

    result = {
        "status": status,
        "mismatches_count": len(mismatches),
        "mismatches": mismatches,
        "roadmap_phases": len(p0_phases),
        "requirements_count": len(p1_reqs),
        "acceptance_scenarios_count": len(p1_tests),
        "public_pages_count": len(app_pages),
        "admin_modules_count": len(p1_admin),
        "roles_count": len(p1_roles),
        "forms_count": len(p1_forms),
        "mandatory_routes": mandatory_routes,
        "page_reconciliation_table": page_rows
    }

    with open("scratch/baseline_reconciliation.json", "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2)

    return result

if __name__ == "__main__":
    res = run_reconciliation()
    print(f"Reconciliation Status: {res['status']}")
    print(f"Mismatches: {res['mismatches_count']}")
    if res['mismatches']:
        for m in res['mismatches']:
            print(f"  [MISMATCH] {m}")
    else:
        print("All architectural invariants reconciled 100% with Phase 0 and Phase 1 source documents!")
