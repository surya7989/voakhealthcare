import json

with open('tests/results/phase-03-responsive-results.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

all_checks = data['masterHomepage'] + data['publicPages'] + data['adminModules']
print(f"Total test rows: {len(all_checks)}")

count = 0
for idx, r in enumerate(all_checks):
    off = r.get('offscreenElements', [])
    cont = r.get('containerBlowouts', [])
    ctrls = r.get('offscreenControls', [])
    if off or cont or ctrls:
        count += 1
        target = r.get('id') or r.get('title')
        vw = r.get('viewport', {})
        w = vw.get('width')
        print(f"\n[{count}] Row #{idx+1}: {target} @ {w}px ({vw.get('category')}) | status: {r.get('status')}")
        if ctrls:
            print(f"  Controls ({len(ctrls)}): {ctrls}")
        if off:
            print(f"  Offscreen Elements ({len(off)}):")
            for o in off:
                print(f"    - <{o.get('tag')} class='{o.get('class')}'> left={o.get('left')}, right={o.get('right')}")
        if cont:
            print(f"  Container Blowouts ({len(cont)}):")
            for c in cont:
                print(f"    - <{c.get('tag')} class='{c.get('class')}'> scrollW={c.get('scrollW')}, clientW={c.get('clientW')}")

print(f"\nTotal rows with findings: {count}")
