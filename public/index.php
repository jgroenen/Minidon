<?php
declare(strict_types=1);

// Laad de classes handmatig
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Actor.php';
require_once __DIR__ . '/../src/ActorRepository.php';
require_once __DIR__ . '/../src/Minidon.php';
require_once __DIR__ . '/../src/HttpSignature.php';

// Laad .env handmatig
if (!file_exists(__DIR__ . '/../.env')) {
    http_response_code(500);
    die("Missing .env file. Please copy .env.example to .env and configure your settings.");
}

$env = parse_ini_file(__DIR__ . '/../.env');

// Zet omgevingsvariabelen
foreach ($env as $key => $value) {
    putenv("$key=$value");
}

// Valideer benodigde configuratie
$requiredEnvVars = ['INSTANCE_NAME', 'TAGLINE', 'POSTS_PER_PAGE', 'DATA_DIR', 'TEMPLATE_DIR'];
foreach ($requiredEnvVars as $var) {
    if (getenv($var) === false) {
        http_response_code(500);
        die("Missing required configuration: $var. Please add it to your .env file.");
    }
}

// Maak Config object (ACTOR_URL en POSTS_URL worden dynamisch gegenereerd)
$config = new Minidon\Config(
    INSTANCE_NAME: getenv('INSTANCE_NAME'),
    DATA_DIR: __DIR__ . '/../' . getenv('DATA_DIR'),
    TEMPLATE_DIR: __DIR__ . '/../' . getenv('TEMPLATE_DIR'),
    TAGLINE: getenv('TAGLINE'),
    // ACTOR_URL en POSTS_URL worden automatisch gegenereerd uit $_SERVER
);

// Generate domain for actors
$scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$domain = $scheme . '://' . $host;

// Maak ActorRepository (laadt actors uit ACTOR_NAME env var voor single-user)
$actorRepository = new Minidon\ActorRepository(
    $config->DATA_DIR,
    $domain
);

// Maak Minidon instance
$minidon = new Minidon\Minidon($config, $actorRepository);

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /public prefix if present (for development setups where public is in the URL)
if (strpos($path, '/public') === 0) {
    $path = substr($path, 7); // Remove '/public'
}
if ($path === '') {
    $path = '/';
}

// Serve static files directly (CSS, JS, images, etc.)
// Check if the request is for a static file that exists in the public directory
if ($path !== '/' && $path !== '' && !str_starts_with($path, '/@') && !str_starts_with($path, '/posts') && !str_starts_with($path, '/post')) {
    $filePath = __DIR__ . $path;
    if (file_exists($filePath) && is_file($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentType = 'text/plain';
        if ($extension === 'css') {
            $contentType = 'text/css';
        } elseif ($extension === 'js') {
            $contentType = 'application/javascript';
        } elseif ($extension === 'png') {
            $contentType = 'image/png';
        } elseif ($extension === 'jpg' || $extension === 'jpeg') {
            $contentType = 'image/jpeg';
        } elseif ($extension === 'gif') {
            $contentType = 'image/gif';
        } elseif ($extension === 'svg') {
            $contentType = 'image/svg+xml';
        } elseif ($extension === 'json') {
            $contentType = 'application/json';
        } elseif ($extension === 'html' || $extension === 'htm') {
            $contentType = 'text/html';
        } elseif ($extension === 'txt') {
            $contentType = 'text/plain';
        }
        header("Content-Type: $contentType");
        readfile($filePath);
        exit;
    }
}

// Handle NodeInfo discovery
if ($path === '/.well-known/nodeinfo') {
    header('Content-Type: application/json');
    
    // For multi-user: use the instance's base URL for nodeinfo
    // This links to /nodeinfo/2.0 which handles all actors
    $nodeInfoUrl = $config->generateUrl('/nodeinfo/2.0');
    
    echo json_encode([
        'links' => [
            [
                'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                'href' => $nodeInfoUrl,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle NodeInfo 2.0 (both /nodeinfo/2.0 and /@username/nodeinfo/2.0)
if ($path === '/nodeinfo/2.0' || preg_match('#^/@[a-zA-Z0-9_-]+/nodeinfo/2\.0$#', $path)) {
    header('Content-Type: application/json');
    
    // Count total actors and posts
    $allActors = $actorRepository->getAll();
    $totalUsers = count($allActors);
    $totalPosts = 0;
    foreach ($allActors as $actor) {
        $actorDataDir = $actorRepository->getActorDataDir($actor->username);
        $postsFile = $actorDataDir . '/posts.csv';
        if (file_exists($postsFile)) {
            $totalPosts += max(0, count(file($postsFile)) - 1); // -1 for header
        }
    }
    
    echo json_encode([
        'version' => '2.0',
        'software' => [
            'name' => 'Minidon',
            'version' => '1.0',
        ],
        'protocols' => ['activitypub'],
        'services' => [
            'inbound' => [],
            'outbound' => [],
        ],
        'openRegistrations' => false,
        'usage' => [
            'users' => [
                'total' => $totalUsers,
            ],
            'localPosts' => $totalPosts,
        ],
        'metadata' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle WebFinger for ActivityPub federation
if ($path === '/.well-known/webfinger') {
    $resource = $_GET['resource'] ?? '';
    $resource = str_replace('acct:', '', $resource);
    
    $parts = explode('@', $resource);
    if (count($parts) === 2) {
        $username = $parts[0];
        $domain = $parts[1];
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Check if domain matches
        if ($domain === $host || $domain === 'localhost') {
            // Normalize the username for lookup
            $normalizedUsername = Actor::normalizeUsername($username);
            
            // Check if actor exists
            if ($actorRepository->hasUsername($normalizedUsername)) {
                $actor = $actorRepository->getByUsername($normalizedUsername);
                
                header('Content-Type: application/jrd+json');
                echo json_encode([
                    'subject' => 'acct:' . $username . '@' . $domain,
                    'aliases' => [$actor->getUrl()],
                    'links' => [
                        [
                            'rel' => 'self',
                            'type' => 'application/activity+json',
                            'href' => $actor->getUrl(),
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
    }
    
    http_response_code(404);
    header('Content-Type: application/jrd+json');
    echo json_encode(['error' => 'Actor not found']);
    exit;
}

// Helper function to extract username from @-prefixed paths
function extractUsername(string $path): ?string {
    if (preg_match('#^/@([a-zA-Z0-9_-]+)#', $path, $matches)) {
        return urldecode($matches[1]);
    }
    return null;
}

// Check if client wants JSON (ActivityPub) or HTML (browser)
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = strpos($acceptHeader, 'application/activity+json') !== false ||
            strpos($acceptHeader, 'application/json') !== false;

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

// Handle /@username routes first (most specific)
$username = extractUsername($path);
if ($username !== null && $actorRepository->hasUsername($username)) {
    // Remove /@username from path to get subpath
    $subpath = substr($path, strlen('/@' . $username));
    if ($subpath === '' || $subpath === '/') {
        // /@username - Actor profile with all posts
        if ($wantsJson) {
            header('Content-Type: application/activity+json');
            echo json_encode($minidon->getActor($username), JSON_PRETTY_PRINT);
        } else {
            // Parse page number from query string
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = (int)getenv('POSTS_PER_PAGE');
            
            $postsData = $minidon->getAllPosts($username, $page, $perPage);
            $actor = $actorRepository->getByUsername($username);
            
            // Build pagination data
            $pagination = [];
            if ($postsData['total'] > $perPage) {
                $totalPages = $postsData['totalPages'];
                
                // Previous page
                if ($page > 1) {
                    $pagination['prevPage'] = '/@' . $username . '?page=' . ($page - 1);
                }
                
                // Next page
                if ($page < $totalPages) {
                    $pagination['nextPage'] = '/@' . $username . '?page=' . ($page + 1);
                }
                
                // Page numbers
                $pages = [];
                $maxVisiblePages = 5;
                $half = (int)floor($maxVisiblePages / 2);
                $start = max(1, $page - $half);
                $end = min($totalPages, $start + $maxVisiblePages - 1);
                
                if ($end - $start + 1 < $maxVisiblePages) {
                    $start = max(1, $end - $maxVisiblePages + 1);
                }
                
                for ($i = $start; $i <= $end; $i++) {
                    $pages[] = [
                        'number' => $i,
                        'url' => '/@' . $username . '?page=' . $i,
                        'current' => $i === $page ? '1' : '0'
                    ];
                }
                $pagination['pages'] = $pages;
            }
            
            // Show HTML profile page with all posts
            header('Content-Type: text/html');
            $avatarUrl = $actorRepository->getAvatar($actor->username);
            echo $minidon->renderWithXSLT('actor', [
                'actorName' => $actor->name,
                'username' => $actor->username,
                'avatar' => $avatarUrl,
                'posts' => $postsData['posts'],
                'pagination' => !empty($pagination) ? $pagination : null,
                'instanceName' => $config->INSTANCE_NAME,
                'tagline' => $config->TAGLINE,
            ]);
        }
        exit;
    } elseif ($subpath === '/inbox') {
        // /@username/inbox - Only accepts POST with JSON and valid HTTP Signature
        if ($method !== 'POST') {
            http_response_code(405);
            die("Method not allowed");
        }
        
        // Read the raw body for signature verification
        $body = file_get_contents('php://input');
        
        // Verify HTTP Signature
        $isValidSignature = Minidon\HttpSignature::verifyRequest(
            $_SERVER,
            $body,
            function(string $keyId) use ($actorRepository): ?string {
                // Extract username from keyId (e.g., "https://example.com/@user#main-key")
                if (preg_match('#/@([a-zA-Z0-9_-]+)#', $keyId, $matches)) {
                    $actorUsername = urldecode($matches[1]);
                    $actor = $actorRepository->getByUsername($actorUsername);
                    if ($actor !== null) {
                        return $actor->getPublicKeyPem();
                    }
                }
                return null;
            }
        );
        
        if (!$isValidSignature) {
            http_response_code(401);
            die("Unauthorized: Invalid or missing HTTP Signature");
        }
        
        $input = json_decode($body, true);
        if (empty($input['type'])) {
            http_response_code(400);
            die("Bad request: 'type' is required");
        }
        if ($input['type'] === 'Follow' && !empty($input['actor'])) {
            $minidon->addSubscriber($username, $input['actor']);
            error_log("New subscriber for $username: " . $input['actor']);
            
            // Send Accept activity back to the follower (ActivityPub spec)
            $actor = $actorRepository->getByUsername($username);
            if ($actor !== null) {
                // Extract follower's inbox URL from their actor URL
                $followerInbox = $input['actor'] . '/inbox';
                
                $acceptActivity = [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $actor->getUrl() . '/' . time() . '/accept',
                    'type' => 'Accept',
                    'actor' => $actor->getUrl(),
                    'published' => date('c'),
                    'object' => [
                        '@context' => 'https://www.w3.org/ns/activitystreams',
                        'id' => $input['id'] ?? $actor->getUrl() . '/' . time() . '/follow',
                        'type' => 'Follow',
                        'actor' => $input['actor'],
                        'object' => $actor->getUrl(),
                    ],
                ];
                
                // Send Accept asynchronously
                $minidon->sendActivity($followerInbox, $acceptActivity);
            }
            
            http_response_code(202);
            die("Accepted");
        }
        http_response_code(400);
        die("Bad request: unsupported activity type");
        exit;
    } elseif ($subpath === '/outbox') {
        // /@username/outbox - OrderedCollection of posts (ActivityPub spec)
        $actor = $actorRepository->getByUsername($username);
        if ($actor === null) {
            http_response_code(404);
            die("Actor not found");
        }
        
        // Get all posts for this actor
        $postsData = $minidon->getAllPosts($username, 1, 1000); // Get all for now
        
        if ($wantsJson) {
            header('Content-Type: application/activity+json');
            
            // Build OrderedCollection with Create activities
            $items = [];
            foreach ($postsData['posts'] as $post) {
                $items[] = [
                    'type' => 'Create',
                    'id' => $post['id'] . '/activity',
                    'actor' => $actor->getUrl(),
                    'published' => $post['published'] ?? date('c'),
                    'object' => $post,
                ];
            }
            
            $outbox = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $actor->getOutboxUrl(),
                'type' => 'OrderedCollection',
                'totalItems' => $postsData['total'],
                'first' => $actor->getOutboxUrl() . '?page=1',
                'last' => $actor->getOutboxUrl() . '?page=1',
                'items' => $items,
            ];
            
            echo json_encode($outbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            // Show HTML posts page
            header('Content-Type: text/html');
            $lastPost = $postsData['posts'][0] ?? null;
            echo $minidon->renderWithXSLT('post', [
                'actorName' => $actor->name,
                'actorUrl' => $actor->getUrl(),
                'post' => $lastPost,
                'instanceName' => $config->INSTANCE_NAME,
                'tagline' => $config->TAGLINE,
            ]);
        }
        exit;
    }
    // Unknown /@username/subpath
    http_response_code(404);
    die("Not found");
}

// Handle routes
switch ($path) {
    case '/':
        // Build actors data for homepage
        $actorsData = [];
        foreach ($actorRepository->getAll() as $actor) {
            $avatarUrl = $actorRepository->getAvatar($actor->username);
            $actorsData[] = [
                'username' => $actor->username,
                'name' => $actor->name,
                'url' => $actor->getUrl(),
                'avatar' => $avatarUrl,
                'lastPost' => $minidon->getLastPost($actor->username),
            ];
        }
        
        header('Content-Type: text/html');
        echo $minidon->renderWithXSLT('home', [
            'actors' => $actorsData,
            'instanceName' => $config->INSTANCE_NAME,
            'tagline' => $config->TAGLINE,
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
        $firstApiKey = $actorRepository->getFirstApiKey();
        if ($firstApiKey === null || !hash_equals($firstApiKey, $providedKey)) {
            http_response_code(401);
            die("Unauthorized");
        }
        // For single-user, post to the first actor
        $firstActor = $actorRepository->getFirst();
        if ($firstActor === null) {
            http_response_code(500);
            die("No actors configured");
        }
        $post = $minidon->createPost($firstActor->username, $input['content']);
        header('Content-Type: application/activity+json');
        echo json_encode($post, JSON_PRETTY_PRINT);
        break;

    case '/posts':
        $firstActor = $actorRepository->getFirst();
        if ($firstActor === null) {
            http_response_code(500);
            die("No actors configured");
        }
        $lastPost = $minidon->getLastPost($firstActor->username);
        if ($wantsJson) {
            header('Content-Type: application/activity+json');
            echo json_encode($lastPost !== null ? [$lastPost] : []);
        } else {
            // For HTML, redirect to homepage
            header('Location: /');
        }
        exit;

    default:
        http_response_code(404);
        die("Not found");
}