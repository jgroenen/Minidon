<?php
declare(strict_types=1);

namespace Minidon;
final class Config
{
    public string $DATA_DIR;
    public string $TEMPLATE_DIR;
    public string $ACTOR_URL;
    public string $POSTS_URL;
    public string $INSTANCE_NAME;
    public string $TAGLINE;

    public function __construct(
        string $INSTANCE_NAME,
        string $DATA_DIR,
        string $TEMPLATE_DIR,
        string $TAGLINE,
    ) {
        $this->INSTANCE_NAME = $INSTANCE_NAME;
        $this->DATA_DIR = $DATA_DIR;
        $this->TEMPLATE_DIR = $TEMPLATE_DIR;
        $this->TAGLINE = $TAGLINE;

        // Generate ACTOR_URL and POSTS_URL dynamically
        // For single-user ActivityPub: actor URL is /@{username} (URL-encoded)
        $username = urlencode(strtolower(str_replace(' ', '_', $INSTANCE_NAME)));
        $this->ACTOR_URL = $this->generateUrl('/@' . $username);
        $this->POSTS_URL = $this->generateUrl('/posts');

        $this->validateDirectories();
    }

    public function generateUrl(string $path): string
    {
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return sprintf('%s://%s%s', $scheme, $host, $path);
    }

    private function validateDirectories(): void
    {
        if (!is_dir($this->DATA_DIR)) {
            throw new \InvalidArgumentException(
                sprintf('DATA_DIR must be a valid directory: %s', $this->DATA_DIR)
            );
        }
        if (!is_dir($this->TEMPLATE_DIR)) {
            throw new \InvalidArgumentException(
                sprintf('TEMPLATE_DIR must be a valid directory: %s', $this->TEMPLATE_DIR)
            );
        }
    }
}