<?php

declare(strict_types=1);

/**
 * Voak Health Care — Repository Secret Scanner
 * Inspects tracked and newly staged repository files for accidental credentials,
 * private keys, API tokens, and live secrets.
 */

$rootDir = dirname(__DIR__);
chdir($rootDir);

// High-confidence credential / secret patterns
$patterns = [
    'Private Key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    'GitHub Token' => '/\bgh[pousr]_[A-Za-z0-9_]{36,255}\b/',
    'AWS Access Key' => '/\b(AKIA|ABIA|ACCA|ASIA)[0-9A-Z]{16}\b/',
    'Stripe Live Key' => '/\bsk_live_[0-9a-zA-Z]{24,}\b/',
    'Slack Webhook' => '/https:\/\/hooks\.slack\.com\/services\/T[a-zA-Z0-9_]+\/B[a-zA-Z0-9_]+\/[a-zA-Z0-9_]+/',
];

$excludedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'zip', 'tar', 'gz', 'woff', 'woff2', 'ttf', 'eot'];
$excludedFiles = [
    'tests/scan_secrets.php',
];

// Obtain list of files via git
$tracked = explode("\n", trim((string) shell_exec('git ls-files')));
$untracked = explode("\n", trim((string) shell_exec('git ls-files --others --exclude-standard')));
$allFiles = array_filter(array_unique(array_merge($tracked, $untracked)));

$violations = [];
$scannedCount = 0;

foreach ($allFiles as $relPath) {
    $relPath = trim(str_replace('\\', '/', $relPath));
    if ($relPath === '' || !file_exists($relPath)) {
        continue;
    }

    if (in_array($relPath, $excludedFiles, true)) {
        continue;
    }

    if (str_ends_with($relPath, '.example')) {
        continue;
    }

    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
    if (in_array($ext, $excludedExtensions, true)) {
        continue;
    }

    $scannedCount++;
    $lines = file($relPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNum => $line) {
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $line)) {
                $violations[] = [
                    'file' => $relPath,
                    'line' => $lineNum + 1,
                    'rule' => $name,
                ];
            }
        }
    }
}

echo "======================================================================\n";
echo "VOAK HEALTH CARE — REPOSITORY SECURITY & CREDENTIAL SCAN\n";
echo "======================================================================\n";
echo "Scanned Files: {$scannedCount}\n";

if (!empty($violations)) {
    echo "FAILED: Potential credential / secret exposure detected!\n";
    foreach ($violations as $v) {
        echo "  [VIOLATION] {$v['file']}:{$v['line']} (Matched: {$v['rule']})\n";
    }
    echo "======================================================================\n";
    exit(1);
}

echo "SUCCESS: 0 secrets detected. Repository clean.\n";
echo "======================================================================\n";
exit(0);
