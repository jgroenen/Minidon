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
     */
    public function getOutboxUrl(): string
    {
        return $this->domain . '/posts';
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
     */
    private function generateKeys(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_ED25519,
        ]);
        openssl_pkey_export($keyPair, $this->privateKeyPEM);
        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        $this->publicKeyPEM = $publicKeyDetails['key'];
    }

    /**
     * Normalize username for use in URLs and file paths.
     */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(str_replace(' ', '_', $username));
    }
}
