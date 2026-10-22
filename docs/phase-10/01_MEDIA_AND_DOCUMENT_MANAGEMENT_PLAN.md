# Phase 10: Media Library & Secure Document Quarantine — Architecture & Implementation Plan

**Document Reference:** `docs/phase-10/01_MEDIA_AND_DOCUMENT_MANAGEMENT_PLAN.md`  
**Phase:** Phase 10 of 32 — Media & Document Management  
**Project:** Voak Health Care — Enterprise B2B Healthcare Operations Platform  
**Status:** **AUTHORITATIVE IMPLEMENTATION PLAN**  
**Traceability References:**
- `ADM-009` / `ADM-008`: Media Library & Asset Manager
- `FR-ADM-009`: Media Library & Asset Optimization (WebP conversion, alt-text enforcement, reference deletion guard)
- `FR-SEC-003`: Document Upload Quarantine (resumes & tenders stored outside web root with UUID naming)
- `FR-SEC-004`: Authenticated Document Streaming (role-authorized streaming proxy with audit logging)
- `SYS-004`: File Upload Quarantine & Protection (.htaccess execution blocking)
- `ADR-007`: Segregated Storage: Public WebP Assets vs. Quarantined Document Streaming
- `TEST-ADM-009`: Media Library WebP Optimization & Reference Deletion Guard
- `TEST-SEC-003`: Document Upload Quarantine & MIME Magic Byte Inspection
- `TEST-SEC-004`: Authenticated Document Streaming & Direct URL Access Denial

---

## 1. Executive Summary & Boundaries

Phase 10 implements the dual-enclave storage architecture for Voak Health Care:
1. **Public Marketing Media Assets Enclave:**
   - Public WebP image library with automated server-side GD/Imagick WebP conversion (max 1920px width, 82% quality) and thumbnail generation (400px width).
   - Mandatory alt-text enforcement for accessibility and SEO.
   - Server-side MIME magic byte verification (`finfo`) rejecting executable scripts, masqueraded `.php` files, and dangerous XML/SVG vectors.
   - UUIDv4 randomized storage filenames (`stored_name`).
   - Referential integrity deletion guard (`FR-ADM-009`): Before deleting an asset (`DELETE /api/v1/admin/media/:id`), the service verifies no references exist in `pages`, `homepage_configs`, `services`, `industries`, or `articles`. Deletion of referenced media is strictly blocked with `HTTP 409 Conflict`.
2. **Private Sensitive Documents Quarantine Enclave:**
   - Isolated physical partition located strictly outside the public web root (`storage/private/quarantine/`).
   - Hardened web access denial via `.htaccess` (`Require all denied`, `php_flag engine off`) and empty `index.html`.
   - Inbound intake validation for resumes (max 10MB) and tender documents (max 15MB) strictly enforcing PDF and DOCX magic bytes.
   - Discarding client filenames on disk, writing randomized UUID partitioned files (`storage/private/quarantine/ab/cd/doc_uuid.bin`).
   - Authenticated streaming proxy (`FR-SEC-004`) requiring role-specific RBAC (`proposal:download_tender`, `candidate:download_cv`), emitting strict anti-sniff download headers and chained SHA-256 cryptographic audit logs.

### Scope Boundaries:
- **Zero Hostinger Changes:** Live staging and production environments remain untouched.
- **Zero React UI Implementation:** Admin UI is deferred to Admin Portal UI Sprint (Phase 26); all REST APIs, CSRF tokens, and security policies are fully provisioned.
- **Zero New Migrations Required:** Reuses canonical `media_assets` (Migration 15), `proposals` (Migration 18), `career_applications` (Migration 21), and `audit_logs` (Migration 28).

---

## 2. Storage Partitioning Architecture (ADR-007)

```
                            VOAK HEALTH CARE STORAGE ENGINE
                                           │
                 ┌─────────────────────────┴─────────────────────────┐
                 ▼                                                   ▼
     PUBLIC MARKETING MEDIA ENCLAVE                      PRIVATE QUARANTINE ENCLAVE
       (Images, Banners, Bento Icons)                (Candidate Resumes, Client RFPs)
                 │                                                   │
                 ▼                                                   ▼
    PATH: public/assets/uploads/YYYY/MM/             PATH: storage/private/quarantine/ab/cd/
    - Web-accessible via web server                  - STRICTLY OUTSIDE web document root
    - Auto-converted to WebP (max 1920px)            - Apache .htaccess 'Require all denied'
    - Responsive thumbnail '_thumb.webp'             - Zero execution permissions (chmod 0600)
    - Mandatory alt text enforced                    - Streamed ONLY via Authenticated PHP
    - MIME magic bytes verified via finfo            - Object-level RBAC & audit logging
```

---

## 3. Database Schema Reutilization

### 3.1 Migration 15: `media_assets`
```sql
CREATE TABLE IF NOT EXISTS media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL UNIQUE,
    file_path VARCHAR(255) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    dimensions VARCHAR(30) NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id),
    INDEX idx_media_mime (mime_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**MIGRATION STATUS: ZERO NEW MIGRATIONS REQUIRED.**

---

## 4. API Endpoints & RBAC Security Matrix

| Method | Path | Permission Required | Allowed Roles | Description | Status Code |
| :--- | :--- | :--- | :--- | :--- | :---: |
| `GET` | `/api/v1/public/media/:id` | Public | All | Retrieve public media metadata | 200, 404 |
| `GET` | `/api/v1/admin/media` | `media.view` | Super Admin, Website Admin, Content Editor | List & paginate media assets | 200, 401, 403 |
| `GET` | `/api/v1/admin/media/:id` | `media.view` | Super Admin, Website Admin, Content Editor | Show single media asset & usage info | 200, 401, 403, 404 |
| `POST` | `/api/v1/admin/media/upload` | `media:upload` | Super Admin, Website Admin, Content Editor | Upload image, auto WebP, alt text | 201, 401, 403, 422 |
| `PATCH`| `/api/v1/admin/media/:id` | `media:update_alt` | Super Admin, Website Admin, Content Editor | Update media alt text & metadata | 200, 401, 403, 404, 422 |
| `DELETE`| `/api/v1/admin/media/:id` | `media:delete` | Super Admin, Website Admin ONLY | Delete media asset (deletion guard) | 200, 401, 403, 404, 409 |
| `GET` | `/api/v1/admin/documents/:id/stream` | `proposal:download_tender` or `candidate:download_cv` | Super Admin, BD/HR Managers | Stream quarantined document | 200, 401, 403, 404 |
| `GET` | `/api/v1/admin/proposals/:id/document` | `proposal:download_tender` | Super Admin, BD Manager | Stream RFP tender attachment | 200, 401, 403, 404 |
| `GET` | `/api/v1/admin/careers/applications/:id/resume` | `candidate:download_cv` | Super Admin, HR Manager | Stream candidate resume attachment | 200, 401, 403, 404 |

---

## 5. Security Threat Mitigation Matrix

| Threat ID | Description | Mitigation Strategy | Acceptance Test |
| :--- | :--- | :--- | :--- |
| **TH-10** | Web Shell File Upload (`.php` disguised as `.jpg` or `.pdf`) | MIME magic byte inspection (`finfo_open`), rejecting mismatched signatures (`HTTP 422`). | `TEST-SEC-003`, `TEST-ADM-009` |
| **TH-11** | Path Traversal (`../../etc/passwd`) | Discard client paths; generate UUIDv4 stored names; canonical directory boundary checks. | `TEST-SEC-003` |
| **TH-12** | Sensitive Document Exposure (Publicly indexing resumes or RFPs) | Quarantine partition strictly outside public web root; `.htaccess` denies all direct HTTP access. | `TEST-SEC-004` |
| **TH-13** | Unauthorized Document Exfiltration | Authenticated streaming proxy enforces object-level RBAC (`proposal:download_tender`, `candidate:download_cv`). | `TEST-SEC-004` |
| **TH-14** | Broken UI Images via Accidental Deletion | Referential integrity deletion guard (`FR-ADM-009`) checks CMS usage; blocks with `HTTP 409 Conflict`. | `TEST-ADM-009` |
| **TH-15** | XML / SVG Script Injection | SVG uploads by non-super-admins rejected; image files checked for executable scripts. | `TEST-SEC-003` |
| **TH-16** | Unaudited Data Access | Streaming downloads emit cryptographic audit records with downloader user ID, IP, and file hash. | `TEST-SEC-004` |

---

## 6. Implementation Steps

1. Create `ConflictException` in `backend/src/Core/Exceptions/ConflictException.php`.
2. Extend `Crypto` with `uuidv4()` in `backend/src/Security/Crypto.php`.
3. Enhance `Request` to carry uploaded files and parsed body; enhance `Response` with `download()` factory.
4. Implement `MediaStorageService` in `backend/src/Services/MediaStorageService.php`.
5. Implement `AdminMediaController` in `backend/src/Modules/Admin/AdminMediaController.php`.
6. Implement `AdminDocumentStreamController` in `backend/src/Modules/Admin/AdminDocumentStreamController.php`.
7. Update `PublicContentController` with `getMedia()` endpoint.
8. Update `RbacManager` with media and document download aliases.
9. Wire routes and container bindings in `backend/src/Core/App.php`.
10. Write comprehensive unit and integration tests.
11. Update MySQL concurrency test `backend/tests/mysql_security_concurrency_test.php`.
12. Execute full local verification suites and CI.
