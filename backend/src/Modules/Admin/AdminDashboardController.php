<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Administrative Executive Dashboard Controller (Module 01 / ADM-001 / Phase 17).
 * 
 * Provides:
 * - Aggregated KPI metrics across inquiries, careers, content publications, and users.
 * - Real-time operational audit telemetry feed.
 * 
 * Fail-Closed Security:
 * Requires authenticated administrative session with dashboard.view permission.
 */
class AdminDashboardController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    private function requireAdminUserId(Request $request): int
    {
        $userId = $request->getAttribute('admin_user_id');
        if ($userId === null || (int)$userId <= 0) {
            throw new AuthenticationException("Authentication required. No authenticated administrative user identity present.");
        }
        return (int)$userId;
    }

    /**
     * Helper to safely execute a COUNT query returning 0 if the table is missing or errors.
     */
    private function safeCount(\PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * GET /api/v1/admin/dashboard/stats
     * GET /api/admin/dashboard/stats
     */
    public function getStats(Request $request): Response
    {
        $this->requireAdminUserId($request);
        $pdo = $this->connection->getPdo();

        // 1. Inquiries & CRM Metrics
        $contactTotal = $this->safeCount($pdo, "SELECT COUNT(*) FROM contact_inquiries");
        $contactNew = $this->safeCount($pdo, "SELECT COUNT(*) FROM contact_inquiries WHERE status = 'NEW'");
        $partnershipsTotal = $this->safeCount($pdo, "SELECT COUNT(*) FROM partnership_inquiries");
        $proposalsTotal = $this->safeCount($pdo, "SELECT COUNT(*) FROM proposals");

        // 2. Recruitment & Careers Metrics
        $activeJobs = $this->safeCount($pdo, "SELECT COUNT(*) FROM job_postings WHERE is_active = 1");
        $applicationsTotal = $this->safeCount($pdo, "SELECT COUNT(*) FROM career_applications");
        $applicationsPending = $this->safeCount($pdo, "SELECT COUNT(*) FROM career_applications WHERE status = 'SUBMITTED'");

        // 3. Content & CMS Metrics
        $publishedPages = $this->safeCount($pdo, "SELECT COUNT(*) FROM pages WHERE is_published = 1");
        $publishedServices = $this->safeCount($pdo, "SELECT COUNT(*) FROM services WHERE is_published = 1");
        $publishedArticles = $this->safeCount($pdo, "SELECT COUNT(*) FROM articles WHERE is_published = 1");
        $publishedCaseStudies = $this->safeCount($pdo, "SELECT COUNT(*) FROM case_studies WHERE is_published = 1");
        $totalFaqs = $this->safeCount($pdo, "SELECT COUNT(*) FROM faqs");

        // 4. Users & System Metrics
        $activeUsers = $this->safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL");
        $auditTotal = $this->safeCount($pdo, "SELECT COUNT(*) FROM audit_logs");

        // 5. Recent Activity Feed from Audit Ledger
        $recentActivity = [];
        try {
            $stmt = $pdo->query(
                "SELECT a.id, a.domain, a.action, a.entity_type, a.entity_id, a.created_at, 
                        u.name AS actor_name, u.email AS actor_email 
                 FROM audit_logs a 
                 LEFT JOIN users u ON a.user_id = u.id 
                 ORDER BY a.id DESC 
                 LIMIT 10"
            );
            if ($stmt) {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                foreach ($rows as $r) {
                    $recentActivity[] = [
                        'id'          => (int)$r['id'],
                        'domain'      => (string)$r['domain'],
                        'action'      => (string)$r['action'],
                        'entity_type' => $r['entity_type'] !== null ? (string)$r['entity_type'] : null,
                        'entity_id'   => $r['entity_id'] !== null ? (int)$r['entity_id'] : null,
                        'actor_name'  => $r['actor_name'] !== null ? (string)$r['actor_name'] : 'System',
                        'actor_email' => $r['actor_email'] !== null ? (string)$r['actor_email'] : 'internal@voakhealthcare.com',
                        'created_at'  => (string)$r['created_at'],
                    ];
                }
            }
        } catch (\Throwable) {
            $recentActivity = [];
        }

        return Response::success([
            'metrics' => [
                'inquiries' => [
                    'contact_total'      => $contactTotal,
                    'contact_new'        => $contactNew,
                    'partnerships_total' => $partnershipsTotal,
                    'proposals_total'    => $proposalsTotal,
                ],
                'recruitment' => [
                    'active_jobs'          => $activeJobs,
                    'applications_total'   => $applicationsTotal,
                    'applications_pending' => $applicationsPending,
                ],
                'content' => [
                    'published_pages'        => $publishedPages,
                    'published_services'     => $publishedServices,
                    'published_articles'     => $publishedArticles,
                    'published_case_studies' => $publishedCaseStudies,
                    'faqs_total'             => $totalFaqs,
                ],
                'system' => [
                    'active_users'         => $activeUsers,
                    'audit_ledger_records' => $auditTotal,
                ],
            ],
            'recent_activity' => $recentActivity,
        ], 200, $request->getCorrelationId());
    }
}
