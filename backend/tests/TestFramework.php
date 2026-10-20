<?php

declare(strict_types=1);

namespace Voak\Health\Tests;

/**
 * Lightweight, zero-dependency test assertion framework for Voak Health Care backend tests.
 */
class TestFramework
{
    private static int $assertions = 0;
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $failures = [];
    private static string $currentSuite = '';

    public static function suite(string $name, callable $tests): void
    {
        self::$currentSuite = $name;
        echo "\n=== SUITE: {$name} ===\n";
        $tests();
    }

    public static function test(string $name, callable $test): void
    {
        try {
            $test();
            self::$passed++;
            echo "  [PASS] {$name}\n";
        } catch (\Throwable $e) {
            self::$failed++;
            self::$failures[] = [
                'suite' => self::$currentSuite,
                'test' => $name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            echo "  [FAIL] {$name} - {$e->getMessage()}\n";
        }
    }

    public static function assertTrue(bool $condition, string $message = 'Expected true, got false'): void
    {
        self::$assertions++;
        if (!$condition) {
            throw new \AssertionError($message);
        }
    }

    public static function assertFalse(bool $condition, string $message = 'Expected false, got true'): void
    {
        self::$assertions++;
        if ($condition) {
            throw new \AssertionError($message);
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            $desc = $message !== '' ? "{$message}. " : '';
            throw new \AssertionError("{$desc}Expected [{$expStr}], got [{$actStr}]");
        }
    }

    public static function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        self::$assertions++;
        if (!str_contains($haystack, $needle)) {
            $desc = $message !== '' ? "{$message}. " : '';
            throw new \AssertionError("{$desc}String does not contain expected substring [{$needle}]");
        }
    }

    public static function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        self::$assertions++;
        if (!array_key_exists($key, $array)) {
            $desc = $message !== '' ? "{$message}. " : '';
            throw new \AssertionError("{$desc}Array missing key [{$key}]");
        }
    }

    public static function expectException(string $expectedClass, callable $callback, ?string $expectedMessageContains = null): void
    {
        self::$assertions++;
        try {
            $callback();
        } catch (\Throwable $e) {
            if (!($e instanceof $expectedClass)) {
                throw new \AssertionError("Expected exception of type [{$expectedClass}], but got [" . get_class($e) . "]");
            }
            if ($expectedMessageContains !== null && !str_contains($e->getMessage(), $expectedMessageContains)) {
                throw new \AssertionError("Expected exception message containing [{$expectedMessageContains}], but got [{$e->getMessage()}]");
            }
            return;
        }
        throw new \AssertionError("Expected exception [{$expectedClass}] was not thrown.");
    }

    public static function summarize(): int
    {
        echo "\n======================================================================\n";
        echo "TEST EXECUTION SUMMARY\n";
        echo "======================================================================\n";
        echo "Total Tests Executed: " . (self::$passed + self::$failed) . "\n";
        echo "Total Assertions:     " . self::$assertions . "\n";
        echo "Passed:               " . self::$passed . "\n";
        echo "Failed:               " . self::$failed . "\n";

        if (!empty(self::$failures)) {
            echo "\nFailures:\n";
            foreach (self::$failures as $f) {
                echo "  - [{$f['suite']}] {$f['test']}: {$f['error']} ({$f['file']}:{$f['line']})\n";
            }
            echo "======================================================================\n";
            return 1;
        }

        echo "======================================================================\n";
        echo "SUCCESS: ALL TESTS PASSED (100% VERIFIED)\n";
        echo "======================================================================\n";
        return 0;
    }
}
