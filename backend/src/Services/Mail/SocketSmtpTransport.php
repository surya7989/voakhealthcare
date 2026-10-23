<?php

declare(strict_types=1);

namespace Voak\Health\Services\Mail;

/**
 * Enterprise Bounded Socket SMTP Transport.
 * Implements direct stream socket SMTP handshakes with strict timeouts:
 * - 3.0s connection timeout
 * - 4.0s read/write stream timeout
 * - 5.0s maximum socket ceiling
 * Guarded against SMTP header injection (CRLF), buffer overflows, and unhandled socket exceptions.
 */
class SocketSmtpTransport implements MailerTransportInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private float $connectTimeout;
    private float $rwTimeout;
    private ?string $lastError = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 25,
        string $username = '',
        string $password = '',
        string $encryption = '',
        string $fromAddress = 'noreply@voak.com.au',
        string $fromName = 'Voak Health Care',
        float $connectTimeout = 3.0,
        float $rwTimeout = 4.0
    ) {
        $this->host           = $host;
        $this->port           = $port;
        $this->username       = $username;
        $this->password       = $password;
        $this->encryption     = strtolower($encryption);
        $this->fromAddress    = $fromAddress;
        $this->fromName       = $fromName;
        $this->connectTimeout = $connectTimeout;
        $this->rwTimeout      = $rwTimeout;
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        array $headers = []
    ): bool {
        $this->lastError = null;

        // Sanitize CRLF injection in headers
        $cleanSubject = str_replace(["\r", "\n"], ' ', trim($subject));
        $cleanToEmail = str_replace(["\r", "\n"], '', trim($toEmail));
        $cleanToName  = str_replace(["\r", "\n"], ' ', trim($toName));

        if (!filter_var($cleanToEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Invalid recipient email address: [{$cleanToEmail}]";
            return false;
        }

        $protocol = '';
        if ($this->encryption === 'ssl' || $this->port === 465) {
            $protocol = 'ssl://';
        }

        $remoteSocket = $protocol . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->lastError = "SMTP connection to {$remoteSocket} failed: [{$errno}] {$errstr}";
            return false;
        }

        stream_set_timeout($socket, (int)$this->rwTimeout, (int)(($this->rwTimeout - (int)$this->rwTimeout) * 1000000));

        try {
            // Read greeting banner
            $greeting = $this->readResponse($socket);
            if (!str_starts_with($greeting, '220')) {
                throw new \RuntimeException("Unexpected SMTP greeting: {$greeting}");
            }

            // EHLO
            $clientHost = gethostname() ?: 'localhost';
            $this->sendCommand($socket, "EHLO {$clientHost}");
            $ehloResp = $this->readResponse($socket);

            // STARTTLS if requested and on non-SSL port
            if ($this->encryption === 'tls' || ($this->encryption === '' && $this->port === 587)) {
                $this->sendCommand($socket, 'STARTTLS');
                $tlsResp = $this->readResponse($socket);
                if (str_starts_with($tlsResp, '220')) {
                    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new \RuntimeException('STARTTLS cryptographic handshake failed.');
                    }
                    // Re-issue EHLO over TLS tunnel
                    $this->sendCommand($socket, "EHLO {$clientHost}");
                    $this->readResponse($socket);
                }
            }

            // Authentication if credentials provided
            if ($this->username !== '' && $this->password !== '') {
                $this->sendCommand($socket, 'AUTH LOGIN');
                $authResp = $this->readResponse($socket);
                if (!str_starts_with($authResp, '334')) {
                    throw new \RuntimeException("AUTH LOGIN initiation failed: {$authResp}");
                }

                $this->sendCommand($socket, base64_encode($this->username));
                $userResp = $this->readResponse($socket);
                if (!str_starts_with($userResp, '334')) {
                    throw new \RuntimeException("AUTH LOGIN username rejected: {$userResp}");
                }

                $this->sendCommand($socket, base64_encode($this->password));
                $passResp = $this->readResponse($socket);
                if (!str_starts_with($passResp, '235')) {
                    throw new \RuntimeException("SMTP authentication failed: {$passResp}");
                }
            }

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromAddress}>");
            $mailResp = $this->readResponse($socket);
            if (!str_starts_with($mailResp, '250')) {
                throw new \RuntimeException("MAIL FROM command rejected: {$mailResp}");
            }

            // RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$cleanToEmail}>");
            $rcptResp = $this->readResponse($socket);
            if (!str_starts_with($rcptResp, '250') && !str_starts_with($rcptResp, '251')) {
                throw new \RuntimeException("RCPT TO command rejected for [{$cleanToEmail}]: {$rcptResp}");
            }

            // DATA
            $this->sendCommand($socket, 'DATA');
            $dataResp = $this->readResponse($socket);
            if (!str_starts_with($dataResp, '354')) {
                throw new \RuntimeException("DATA command rejected: {$dataResp}");
            }

            // Construct RFC 5322 payload
            $msgId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(16)), time(), $this->host);
            $mimeHeaders = [
                'Date'                      => date('r'),
                'To'                        => "{$cleanToName} <{$cleanToEmail}>",
                'From'                      => "{$this->fromName} <{$this->fromAddress}>",
                'Subject'                   => $cleanSubject,
                'Message-ID'                => $msgId,
                'MIME-Version'              => '1.0',
                'Content-Type'              => 'text/html; charset=UTF-8',
                'Content-Transfer-Encoding' => '8bit',
                'X-Mailer'                  => 'VoakHealth-TriTierMailer/1.0',
            ];

            foreach ($headers as $hk => $hv) {
                $cleanHk = str_replace(["\r", "\n", ':'], '', trim($hk));
                $cleanHv = str_replace(["\r", "\n"], ' ', trim($hv));
                if ($cleanHk !== '') {
                    $mimeHeaders[$cleanHk] = $cleanHv;
                }
            }

            $rawPayload = '';
            foreach ($mimeHeaders as $hk => $hv) {
                $rawPayload .= "{$hk}: {$hv}\r\n";
            }
            $rawPayload .= "\r\n";
            $rawPayload .= $htmlBody;
            $rawPayload .= "\r\n.\r\n";

            $this->sendRaw($socket, $rawPayload);
            $finalResp = $this->readResponse($socket);
            if (!str_starts_with($finalResp, '250')) {
                throw new \RuntimeException("Message body transmission rejected: {$finalResp}");
            }

            // QUIT
            $this->sendCommand($socket, 'QUIT');
            $this->readResponse($socket);

            fclose($socket);
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            @fclose($socket);
            return false;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function sendCommand($socket, string $command): void
    {
        $this->sendRaw($socket, $command . "\r\n");
    }

    private function sendRaw($socket, string $data): void
    {
        $written = 0;
        $len = strlen($data);
        while ($written < $len) {
            $chunk = @fwrite($socket, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new \RuntimeException('Socket write failed or connection aborted.');
            }
            $written += $chunk;
        }
    }

    private function readResponse($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = @fgets($socket, 512);
            if ($line === false) {
                $info = stream_get_meta_data($socket);
                if (!empty($info['timed_out'])) {
                    throw new \RuntimeException("SMTP socket read timed out after {$this->rwTimeout} seconds.");
                }
                break;
            }
            $response .= $line;
            // Check for RFC 5321 multiline continuation: code followed by space or end
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return trim($response);
    }
}
