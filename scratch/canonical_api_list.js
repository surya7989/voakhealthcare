const fs = require('fs');

const publicEndpoints = [
  { method: 'GET', path: '/health', summary: 'Health check probe', auth: 'None', rate: '120/min' },
  { method: 'GET', path: '/public/settings/header', summary: 'Header config and navigation', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/homepage', summary: 'Homepage hero and bento data', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/pages/{slug}', summary: 'Static CMS page detail', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/services', summary: 'List published services', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/services/{slug}', summary: 'Service detail and SLA matrix', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/industries', summary: 'List industry verticals', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/industries/{slug}', summary: 'Industry sector detail and compliance specs', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/case-studies', summary: 'List verified case studies', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/jobs', summary: 'List open career vacancies', auth: 'None', rate: '60/min' },
  { method: 'POST', path: '/public/careers/apply', summary: 'Submit job application with resume', auth: 'None', rate: '5/hr/IP' },
  { method: 'GET', path: '/public/articles', summary: 'List published articles', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/articles/{slug}', summary: 'Article detail with reading time', auth: 'None', rate: '60/min' },
  { method: 'GET', path: '/public/faqs', summary: 'List FAQ accordions by category', auth: 'None', rate: '60/min' },
  { method: 'POST', path: '/public/contact', summary: 'Submit contact inquiry', auth: 'None', rate: '5/hr/IP' },
  { method: 'POST', path: '/public/partnerships/inquire', summary: 'Submit partnership proposal', auth: 'None', rate: '5/hr/IP' },
  { method: 'POST', path: '/public/proposals/submit', summary: 'Submit enterprise RFP wizard with tender file', auth: 'None', rate: '3/hr/IP' }
];

const adminEndpoints = [
  // Module 01: Auth & Session Management
  { method: 'GET', path: '/admin/auth/csrf', summary: 'Retrieve CSRF token for unauthenticated login form', perm: 'Public', audit: 'None' },
  { method: 'POST', path: '/admin/auth/login', summary: 'Authenticate credentials and issue pre-auth token', perm: 'Public', audit: 'AUTH_LOGIN_ATTEMPT' },
  { method: 'POST', path: '/admin/auth/mfa/verify', summary: 'Verify TOTP MFA code and issue session cookie', perm: 'Pre-Auth', audit: 'AUTH_LOGIN_SUCCESS' },
  { method: 'POST', path: '/admin/auth/mfa/recovery', summary: 'Verify emergency MFA recovery code and issue session', perm: 'Pre-Auth', audit: 'AUTH_MFA_RECOVERY_USED' },
  { method: 'POST', path: '/admin/auth/password/forgot', summary: 'Request password reset email', perm: 'Public', audit: 'AUTH_PASSWORD_RESET_REQUESTED' },
  { method: 'POST', path: '/admin/auth/password/reset', summary: 'Confirm password reset with token', perm: 'Public', audit: 'AUTH_PASSWORD_RESET_COMPLETED' },
  { method: 'POST', path: '/admin/auth/logout', summary: 'Invalidate active session and clear cookie', perm: 'Authenticated', audit: 'AUTH_LOGOUT' },
  { method: 'GET', path: '/admin/auth/me', summary: 'Get current user identity, roles and permissions', perm: 'Authenticated', audit: 'None' },

  // Module 02: Dashboard
  { method: 'GET', path: '/admin/dashboard/metrics', summary: 'Operational metrics and queue telemetry', perm: 'telemetry.view', audit: 'None' },

  // Module 03: Administrative Users
  { method: 'GET', path: '/admin/users', summary: 'List administrative staff accounts', perm: 'users.view', audit: 'None' },
  { method: 'POST', path: '/admin/users', summary: 'Create or invite new administrative user', perm: 'users.manage', audit: 'USER_CREATED' },
  { method: 'PUT', path: '/admin/users/{id}', summary: 'Update user account, role or active status', perm: 'users.manage', audit: 'USER_UPDATED' },
  { method: 'DELETE', path: '/admin/users/{id}', summary: 'Suspend or deactivate administrative user account', perm: 'users.manage', audit: 'USER_SUSPENDED' },

  // Module 04: Public Pages
  { method: 'GET', path: '/admin/pages', summary: 'List all static editable CMS pages', perm: 'pages.view', audit: 'None' },
  { method: 'PUT', path: '/admin/pages/{id}', summary: 'Update page copy and publish revision', perm: 'pages.manage', audit: 'PAGE_PUBLISHED' },
  { method: 'POST', path: '/admin/pages/{id}/revert', summary: 'Revert page content to historical revision', perm: 'pages.manage', audit: 'PAGE_REVERTED' },

  // Module 05: Homepage Layout
  { method: 'GET', path: '/admin/homepage', summary: 'Get homepage hero, bento layout and proof config', perm: 'homepage.manage', audit: 'None' },
  { method: 'PUT', path: '/admin/homepage', summary: 'Update homepage hero, bento order and metrics', perm: 'homepage.manage', audit: 'HOMEPAGE_UPDATED' },

  // Module 06: Services Catalog
  { method: 'GET', path: '/admin/services', summary: 'List all service catalog pillars', perm: 'services.view', audit: 'None' },
  { method: 'POST', path: '/admin/services', summary: 'Create new B2B healthcare service pillar', perm: 'services.manage', audit: 'SERVICE_CREATED' },
  { method: 'PUT', path: '/admin/services/{id}', summary: 'Update service details, deliverables, and SLAs', perm: 'services.manage', audit: 'SERVICE_UPDATED' },
  { method: 'DELETE', path: '/admin/services/{id}', summary: 'Archive or unpublish service pillar', perm: 'services.manage', audit: 'SERVICE_ARCHIVED' },

  // Module 07: Industry Verticals
  { method: 'GET', path: '/admin/industries', summary: 'List all healthcare industry verticals', perm: 'industries.view', audit: 'None' },
  { method: 'POST', path: '/admin/industries', summary: 'Create new healthcare sector vertical', perm: 'industries.manage', audit: 'INDUSTRY_CREATED' },
  { method: 'PUT', path: '/admin/industries/{id}', summary: 'Update sector compliance specs and challenges', perm: 'industries.manage', audit: 'INDUSTRY_UPDATED' },
  { method: 'DELETE', path: '/admin/industries/{id}', summary: 'Archive healthcare sector vertical', perm: 'industries.manage', audit: 'INDUSTRY_ARCHIVED' },

  // Module 08: Media Library
  { method: 'GET', path: '/admin/media', summary: 'List media assets with pagination and search', perm: 'media.view', audit: 'None' },
  { method: 'POST', path: '/admin/media', summary: 'Upload public WebP image with mandatory alt text', perm: 'media.manage', audit: 'MEDIA_UPLOADED' },
  { method: 'DELETE', path: '/admin/media/{id}', summary: 'Delete unreferenced media asset', perm: 'media.manage', audit: 'MEDIA_DELETED' },

  // Module 09: Contact Enquiries
  { method: 'GET', path: '/admin/inquiries/contact', summary: 'List and filter inbound contact inquiries', perm: 'inquiries.view', audit: 'None' },
  { method: 'PATCH', path: '/admin/inquiries/contact/{id}/status', summary: 'Transition contact inquiry triage status', perm: 'inquiries.manage', audit: 'INQUIRY_STATUS_UPDATED' },
  { method: 'GET', path: '/admin/inquiries/contact/export', summary: 'Export contact inquiries as CSV', perm: 'inquiries.export', audit: 'INQUIRIES_EXPORTED' },

  // Module 10: Partnerships Desk
  { method: 'GET', path: '/admin/partnerships', summary: 'List strategic alliance proposals and pipeline', perm: 'partnerships.view', audit: 'None' },
  { method: 'PATCH', path: '/admin/partnerships/{id}/status', summary: 'Update partnership alliance stage and assignee', perm: 'partnerships.manage', audit: 'PARTNERSHIP_STATUS_UPDATED' },
  { method: 'GET', path: '/admin/partnerships/export', summary: 'Export partnerships pipeline as CSV', perm: 'partnerships.export', audit: 'PARTNERSHIPS_EXPORTED' },

  // Module 11: Enterprise Proposals (RFPs)
  { method: 'GET', path: '/admin/proposals', summary: 'List enterprise RFP wizard submissions', perm: 'proposals.view', audit: 'None' },
  { method: 'PATCH', path: '/admin/proposals/{id}/status', summary: 'Update proposal evaluation status and value', perm: 'proposals.manage', audit: 'PROPOSAL_STATUS_UPDATED' },
  { method: 'GET', path: '/admin/proposals/{id}/download', summary: 'Stream private tender specification file', perm: 'proposals.download_tender', audit: 'PROPOSAL_TENDER_DOWNLOADED' },
  { method: 'GET', path: '/admin/proposals/export', summary: 'Export proposals register as CSV', perm: 'proposals.export', audit: 'PROPOSALS_EXPORTED' },

  // Module 12: Email Templates & Outbox Telemetry
  { method: 'GET', path: '/admin/email-templates', summary: 'List transactional email templates', perm: 'settings.view', audit: 'None' },
  { method: 'PUT', path: '/admin/email-templates/{id}', summary: 'Update email template subject and HTML body', perm: 'email_templates.manage', audit: 'EMAIL_TEMPLATE_UPDATED' },
  { method: 'GET', path: '/admin/email-queue', summary: 'List transactional email outbox queue status', perm: 'telemetry.view', audit: 'None' },
  { method: 'POST', path: '/admin/email-queue/{id}/retry', summary: 'Trigger manual redelivery for failed email', perm: 'email_queue.retry', audit: 'EMAIL_QUEUE_RETRY_TRIGGERED' },

  // Module 13: Careers & Vacancies
  { method: 'GET', path: '/admin/jobs', summary: 'List all career vacancies and job postings', perm: 'careers.view', audit: 'None' },
  { method: 'POST', path: '/admin/jobs', summary: 'Create new career job vacancy', perm: 'careers.manage', audit: 'JOB_POSTED' },
  { method: 'PUT', path: '/admin/jobs/{id}', summary: 'Update job posting details or closing date', perm: 'careers.manage', audit: 'JOB_UPDATED' },
  { method: 'DELETE', path: '/admin/jobs/{id}', summary: 'Close or archive job vacancy', perm: 'careers.manage', audit: 'JOB_ARCHIVED' },

  // Module 14: Candidate Applications & Triage
  { method: 'GET', path: '/admin/applications', summary: 'List candidate applications with filters', perm: 'applications.view', audit: 'None' },
  { method: 'PATCH', path: '/admin/applications/{id}/status', summary: 'Advance candidate recruitment stage', perm: 'applications.manage', audit: 'CANDIDATE_STATUS_UPDATED' },
  { method: 'GET', path: '/admin/applications/{id}/download', summary: 'Stream private candidate resume PDF', perm: 'applications.download_cv', audit: 'RESUME_DOWNLOADED' },
  { method: 'GET', path: '/admin/applications/export', summary: 'Export candidates register as CSV', perm: 'applications.export', audit: 'APPLICATIONS_EXPORTED' },

  // Module 15: Case Studies Manager
  { method: 'GET', path: '/admin/case-studies', summary: 'List case study proof entries', perm: 'case_studies.view', audit: 'None' },
  { method: 'POST', path: '/admin/case-studies', summary: 'Create new verified case study proof', perm: 'case_studies.manage', audit: 'CASE_STUDY_CREATED' },
  { method: 'PUT', path: '/admin/case-studies/{id}', summary: 'Update case study challenge, intervention, outcomes', perm: 'case_studies.manage', audit: 'CASE_STUDY_UPDATED' },
  { method: 'DELETE', path: '/admin/case-studies/{id}', summary: 'Archive case study proof record', perm: 'case_studies.manage', audit: 'CASE_STUDY_ARCHIVED' },

  // Module 16: Insights & Blog (Articles)
  { method: 'GET', path: '/admin/articles', summary: 'List articles with pagination and author filter', perm: 'articles.view', audit: 'None' },
  { method: 'POST', path: '/admin/articles', summary: 'Create new healthcare whitepaper or article', perm: 'articles.manage', audit: 'ARTICLE_CREATED' },
  { method: 'PUT', path: '/admin/articles/{id}', summary: 'Update article copy, tags, and reading time', perm: 'articles.manage', audit: 'ARTICLE_UPDATED' },
  { method: 'DELETE', path: '/admin/articles/{id}', summary: 'Archive published article', perm: 'articles.manage', audit: 'ARTICLE_ARCHIVED' },

  // Module 17: SEO & Routing Engine
  { method: 'GET', path: '/admin/seo/routes', summary: 'List SEO route metadata overrides', perm: 'seo.view', audit: 'None' },
  { method: 'POST', path: '/admin/seo/routes', summary: 'Upsert custom meta title and description for route', perm: 'seo.manage', audit: 'SEO_ROUTE_OVERRIDDEN' },
  { method: 'POST', path: '/admin/seo/rebuild-sitemap', summary: 'Trigger dynamic XML sitemap rebuild', perm: 'seo.manage', audit: 'SITEMAP_REBUILT' },

  // Module 18: System Settings
  { method: 'GET', path: '/admin/settings', summary: 'Get global system settings and entity variables', perm: 'settings.view', audit: 'None' },
  { method: 'PUT', path: '/admin/settings', summary: 'Update corporate identity and configuration variables', perm: 'settings.manage', audit: 'SETTINGS_UPDATED' },
  { method: 'POST', path: '/admin/settings/test-smtp', summary: 'Execute live socket test to verify SMTP transport', perm: 'settings.smtp_test', audit: 'SMTP_SOCKET_TESTED' },

  // Module 19: Security Audit Log Explorer
  { method: 'GET', path: '/admin/audit-logs', summary: 'Browse cryptographic SHA-256 chained audit logs', perm: 'audit.view', audit: 'None' },
  { method: 'GET', path: '/admin/audit-logs/{id}', summary: 'Inspect single audit log entry and verify cryptographic hash chain', perm: 'audit.view', audit: 'None' },
  { method: 'GET', path: '/admin/audit-logs/export', summary: 'Export verified audit log ledger for compliance', perm: 'audit.export', audit: 'AUDIT_LOGS_EXPORTED' },

  // Supporting Admin FAQ Accordion Management
  { method: 'GET', path: '/admin/faqs', summary: 'List FAQ items for management', perm: 'faqs.view', audit: 'None' },
  { method: 'POST', path: '/admin/faqs', summary: 'Create new FAQ item', perm: 'faqs.manage', audit: 'FAQ_CREATED' },
  { method: 'PUT', path: '/admin/faqs/{id}', summary: 'Update FAQ question, answer or display order', perm: 'faqs.manage', audit: 'FAQ_UPDATED' },
  { method: 'DELETE', path: '/admin/faqs/{id}', summary: 'Delete FAQ item', perm: 'faqs.manage', audit: 'FAQ_DELETED' }
];

console.log('Public Operations Count:', publicEndpoints.length);
console.log('Admin Operations Count:', adminEndpoints.length);
console.log('Total Operations Count:', publicEndpoints.length + adminEndpoints.length);

const allEndpoints = [...publicEndpoints, ...adminEndpoints];
const uniquePaths = new Set(allEndpoints.map(e => e.path));
console.log('Unique Path Count:', uniquePaths.size);

const uniqueMethodPath = new Set(allEndpoints.map(e => e.method + ' ' + e.path));
console.log('Unique (Method, Path) Operations Count:', uniqueMethodPath.size);
