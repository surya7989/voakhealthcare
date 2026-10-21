<?php

declare(strict_types=1);

namespace Voak\Health\Auth;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Security\Crypto;
use Voak\Health\Security\TotpEngine;

/**
 * Multi-Factor Authentication (MFA / TOTP) Service.
 * Manages secret generation, AES-256-GCM encryption, verification, and recovery backup codes.
 */
class TotpService
{
    private TotpEngine $totpEngine;
    private Config $config;

    public function __construct(Config $config, ?TotpEngine $totpEngine = null)
    {
        $this->config = $config;
        $this->totpEngine = $totpEngine ?? new TotpEngine();
    }

    /**
     * Checks if a user has confirmed and active MFA configured.
     */
    public function isMfaActive(\PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare("SELECT `is_confirmed` FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $confirmed = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $confirmed !== false && (int)$confirmed === 1;
    }

    /**
     * Initializes MFA enrollment by generating secret, QR URI, and 8 recovery codes.
     *
     * @return array{secret: string, provisioning_uri: string, backup_codes: array<string>}
     */
    public function initiateSetup(\PDO $pdo, int $userId, string $accountEmail): array
    {
        $secret = $this->totpEngine->generateSecret(20);
        $provisioningUri = $this->totpEngine->getProvisioningUri($secret, $accountEmail, 'Voak Health Care');

        // Generate 8 single-use 10-char backup codes
        $rawBackupCodes = [];
        $hashedBackupCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
            $rawBackupCodes[] = $code;
            $hashedBackupCodes[] = hash('sha256', $code);
        }

        $masterKey = $this->getEncryptionKey();
        $encryptedSecret = Crypto::encrypt($secret, $masterKey);
        $backupJson = json_encode($hashedBackupCodes, JSON_THROW_ON_ERROR);

        // Delete existing unconfirmed secret if any
        $del = $pdo->prepare("DELETE FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $del->execute(['uid' => $userId]);
        $del->closeCursor();

        $stmt = $pdo->prepare(
            "INSERT INTO `admin_mfa_secrets` 
             (`user_id`, `encrypted_secret`, `backup_codes_json`, `is_confirmed`, `created_at`, `updated_at`) 
             VALUES 
             (:uid, :secret, :codes, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'uid'    => $userId,
            'secret' => $encryptedSecret,
            'codes'  => $backupJson,
        ]);
        $stmt->closeCursor();

        return [
            'secret'           => $secret,
            'provisioning_uri' => $provisioningUri,
            'backup_codes'     => $rawBackupCodes,
        ];
    }

    /**
     * Confirms and activates MFA setup by validating the first 6-digit TOTP code.
     */
    public function confirmSetup(\PDO $pdo, int $userId, string $code): bool
    {
        $stmt = $pdo->prepare("SELECT `encrypted_secret` FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $encrypted = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($encrypted === false) {
            return false;
        }

        $secret = Crypto::decrypt((string)$encrypted, $this->getEncryptionKey());
        if ($secret === null) {
            throw new SecurityException("Failed to decrypt MFA secret.");
        }

        if (!$this->totpEngine->verify($secret, $code)) {
            return false;
        }

        $update = $pdo->prepare("UPDATE `admin_mfa_secrets` SET `is_confirmed` = 1, `updated_at` = CURRENT_TIMESTAMP WHERE `user_id` = :uid");
        $update->execute(['uid' => $userId]);
        $update->closeCursor();

        return true;
    }

    /**
     * Verifies a 6-digit TOTP code for a user during login.
     */
    public function verifyLoginCode(\PDO $pdo, int $userId, string $code): bool
    {
        $stmt = $pdo->prepare("SELECT `encrypted_secret`, `is_confirmed` FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row || (int)$row['is_confirmed'] !== 1) {
            return false;
        }

        $secret = Crypto::decrypt((string)$row['encrypted_secret'], $this->getEncryptionKey());
        if ($secret === null) {
            return false;
        }

        return $this->totpEngine->verify($secret, $code);
    }

    /**
     * Verifies and consumes a single-use emergency recovery backup code.
     */
    public function verifyAndConsumeRecoveryCode(\PDO $pdo, int $userId, string $backupCode): bool
    {
        $cleanCode = strtoupper(trim(str_replace(['-', ' '], '', $backupCode)));
        $submittedHash = hash('sha256', $cleanCode);

        $stmt = $pdo->prepare("SELECT `backup_codes_json`, `is_confirmed` FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row || (int)$row['is_confirmed'] !== 1) {
            return false;
        }

        $codes = json_decode((string)$row['backup_codes_json'], true);
        if (!is_array($codes)) {
            return false;
        }

        $foundIndex = null;
        foreach ($codes as $idx => $storedHash) {
            if (hash_equals((string)$storedHash, $submittedHash)) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return false;
        }

        // Consume code
        unset($codes[$foundIndex]);
        $updatedJson = json_encode(array_values($codes));

        $update = $pdo->prepare("UPDATE `admin_mfa_secrets` SET `backup_codes_json` = :codes, `updated_at` = CURRENT_TIMESTAMP WHERE `user_id` = :uid");
        $update->execute(['codes' => $updatedJson, 'uid' => $userId]);
        $update->closeCursor();

        return true;
    }

    private function getEncryptionKey(): string
    {
        $key = (string)$this->config->get('MFA_ENCRYPTION_KEY', '');
        if ($key === '') {
            $key = (string)$this->config->get('APP_KEY', 'voak-default-development-encryption-key-32-chars!!');
        }
        return $key;
    }
}
