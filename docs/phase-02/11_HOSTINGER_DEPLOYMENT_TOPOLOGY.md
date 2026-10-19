# Hostinger Shared-Hosting Deployment Topology & Directory Layout

**Document Reference:** `docs/phase-02/11_HOSTINGER_DEPLOYMENT_TOPOLOGY.md`  
**Phase:** Phase 2 of 31 — Complete System Architecture  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Executing Role:** Hostinger Deployment Specialist & Site Reliability Engineer  
**Baseline Authority:** Accepted Phase 0 Audit (`docs/phase-00/06_HOSTINGER_COMPATIBILITY_AUDIT.md`) and Deployment Strategy (`docs/phase-00/12_DEPLOYMENT_STRATEGY.md`)  
**Status:** **AUTHORITATIVE ARCHITECTURAL SPECIFICATION** (Topology Design Only — Live Deployment Deferred to Phase 31)

---

## 1. Hosting Architecture & Multi-Site Coexistence Model

The Voak Health Care platform deploys to Hostinger shared hosting using the **PHP/HTML deployment model**. The hosting account hosts multiple domain properties (`atspecialists`, `healthonus`, etc.). 

To guarantee that the Voak Health Care deployment can **never corrupt, overwrite, or interfere with neighboring sites**, the platform is architected within an isolated addon domain root:

```
                            HOSTINGER SHARED HOSTING (Linux / CloudLinux)
                     Account Home: /home/u123456789/ (Illustrative Account Identifier)
                                                   │
         ┌─────────────────────────────────────────┼─────────────────────────────────────────┐
         ▼                                         ▼                                         ▼
/domains/atspecialists.com/               /domains/healthonus.com/              /domains/voakhealthcare.com/
(Existing Production Site)                (Existing Production Site)            (ISOLATED VOAK DOMAIN ROOT)
(100% UNTOUCHED)                          (100% UNTOUCHED)                                   │
                                                                       ┌─────────────────────┴─────────────────────┐
                                                                       ▼                                           ▼
                                                            PUBLIC WEB ROOT (public_html/)               PRIVATE APPLICATION ROOT
                                                            - World-readable web root                    - Strictly outside public_html
                                                            - Static React compiled assets               - PHP source modules (src/)
                                                            - Public Front Controller (index.php)        - Composer vendor/ dependencies
                                                            - API Front Controller (api/index.php)       - Quarantined storage (storage/)
                                                            - Admin SPA shell (admin/index.html)         - Environment secrets (.env)
                                                            - Public media library (assets/uploads/)     - CLI runners & migrations
```

---

## 2. Directory Layout Comparisons across Environments

### 2.1 A. Local Development Environment Layout
```
voak-health-care/
├── .env.example                       # Committed environment template (zero secrets)
├── .env                               # Local secrets (chmod 0600, gitignored)
├── composer.json / composer.lock      # PHP dependencies
├── package.json                       # Frontend monorepo root
├── frontend/
│   ├── public-app/                    # Public React application (Vite)
│   └── admin-app/                     # Admin React application (Vite)
├── src/                               # PHP backend source code (PSR-4)
│   ├── Core/                          # Kernel, Container, Router, Exceptions
│   ├── Middleware/                    # CORS, CSRF, Session, RBAC
│   └── Modules/                       # 20 Domain business modules
├── config/                            # Application configuration files
├── storage/
│   ├── logs/                          # Daily JSON application logs
│   └── private/quarantine/            # Uploaded test resumes & tender files
├── cli/                               # CLI Migration & queue worker runners
├── tests/                             # PHPUnit & E2E Playwright test suites
└── public_html/                       # Dev public web root
    ├── index.php                      # Dev pre-renderer
    └── api/index.php                  # Dev API gateway
```

---

### 2.2 B. Production Release Artifact Layout
When compiled by CI/CD (GitHub Actions) for deployment:
```
release-artifact-v1.0.0.tar.gz
├── public_html/                       # Compiled assets to deploy into domain public_html/
│   ├── .htaccess                      # Production Apache rewrite rules (chmod 0644)
│   ├── index.php                      # Production PHP pre-renderer
│   ├── admin/
│   │   └── index.html                 # Compiled Admin SPA shell
│   ├── api/
│   │   └── index.php                  # API gateway
│   └── assets/
│       ├── app-*.js                   # Compiled public React bundle
│       ├── admin-*.js                 # Compiled admin React bundle
│       ├── style-*.css                # Compiled Tailwind CSS stylesheet
│       └── uploads/                   # Media directory structure
└── private_app/                       # Backend code to deploy outside public_html/
    ├── src/                           # PHP 8.x backend modules
    ├── config/                        # Static application config
    ├── vendor/                        # Production Composer vendor (no dev deps)
    ├── cli/                           # CLI migration & queue runners
    └── storage/                       # Initialized storage directories (chmod 0700)
```

---

### 2.3 C. Hostinger Live Deployed Filesystem Structure
On the live Hostinger shared hosting account under the isolated domain root:
```
/home/u123456789/domains/voakhealthcare.com/
├── .env                               # Production secrets file (chmod 0600)
├── config/                            # App configs (chmod 0755)
├── src/                               # PHP source modules (chmod 0755)
├── vendor/                            # Composer vendor directory (chmod 0755)
├── cli/                               # Migration & outbox runners (chmod 0750)
├── storage/                           # Private storage (chmod 0700)
│   ├── logs/                          # Application logs (chmod 0700)
│   └── private/
│       └── quarantine/                # Quarantined resumes & RFPs (chmod 0700)
│
└── public_html/                       # Apache document root (chmod 0755)
    ├── .htaccess                      # Security headers & loop-safe rewrites (chmod 0644)
    ├── index.php                      # Public pre-renderer (chmod 0644)
    ├── admin/
    │   └── index.html                 # Admin SPA shell (chmod 0644)
    ├── api/
    │   └── index.php                  # REST API gateway (chmod 0644)
    └── assets/
        ├── app-a1b2c3d4.js            # Immutable hashed public bundle
        ├── admin-e5f6g7h8.js          # Immutable hashed admin bundle
        ├── style-9i0j1k2l.css         # Immutable hashed stylesheet
        └── uploads/                   # Public WebP media uploads (chmod 0755)
```

---

## 3. Hostinger Apache Routing & Rewrite Rules

The root `.htaccess` in `public_html/` coordinates all traffic safely without leaking internal paths or creating recursive rewrite loops:

```apache
# ----------------------------------------------------------------------
# 1. Hostinger Environment & Rewrite Engine Initialization
# ----------------------------------------------------------------------
RewriteEngine On
RewriteBase /

# ----------------------------------------------------------------------
# 2. Canonical HTTPS Enforcement (Cloudflare & Hostinger Reverse Proxy Safe)
# ----------------------------------------------------------------------
RewriteCond %{HTTPS} off
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ----------------------------------------------------------------------
# 3. Deny Direct Web Access to Sensitive Files, Dirs, & Private Storage
# ----------------------------------------------------------------------
RewriteRule ^(\.env|\.git|\.agents|config/|storage/|vendor/|cli/|composer\.(json|lock)|package(-lock)?\.json) - [F,L]
RewriteRule \.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist|md|json)$ - [F,L]

# ----------------------------------------------------------------------
# 4. Security Header Injection
# ----------------------------------------------------------------------
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "0"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

# ----------------------------------------------------------------------
# 5. Stop Processing Immediately for Real Physical Files & Directories
# ----------------------------------------------------------------------
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# ----------------------------------------------------------------------
# 6. Prevent Self-Rewriting & Infinite Loops for Target Entry Points
# ----------------------------------------------------------------------
RewriteRule ^(index\.php|api/index\.php|admin/index\.html)$ - [L]

# ----------------------------------------------------------------------
# 7. Route API Requests to PHP API Front Controller (/api/index.php)
# ----------------------------------------------------------------------
RewriteRule ^api(/.*)?$ api/index.php [QSA,L]

# ----------------------------------------------------------------------
# 8. Route Admin SPA Client-Side Routes to Admin SPA (/admin/index.html)
# ----------------------------------------------------------------------
RewriteRule ^admin(/.*)?$ admin/index.html [L]

# ----------------------------------------------------------------------
# 9. Route All Public Frontend Page Requests to Public PHP Front Controller
# ----------------------------------------------------------------------
RewriteRule ^ index.php [QSA,L]
```

---

## 4. Deployment Feasibility & Verification Decision Matrix

To ensure engineering honesty and avoid claiming unverified operational guarantees on shared hosting, all deployment mechanisms are evaluated against empirical capabilities:

| Deployment Mechanism | Target Operational Property | Architectural Feasibility Status | Hostinger Operational Constraint / Fallback Strategy |
| :--- | :--- | :---: | :--- |
| **Atomic Symlink Switching** | Zero-downtime release switching via `ln -sfn releases/vX current` | **PENDING HOSTINGER VERIFICATION** | Requires Hostinger SSH access and Apache `Options +FollowSymLinks` approval. If disallowed, fallback to Controlled In-Place Sync. |
| **Controlled In-Place Sync** | Rapid file synchronization via SFTP/rsync | **DESIGN VERIFIED** | **Verified Fallback:** Maintenance file `public_html/.maintenance` is activated during copy (< 5 seconds), returning HTTP 503 to visitors. |
| **Database Migration Safety** | Single-table atomic forward-only DDL | **DESIGN VERIFIED** | Executed strictly via CLI (`php cli/migrate.php up`) prior to cutover. Backups verified before migration runs. |
| **OPcache Invalidation** | Immediate execution of fresh PHP bytecode | **DESIGN VERIFIED** | Invoked via authenticated local CLI command or protected deployment endpoint calling `opcache_reset()`. |
| **Neighbor Site Isolation** | 100% isolation from `atspecialists` and `healthonus` | **DESIGN VERIFIED** | Guaranteed by physical isolation in `/domains/voakhealthcare.com/`. No shared parent configurations modified. |
| **Claim: "Guaranteed Zero Downtime"**| Uninterrupted visitor traffic during deployment | **PENDING RUNTIME VERIFICATION** | Realistic shared-hosting expectation: near-zero downtime (~2–5s maintenance window during file cutover). |

---

## 5. Existing Hosted Site Isolation Guarantee

Hostinger shared hosting accounts host multiple domains under separate subdirectories or addon domains:
- **Root Account Boundary:** The Voak deployment operates strictly within `/home/u123456789/domains/voakhealthcare.com/`.
- **No Shared Filesystem Access:** The deployment configuration, `.env` file, database credentials, and Apache rewrites are strictly scoped to the Voak domain root.
- **Zero Impact on Neighbor Sites:** Neighboring addon domains (`atspecialists`, `healthonus`, etc.) maintain independent document roots and databases. The deployment touches **zero files outside the Voak platform scope**.
