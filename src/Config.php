<?php
declare(strict_types=1);

namespace Minidon;
final class Config
{
    public string $API_KEY;
    public string $ACTOR_NAME;
    public string $DATA_DIR;
    public string $TEMPLATE_DIR;
    public string $ACTOR_URL;
    public string $POSTS_URL;

    public function __construct(
        string $API_KEY,
        string $ACTOR_NAME,
        string $DATA_DIR,
        string $TEMPLATE_DIR,
        string $ACTOR_URL = '',
        string $POSTS_URL = '',
    ) {
        $this->API_KEY = $API_KEY;
        $this->ACTOR_NAME = $ACTOR_NAME;
        $this->DATA_DIR = $DATA_DIR;
        $this->TEMPLATE_DIR = $TEMPLATE_DIR;

        // Als ACTOR_URL of POSTS_URL niet zijn meegegeven, genereer ze dynamisch
        $this->ACTOR_URL = $ACTOR_URL ?: $this->generateUrl('/actor');
        $this->POSTS_URL = $POSTS_URL ?: $this->generateUrl('/posts');

        $this->validateDirectories();
    }

    private function generateUrl(string $path): string
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