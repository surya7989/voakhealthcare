<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Tests\TestFramework;

class HtmlSanitizerTest
{
    public static function run(): void
    {
        TestFramework::suite('Phase 7 — HtmlSanitizer & Stored-XSS Defense Tests', function () {
            $sanitizer = new HtmlSanitizer();

            TestFramework::test('preserves benign formatted HTML markup and structure', function () use ($sanitizer) {
                $input = '<p>Welcome to <strong>Voak Health Care</strong>. Visit <a href="https://voak.com.au">our website</a>.</p>';
                $output = $sanitizer->clean($input);

                TestFramework::assertContains('<p>', $output);
                TestFramework::assertContains('<strong>Voak Health Care</strong>', $output);
                TestFramework::assertContains('<a href="https://voak.com.au">our website</a>', $output);
            });

            TestFramework::test('strips malicious <script> tags and inner executable content', function () use ($sanitizer) {
                $input = '<p>Clinical Notice <script>alert("xss")</script></p>';
                $output = $sanitizer->clean($input);

                TestFramework::assertFalse(str_contains($output, '<script>'));
                TestFramework::assertFalse(str_contains($output, 'alert("xss")'));
                TestFramework::assertContains('Clinical Notice', $output);
                TestFramework::assertContains('<p>', $output);
                TestFramework::assertContains('</p>', $output);
            });

            TestFramework::test('strips inline event handler attributes (onerror, onload, onclick, onmouseover)', function () use ($sanitizer) {
                $vectors = [
                    '<img src="invalid.jpg" onerror="alert(1)">',
                    '<body onload="alert(1)">',
                    '<div onmouseover="alert(1)">Hover me</div>',
                    '<button onclick="alert(1)">Click</button>',
                ];

                foreach ($vectors as $v) {
                    $cleaned = $sanitizer->clean($v);
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'alert(1)'), "Event handler not stripped from: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'onerror'), "onerror attribute remained in: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'onload'), "onload attribute remained in: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'onclick'), "onclick attribute remained in: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'onmouseover'), "onmouseover attribute remained in: {$v}");
                }
            });

            TestFramework::test('neutralizes dangerous javascript: URI schemes in hyperlinks and image sources', function () use ($sanitizer) {
                $input = '<a href="javascript:alert(document.cookie)">Click Here</a>';
                $output = $sanitizer->clean($input);

                TestFramework::assertFalse(str_contains(strtolower($output), 'javascript:'));
                TestFramework::assertFalse(str_contains(strtolower($output), 'document.cookie'));
            });

            TestFramework::test('strips dangerous <iframe>, <object>, and <embed> tags', function () use ($sanitizer) {
                $input = '<div>Safe content <iframe src="https://attacker.com/payload"></iframe><embed src="malware.swf"></div>';
                $output = $sanitizer->clean($input);

                TestFramework::assertFalse(str_contains($output, '<iframe'));
                TestFramework::assertFalse(str_contains($output, '<embed'));
                TestFramework::assertContains('Safe content', $output);
            });

            TestFramework::test('neutralizes dangerous SVG vectors with embedded scripts or onload', function () use ($sanitizer) {
                $input = '<svg onload="alert(1)"><circle cx="50" cy="50" r="40"/><script>alert(2)</script></svg>';
                $output = $sanitizer->clean($input);

                TestFramework::assertFalse(str_contains(strtolower($output), 'onload'));
                TestFramework::assertFalse(str_contains(strtolower($output), '<script'));
                TestFramework::assertFalse(str_contains(strtolower($output), 'alert(1)'));
                TestFramework::assertFalse(str_contains(strtolower($output), 'alert(2)'));
            });

            TestFramework::test('recursively sanitizes associative arrays and nested JSON structures', function () use ($sanitizer) {
                $payload = [
                    'title' => 'Healthcare Policy',
                    'meta' => [
                        'description' => 'Safe description',
                        'injection'   => '<script>alert("nested")</script>',
                    ],
                    'sections' => [
                        ['heading' => 'Governance', 'body' => '<p>Valid text <img src=x onerror=alert(2)></p>'],
                    ],
                ];

                $sanitized = $sanitizer->sanitizePayload($payload);

                TestFramework::assertEquals('Healthcare Policy', $sanitized['title']);
                TestFramework::assertFalse(str_contains($sanitized['meta']['injection'], '<script>'));
                TestFramework::assertFalse(str_contains($sanitized['sections'][0]['body'], 'onerror'));
                TestFramework::assertContains('Valid text', $sanitized['sections'][0]['body']);
            });

            TestFramework::test('handles non-string, integer, float, boolean, and null data types safely', function () use ($sanitizer) {
                TestFramework::assertEquals(42, $sanitizer->clean(42));
                TestFramework::assertEquals(3.14, $sanitizer->clean(3.14));
                TestFramework::assertEquals(true, $sanitizer->clean(true));
                TestFramework::assertEquals(null, $sanitizer->clean(null));
                TestFramework::assertEquals('', $sanitizer->clean(''));
            });

            TestFramework::test('neutralizes HTML entity encoded javascript schemes and obfuscated pseudoprotocols', function () use ($sanitizer) {
                $vectors = [
                    '<a href="java&#x73;cript:alert(1)">Entity XSS 1</a>',
                    '<a href="jav&#97;script:alert(2)">Entity XSS 2</a>',
                    '<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(3)">Entity XSS 3</a>',
                    '<a href="data:text/html,<script>alert(4)</script>">Data URI</a>',
                    '<a href="vbscript:msgbox(5)">VBScript</a>',
                    '<a href="//attacker.com/exploit">Protocol Relative</a>',
                    '<math href="javascript:alert(6)">Math</math>',
                ];

                foreach ($vectors as $v) {
                    $cleaned = $sanitizer->clean($v);
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'javascript:'), "Failed for: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'alert('), "Failed for: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'attacker.com'), "Failed for: {$v}");
                    TestFramework::assertFalse(str_contains(strtolower($cleaned), 'msgbox'), "Failed for: {$v}");
                }
            });
        });
    }
}
