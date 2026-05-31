<?php
declare(strict_types=1);

namespace Minidon;

/**
 * Manages multiple ActivityPub actors.
 * Loads actors from CSV file and provides lookup by username.
 */
final class ActorRepository
{
    /** @var array<string, Actor> Map of normalized username to Actor */
    private array $actors = [];

    /** @var array<string, string> Map of normalized username to API key */
    private array $apiKeys = [];

    /** @var array<string, string> Map of normalized username to avatar URL */
    private array $avatars = [];

    private string $dataDir;
    private string $domain;

    /**
     * @param array<array{username: string, name: string, api_key?: string}> $actorsConfig
     */
    public function __construct(string $dataDir, string $domain, array $actorsConfig = [])
    {
        $this->dataDir = rtrim($dataDir, '/');
        $this->domain = rtrim($domain, '/');

        // Try to load actors from CSV file
        $csvPath = $this->dataDir . '/actors.csv';
        if (file_exists($csvPath)) {
            $this->loadActorsFromCsv($csvPath);
        } elseif (!empty($actorsConfig)) {
            // Load from config array
            $this->loadActorsFromConfig($actorsConfig);
        } else {
            // No actors configuration found
            throw new \RuntimeException(
                'No actors configured. Please create a data/actors.csv file or provide actorsConfig.'
            );
        }
    }

    /**
     * Load actors from CSV file.
     * CSV format: username,name,avatar,api_key
     */
    private function loadActorsFromCsv(string $csvPath): void
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open actors CSV file: $csvPath");
        }

        $isFirstLine = true;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            // Skip header line
            if ($isFirstLine) {
                $isFirstLine = false;
                continue;
            }

            if (count($row) >= 2) {
                $username = trim($row[0]);
                $name = trim($row[1]);
                $avatar = count($row) >= 3 ? trim($row[2]) : '';
                $apiKey = count($row) >= 4 ? trim($row[3]) : (count($row) >= 3 && $avatar === '' ? trim($row[2]) : '');
                $this->addActor($username, $name, $apiKey, $avatar);
            }
        }
        fclose($handle);
    }

    /**
     * Load actors from config array.
     * @param array<array{username: string, name: string, api_key?: string}> $actorsConfig
     */
    private function loadActorsFromConfig(array $actorsConfig): void
    {
        foreach ($actorsConfig as $config) {
            $username = $config['username'];
            $name = $config['name'] ?? $username;
            $apiKey = $config['api_key'] ?? '';
            $this->addActor($username, $name, $apiKey);
        }
    }

    /**
     * Add an actor to the repository.
     */
    private function addActor(string $username, string $name, string $apiKey, string $avatar = ''): void
    {
        $normalized = Actor::normalizeUsername($username);
        $this->actors[$normalized] = new Actor(
            $username,
            $name,
            $this->domain
        );
        if (!empty($apiKey)) {
            $this->apiKeys[$normalized] = $apiKey;
        }
        if (!empty($avatar)) {
            $this->avatars[$normalized] = $avatar;
        }
    }

    /**
     * Get an actor by normalized username.
     */
    public function getByUsername(string $username): ?Actor
    {
        $normalized = Actor::normalizeUsername($username);
        return $this->actors[$normalized] ?? null;
    }

    /**
     * Get all actors.
     * @return array<string, Actor>
     */
    public function getAll(): array
    {
        return $this->actors;
    }

    /**
     * Check if an actor exists.
     */
    public function hasUsername(string $username): bool
    {
        $normalized = Actor::normalizeUsername($username);
        return isset($this->actors[$normalized]);
    }

    /**
     * Get the first actor (for single-user backwards compatibility).
     */
    public function getFirst(): ?Actor
    {
        return $this->actors[array_key_first($this->actors)] ?? null;
    }

    /**
     * Get data directory for a specific actor.
     */
    public function getActorDataDir(string $username): string
    {
        $normalized = Actor::normalizeUsername($username);
        $actorDir = $this->dataDir . '/@' . $normalized;
        
        if (!file_exists($actorDir)) {
            mkdir($actorDir, 0755, true);
        }
        
        return $actorDir;
    }

    /**
     * Get the avatar URL for a specific actor.
     */
    public function getAvatar(string $username): ?string
    {
        $normalized = Actor::normalizeUsername($username);
        return $this->avatars[$normalized] ?? null;
    }

    /**
     * Get the API key for a specific actor.
     */
    public function getApiKey(string $username): ?string
    {
        $normalized = Actor::normalizeUsername($username);
        return $this->apiKeys[$normalized] ?? null;
    }

    /**
     * Get the API key for the first actor (for single-user backwards compatibility).
     */
    public function getFirstApiKey(): ?string
    {
        $firstActor = $this->getFirst();
        if ($firstActor === null) {
            return null;
        }
        return $this->getApiKey($firstActor->username);
    }

    /**
     * Validate an API key against an actor's API key.
     */
    public function validateApiKey(string $username, string $providedKey): bool
    {
        $apiKey = $this->getApiKey($username);
        return $apiKey !== null && hash_equals($apiKey, $providedKey);
    }
}
