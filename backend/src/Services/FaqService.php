<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;

/**
 * FAQ Support Center & Accordion Service (Module 16).
 *
 * Implements:
 * - FR-PUB-018 / TEST-PUB-018: Interactive FAQ Accordion categories, display order, search
 * - Admin FAQ management and sequential ordering
 */
class FaqService
{
    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private ?RbacManager $rbacManager;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?RbacManager $rbacManager = null
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
        $this->rbacManager = $rbacManager;
    }

    /**
     * Lists FAQs for administration with optional category filter.
     */
    public function getFaqs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['category'])) {
            $conditions[] = "category = :category";
            $params['category'] = (string)$filters['category'];
        }

        if (isset($filters['is_published']) && $filters['is_published'] !== '' && $filters['is_published'] !== null) {
            $conditions[] = "is_published = :is_published";
            $params['is_published'] = (int)$filters['is_published'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(question LIKE :search1 OR answer_html LIKE :search2)";
            $params['search1'] = $term;
            $params['search2'] = $term;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*) FROM faqs {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        $sql = "SELECT * FROM faqs
                {$whereClause}
                ORDER BY category ASC, display_order ASC, id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $faqs = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['display_order'] = (int)$row['display_order'];
            $row['is_published'] = (int)$row['is_published'];
            return $row;
        }, $rows);

        return [
            'faqs'  => $faqs,
            'items' => $faqs,
            'meta'  => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Gets a single FAQ by ID.
     */
    public function getFaqById(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("FAQ with ID {$id} was not found.");
        }

        $row['id'] = (int)$row['id'];
        $row['display_order'] = (int)$row['display_order'];
        $row['is_published'] = (int)$row['is_published'];

        return $row;
    }

    /**
     * Creates a new FAQ item.
     */
    public function createFaq(array $data, int $editorUserId): array
    {
        $errors = [];

        $question = trim((string)($data['question'] ?? ''));
        if ($question === '') {
            $errors['question'] = 'Question is required.';
        } elseif (strlen($question) > 255) {
            $errors['question'] = 'Question cannot exceed 255 characters.';
        }

        $category = trim((string)($data['category'] ?? 'General'));
        if ($category === '') {
            $errors['category'] = 'Category is required.';
        } elseif (strlen($category) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters.';
        }

        $answer = trim((string)($data['answer_html'] ?? $data['answer'] ?? ''));
        if ($answer === '') {
            $errors['answer_html'] = 'Answer content is required.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $sanitizedAnswer = $this->sanitizer->sanitize($answer);
        $isPublished = !empty($data['is_published']) || !isset($data['is_published']) ? 1 : 0;

        $pdo = $this->connection->getPdo();

        $displayOrder = isset($data['display_order'])
            ? (int)$data['display_order']
            : (int)($pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM faqs WHERE category = '{$category}'")->fetchColumn() ?: 1);

        $stmt = $pdo->prepare(
            "INSERT INTO faqs (category, question, answer_html, display_order, is_published, created_at, updated_at)
             VALUES (:category, :question, :answer_html, :display_order, :is_published, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );

        $stmt->execute([
            'category'      => $category,
            'question'      => $question,
            'answer_html'   => $sanitizedAnswer,
            'display_order' => $displayOrder,
            'is_published'  => $isPublished,
        ]);

        $id = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'faqs',
            'FAQ_CREATED',
            $editorUserId,
            '127.0.0.1',
            'Admin/FaqService',
            'faqs',
            $id,
            [
                'category'      => $category,
                'question'      => $question,
                'display_order' => $displayOrder,
                'is_published'  => $isPublished,
            ]
        );

        return $this->getFaqById($id);
    }

    /**
     * Updates an existing FAQ item.
     */
    public function updateFaq(int $id, array $data, int $editorUserId): array
    {
        $current = $this->getFaqById($id);
        $pdo = $this->connection->getPdo();

        $errors = [];

        $question = isset($data['question']) ? trim((string)$data['question']) : $current['question'];
        if ($question === '') {
            $errors['question'] = 'Question cannot be empty.';
        } elseif (strlen($question) > 255) {
            $errors['question'] = 'Question cannot exceed 255 characters.';
        }

        $category = isset($data['category']) ? trim((string)$data['category']) : $current['category'];
        if ($category === '') {
            $errors['category'] = 'Category cannot be empty.';
        } elseif (strlen($category) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters.';
        }

        $answer = isset($data['answer_html']) || isset($data['answer'])
            ? trim((string)($data['answer_html'] ?? $data['answer']))
            : $current['answer_html'];
        if ($answer === '') {
            $errors['answer_html'] = 'Answer content cannot be empty.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $sanitizedAnswer = $this->sanitizer->sanitize($answer);
        $displayOrder = isset($data['display_order']) ? (int)$data['display_order'] : $current['display_order'];
        $isPublished = isset($data['is_published'])
            ? (!empty($data['is_published']) ? 1 : 0)
            : $current['is_published'];

        $stmt = $pdo->prepare(
            "UPDATE faqs SET
                category = :category,
                question = :question,
                answer_html = :answer_html,
                display_order = :display_order,
                is_published = :is_published,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute([
            'category'      => $category,
            'question'      => $question,
            'answer_html'   => $sanitizedAnswer,
            'display_order' => $displayOrder,
            'is_published'  => $isPublished,
            'id'            => $id,
        ]);
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'faqs',
            'FAQ_UPDATED',
            $editorUserId,
            '127.0.0.1',
            'Admin/FaqService',
            'faqs',
            $id,
            [
                'previous' => [
                    'category'      => $current['category'],
                    'question'      => $current['question'],
                    'display_order' => $current['display_order'],
                ],
                'updated'  => [
                    'category'      => $category,
                    'question'      => $question,
                    'display_order' => $displayOrder,
                ],
            ]
        );

        return $this->getFaqById($id);
    }

    /**
     * Deletes an FAQ item.
     */
    public function deleteFaq(int $id, int $editorUserId): void
    {
        $current = $this->getFaqById($id);
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'faqs',
            'FAQ_DELETED',
            $editorUserId,
            '127.0.0.1',
            'Admin/FaqService',
            'faqs',
            $id,
            [
                'category' => $current['category'],
                'question' => $current['question'],
            ]
        );
    }

    /**
     * Reorders FAQs sequentially.
     */
    public function reorderFaqs(array $orderedIds, int $editorUserId): void
    {
        if (empty($orderedIds)) {
            return;
        }

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("UPDATE faqs SET display_order = :order, updated_at = CURRENT_TIMESTAMP WHERE id = :id");

        foreach ($orderedIds as $index => $id) {
            $stmt->execute([
                'order' => $index + 1,
                'id'    => (int)$id,
            ]);
        }
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'faqs',
            'FAQ_REORDERED',
            $editorUserId,
            '127.0.0.1',
            'Admin/FaqService',
            'faqs',
            null,
            ['ordered_ids' => $orderedIds]
        );
    }

    /**
     * Public delivery of published FAQs with optional category or search filter.
     * Returned with ETag support (TEST-PUB-018).
     */
    public function getPublishedFaqs(?string $category = null, ?string $search = null): array
    {
        $pdo = $this->connection->getPdo();

        $conditions = ["is_published = 1"];
        $params = [];

        if (!empty($category)) {
            $conditions[] = "category = :category";
            $params['category'] = $category;
        }

        if (!empty($search)) {
            $term = '%' . trim($search) . '%';
            $conditions[] = "(question LIKE :search1 OR answer_html LIKE :search2)";
            $params['search1'] = $term;
            $params['search2'] = $term;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT id, category, question, answer_html, display_order, created_at
                FROM faqs
                {$whereClause}
                ORDER BY category ASC, display_order ASC, id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $faqs = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['display_order'] = (int)$row['display_order'];
            return $row;
        }, $rows);

        // Group by category for convenience in frontend accordions
        $grouped = [];
        foreach ($faqs as $item) {
            $grouped[$item['category']][] = $item;
        }

        $etagInput = count($faqs) . '-' . implode('-', array_column($faqs, 'id')) . '-' . implode('-', array_column($faqs, 'display_order'));
        $etag = '"' . sha1($etagInput) . '"';

        return [
            'faqs'       => $faqs,
            'categories' => $grouped,
            'total'      => count($faqs),
            'etag'       => $etag,
        ];
    }
}
