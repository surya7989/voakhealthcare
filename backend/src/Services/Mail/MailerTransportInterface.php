<?php

declare(strict_types=1);

namespace Voak\Health\Services\Mail;

/**
 * Interface for mail dispatch transports.
 */
interface MailerTransportInterface
{
    /**
     * Sends an email message.
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient display name
     * @param string $subject Email subject line
     * @param string $htmlBody Rendered HTML body content
     * @param array<string, string> $headers Additional email headers
     * @return bool True if accepted by transport, false otherwise
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        array $headers = []
    ): bool;

    /**
     * Gets the last error message from the transport, if any.
     */
    public function getLastError(): ?string;
}
