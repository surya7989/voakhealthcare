<?php

declare(strict_types=1);

namespace Voak\Health\Services\Mail;

/**
 * In-memory test and offline transport.
 * Records dispatched emails for verification and allows controlled failure injection.
 */
class ArrayTransport implements MailerTransportInterface
{
    /**
     * @var array<int, array{to_email: string, to_name: string, subject: string, html_body: string, headers: array<string, string>, dispatched_at: string}>
     */
    private array $sentMessages = [];

    private bool $shouldFail = false;
    private ?string $lastError = null;
    private int $delayMs = 0;

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        array $headers = []
    ): bool {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }

        if ($this->shouldFail) {
            return false;
        }

        $this->sentMessages[] = [
            'to_email'      => $toEmail,
            'to_name'       => $toName,
            'subject'       => $subject,
            'html_body'     => $htmlBody,
            'headers'       => $headers,
            'dispatched_at' => date('Y-m-d H:i:s'),
        ];

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setFailing(bool $shouldFail, ?string $errorMessage = 'Simulated SMTP connection error'): void
    {
        $this->shouldFail = $shouldFail;
        $this->lastError = $shouldFail ? $errorMessage : null;
    }

    public function setDelayMs(int $ms): void
    {
        $this->delayMs = $ms;
    }

    /**
     * @return array<int, array{to_email: string, to_name: string, subject: string, html_body: string, headers: array<string, string>, dispatched_at: string}>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function count(): int
    {
        return count($this->sentMessages);
    }

    public function clear(): void
    {
        $this->sentMessages = [];
        $this->shouldFail = false;
        $this->lastError = null;
        $this->delayMs = 0;
    }
}
