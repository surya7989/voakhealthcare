<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 0 Baseline Database Migration Compatibility Shim
 * 
 * Preserves the Phase 0 baseline invocation command:
 *   php database/migrate.php up
 *   php database/migrate.php down
 *   php database/migrate.php status
 * 
 * Forwards execution to the canonical Phase 5 runner at backend/cli/migrate.php.
 */

$canonicalRunner = __DIR__ . '/../backend/cli/migrate.php';

if (!file_exists($canonicalRunner)) {
    fwrite(STDERR, "[ERROR] Canonical migration runner not found at: {$canonicalRunner}\n");
    exit(1);
}

// Forward execution to canonical runner
require $canonicalRunner;
