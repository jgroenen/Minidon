<?php
declare(strict_types=1);

namespace Minidon;

/**
 * Represents an ActivityPub Actor with keys and metadata.
 */
final class Actor
{
    private string $privateKeyPEM = '';
    private string $publicKeyPEM = '';

    public function __construct(
        public string $username,
        public string $name,
        public string $domain,
    ) {
        $this->generateKeys();
    }

    /**
     * Get the private key PEM for signing requests.
     * WARNING: Only use for HTTP Signature signing, never expose this key.
     */
    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPEM;
    }

    /**
     * Get the full actor URL.
     */
    public function getUrl(): string
    {
        return $this->domain . '/@' . $this->username;
    }

    /**
     * Get the inbox URL.
     */
    public function getInboxUrl(): string
    {
        return $this->getUrl() . '/inbox';
    }

    /**
     * Get the outbox URL.
     * ActivityPub spec: must be /@{username}/outbox
     */
    public function getOutboxUrl(): string
    {
        return $this->getUrl() . '/outbox';
    }

    /**
     * Get the public key ID.
     */
    public function getPublicKeyId(): string
    {
        return $this->getUrl() . '#main-key';
    }

    /**
     * Get the public key PEM.
     */
    public function getPublicKeyPem(): string
    {
        return $this->publicKeyPEM;
    }

    /**
     * Get the actor as an ActivityPub JSON object.
     */
    public function toArray(): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->getUrl(),
            'type' => 'Person',
            'name' => $this->name,
            'preferredUsername' => $this->username,
            'inbox' => $this->getInboxUrl(),
            'outbox' => $this->getOutboxUrl(),
            'url' => $this->getUrl(),
            'publicKey' => [
                'id' => $this->getPublicKeyId(),
                'owner' => $this->getUrl(),
                'publicKeyPem' => $this->publicKeyPEM,
            ],
        ];
    }

    /**
     * Generate Ed25519 key pair for ActivityPub.
     *
     * @throws \RuntimeException If Ed25519 is not supported (requires PHP 8.0+ with OpenSSL 1.1.1+)
     */
    private function generateKeys(): void
    {
        // Check if Ed25519 is supported
        if (!defined('OPENSSL_KEYTYPE_ED25519')) {
            throw new \RuntimeException(
                'Ed25519 not supported. Requires PHP 8.0+ with OpenSSL 1.1.1+.'
            );
        }

        $keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_ED25519,
        ]);

        if ($keyPair === false) {
            throw new \RuntimeException(
                'Failed to generate Ed25519 key pair. Check OpenSSL configuration.'
            );
        }

        $exportResult = openssl_pkey_export($keyPair, $this->privateKeyPEM);
        if ($exportResult === false) {
            throw new \RuntimeException(
                'Failed to export private key.'
            );
        }

        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        if ($publicKeyDetails === false || !isset($publicKeyDetails['key'])) {
            throw new \RuntimeException(
                'Failed to get public key details.'
            );
        }

        $this->publicKeyPEM = $publicKeyDetails['key'];
    }

    /**
     * Normalize username for use in URLs and file paths.
     */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(str_replace([' ', '@'], '_', $username));
    }
}
