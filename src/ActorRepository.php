<?php
declare(strict_types=1);

namespace Minidon;

/**
 * Manages multiple ActivityPub actors.
 * Loads actors from configuration and provides lookup by username.
 */
final class ActorRepository
{
    /** @var array<string, Actor> Map of normalized username to Actor */
    private array $actors = [];

    private string $dataDir;
    private string $domain;

    /**
     * @param array<array{username: string, name: string}> $actorsConfig
     */
    public function __construct(string $dataDir, string $domain, array $actorsConfig = [])
    {
        $this->dataDir = rtrim($dataDir, '/');
        $this->domain = rtrim($domain, '/');

        // If no actors provided, create a default one from environment
        if (empty($actorsConfig)) {
            $username = getenv('ACTOR_NAME') ?: 'minidon';
            $name = getenv('ACTOR_DISPLAY_NAME') ?: getenv('ACTOR_NAME') ?: 'Minidon';
            $actorsConfig = [['username' => $username, 'name' => $name]];
        }

        // Create Actor objects
        foreach ($actorsConfig as $config) {
            $username = $config['username'];
            $name = $config['name'] ?? $username;
            $normalized = Actor::normalizeUsername($username);
            
            $this->actors[$normalized] = new Actor(
                $username,
                $name,
                $this->domain
            );
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
}
