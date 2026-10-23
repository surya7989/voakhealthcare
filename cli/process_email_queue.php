<?php

declare(strict_types=1);

/**
 * Root convenience shim for process_email_queue.php.
 * Forwards to backend/cli/process_email_queue.php.
 */

require_once __DIR__ . '/backend/cli/process_email_queue.php';
