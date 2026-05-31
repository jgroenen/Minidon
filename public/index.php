<?php
declare(strict_types=1);

// Laad de classes handmatig
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Minidon.php';

// Laad .env handmatig (alleen voor API_KEY en ACTOR_NAME)
$env = [];
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
} elseif (file_exists(__DIR__ . '/../.env.example')) {
    $env = parse_ini_file(__DIR__ . '/../.env.example');
}

// Zet omgevingsvariabelen
foreach ($env as $key => $value) {
    putenv("$key=$value");
}

// Maak Config object (ACTOR_URL en POSTS_URL worden dynamisch gegenereerd)
$config = new Minidon\Config(
    API_KEY: getenv('MINIDON_API_KEY') ?: die("MINIDON_API_KEY is required in .env\n"),
    ACTOR_NAME: getenv('ACTOR_NAME') ?: 'Minidon Radio',
    DATA_DIR: __DIR__ . '/../data',
    TEMPLATE_DIR: __DIR__ . '/../templates',
    // ACTOR_URL en POSTS_URL worden automatisch gegenereerd uit $_SERVER
);

// Maak Minidon instance
$minidon = new Minidon\Minidon($config);

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle /posts/{id}
if (preg_match('#^/posts/([a-zA-Z0-9\-]+)$#', $path, $matches)) {
    $postId = $matches[1];
    $post = $minidon->getPostById($postId);
    if ($post) {
        header('Content-Type: application/activity+json');
        echo json_encode($post, JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        die("Post not found");
    }
    exit;
}

// Handle routes
switch ($path) {
    case '/':
        $lastPost = $minidon->getLastPost();
        header('Content-Type: text/html');
        echo $minidon->renderWithXSLT('post', [
            'actorName' => $config->ACTOR_NAME,
            'actorUrl' => $config->ACTOR_URL,
            'post' => $lastPost,
        ]);
        break;

    case '/post':
        if ($method !== 'POST') {
            http_response_code(405);
            die("Method not allowed");
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['content'])) {
            http_response_code(400);
            die("Bad request: 'content' is required");
        }
        $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($providedKey !== $config->API_KEY) {
            http_response_code(401);
            die("Unauthorized");
        }
        $post = $minidon->createPost($input['content']);
        header('Content-Type: application/activity+json');
        echo json_encode($post, JSON_PRETTY_PRINT);
        break;

    case '/actor':
        header('Content-Type: application/activity+json');
        echo json_encode($minidon->getActor(), JSON_PRETTY_PRINT);
        break;

    case '/inbox':
        if ($method !== 'POST') {
            http_response_code(405);
            die("Method not allowed");
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['type'])) {
            http_response_code(400);
            die("Bad request: 'type' is required");
        }
        if ($input['type'] === 'Follow' && !empty($input['actor'])) {
            $minidon->addSubscriber($input['actor']);
            error_log("New subscriber: " . $input['actor']);
            http_response_code(202);
            die("Accepted");
        }
        break;

    case '/posts':
        $lastPost = $minidon->getLastPost();
        header('Content-Type: application/activity+json');
        echo json_encode($lastPost !== null ? [$lastPost] : []);
        break;

    default:
        http_response_code(404);
        die("Not found");
}