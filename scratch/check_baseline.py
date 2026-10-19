import re, json

with open('design/phase-03/app.js', 'r', encoding='utf-8') as f:
    text = f.read()

# Match entries in PUBLIC_PAGES_DATA
# Example:
# {
#   id: "PUB-01",
#   canonicalId: "PAGE-PUB-01",
#   reqId: "...",
#   title: "...",
#   route: "...",
# }

pattern = re.compile(r'{\s*id:\s*"([^"]+)",\s*canonicalId:\s*"([^"]+)",\s*reqId:\s*"([^"]+)",\s*title:\s*"([^"]+)",\s*route:\s*"([^"]+)"', re.DOTALL)
matches = pattern.findall(text)

print(f"Total matched pages in app.js: {len(matches)}")
for m in matches:
    print(f"  {m[0]} | {m[1]} | {m[2]} | {m[3]} | {m[4]}")
