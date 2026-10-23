<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 4 Root Test Harness
 * Executes the backend test suite from repository root.
 */

$backendDir = dirname(__DIR__) . '/backend';
$runner = $backendDir . '/tests/run_tests.php';

if (!file_exists($runner)) {
    echo "FATAL: Backend test runner not found at {$runner}\n";
    exit(1);
}

$phpBin = defined('PHP_BINARY') && PHP_BINARY ? escapeshellcmd(PHP_BINARY) : 'php';
passthru("{$phpBin} -d memory_limit=512M \"{$runner}\"", $exitCode);
exit($exitCode);
