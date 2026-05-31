<?php
declare(strict_types=1);

namespace Minidon;

use InvalidArgumentException;

final class Minidon
{
    private const POSTS_CSV = 'posts.csv';
    private const SUBS_CSV = 'subscribers.csv';

    public function __construct(
        private Config $config,
        private ActorRepository $actorRepository,
    ) {
        $this->setupDataDirectory();
    }

    // --- Setup ---

    /**
     * Zorgt ervoor dat de data-directory bestaat.
     */
    private function setupDataDirectory(): void
    {
        $dataDir = $this->config->DATA_DIR;
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }

    // --- CSV Operaties ---

    /**
     * Voegt een rij toe aan een CSV-bestand (append).
     */
    private function appendToCSV(string $file, array $data): void
    {
        $fp = fopen($file, 'a');
        if ($fp === false) {
            throw new \RuntimeException("Could not open file for writing: $file");
        }

        if (filesize($file) === 0) {
            fputcsv($fp, array_keys($data), ',', '"', '\\');
        }
        fputcsv($fp, $data, ',', '"', '\\');
        fclose($fp);
    }

    // --- Post Operaties ---

    /**
     * Maakt een nieuwe post aan voor een specifieke actor en verzendt deze asynchroon naar alle subscribers.
     */
    public function createPost(string $username, string $content): array
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            throw new \InvalidArgumentException("Actor not found: $username");
        }

        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $postId = time() . '_' . substr(md5($username), 0, 8);
        
        // Create ActivityPub Note object
        $post = [
            'id' => $actor->getUrl() . '/' . $postId,
            'type' => 'Note',
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'content' => $sanitizedContent,
            'published' => date('c'),
            'attributedTo' => $actor->getUrl(),
            'to' => 'https://www.w3.org/ns/activitystreams#Public',
        ];

        $actorDataDir = $this->actorRepository->getActorDataDir($username);
        $this->appendToCSV($actorDataDir . '/' . self::POSTS_CSV, $post);

        // Verzend asynchroon naar subscribers
        $this->sendPostToSubscribers($actor, $post);

        return $post;
    }

    /**
     * Haalt een post op aan de hand van de ID (zoekt in alle actors).
     */
    public function getPostById(string $postId): ?array
    {
        foreach ($this->actorRepository->getAll() as $actor) {
            $actorDataDir = $this->actorRepository->getActorDataDir($actor->username);
            $file = $actorDataDir . '/' . self::POSTS_CSV;
            
            if (!file_exists($file)) {
                continue;
            }

            $fp = fopen($file, 'r');
            if ($fp === false) {
                continue;
            }

            // Lees header
            $header = fgetcsv($fp, 0, ',', '"', '\\');
            if ($header === false) {
                fclose($fp);
                continue;
            }

            // Lees alle regels tot we de post vinden
            while (($line = fgets($fp)) !== false) {
                $post = str_getcsv($line, ',', '"', '\\');
                if (isset($post[0]) && $post[0] === $postId) {
                    fclose($fp);
                    return array_combine($header, $post);
                }
                // For backwards compatibility: check if postId is at the end of the URL
                // e.g., postId = "12345678_abcdef12" and post[0] = "http://domain/@user/12345678_abcdef12"
                if (isset($post[0]) && str_ends_with($post[0], '/' . $postId)) {
                    fclose($fp);
                    return array_combine($header, $post);
                }
            }

            fclose($fp);
        }
        
        return null;
    }

    /**
     * Haalt de laatste post op van een specifieke actor.
     */
    public function getLastPost(string $username): ?array
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            return null;
        }

        $actorDataDir = $this->actorRepository->getActorDataDir($username);
        $file = $actorDataDir . '/' . self::POSTS_CSV;
        
        if (!file_exists($file)) {
            return null;
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            throw new \RuntimeException("Could not open file: $file");
        }

        // Lees header
        $header = fgetcsv($fp, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($fp);
            return null;
        }

        // Lees alle regels en neem de laatste
        $lastLine = '';
        while (($line = fgets($fp)) !== false) {
            if (trim($line) !== '') {
                $lastLine = $line;
            }
        }
        fclose($fp);

        if (empty($lastLine)) {
            return null;
        }

        $post = str_getcsv($lastLine, ',', '"', '\\');
        return array_combine($header, $post);
    }

    /**
     * Haalt de laatste post op van de eerste actor (backwards compatibility).
     */
    public function getLastPostAny(): ?array
    {
        $firstActor = $this->actorRepository->getFirst();
        if ($firstActor === null) {
            return null;
        }
        return $this->getLastPost($firstActor->username);
    }

    /**
     * Haalt alle posts op van een specifieke actor (gepagineerd).
     * @param string $username De username van de actor
     * @param int $page Pagina nummer (1-based)
     * @param int $perPage Aantal posts per pagina
     * @return array{posts: array, total: int, page: int, perPage: int}
     */
    public function getAllPosts(string $username, int $page = 1, int $perPage = 20): array
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            return ['posts' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $actorDataDir = $this->actorRepository->getActorDataDir($username);
        $file = $actorDataDir . '/' . self::POSTS_CSV;
        
        if (!file_exists($file)) {
            return ['posts' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            throw new \RuntimeException("Could not open file: $file");
        }

        // Lees header
        $header = fgetcsv($fp, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($fp);
            return ['posts' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        // Lees alle posts
        $allPosts = [];
        while (($line = fgets($fp)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            $post = str_getcsv($line, ',', '"', '\\');
            if (count($post) === count($header)) {
                $allPosts[] = array_combine($header, $post);
            }
        }
        fclose($fp);

        $total = count($allPosts);
        $totalPages = (int)ceil($total / $perPage);
        
        // Sorteer op published (nieuigste eerst) en pagineer
        usort($allPosts, function($a, $b) {
            return strnatcasecmp($b['published'] ?? '', $a['published'] ?? '');
        });

        $offset = ($page - 1) * $perPage;
        $posts = array_slice($allPosts, $offset, $perPage);

        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    // --- Subscriber Operaties ---

    /**
     * Voegt een subscriber toe voor een specifieke actor.
     */
    public function addSubscriber(string $username, string $subscriberUrl): void
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            throw new \InvalidArgumentException("Actor not found: $username");
        }

        $actorDataDir = $this->actorRepository->getActorDataDir($username);
        $this->appendToCSV(
            $actorDataDir . '/' . self::SUBS_CSV,
            ['url' => $subscriberUrl]
        );
    }

    /**
     * Haalt alle subscribers op voor een specifieke actor.
     *
     * @return array<int, string> Lijst met subscriber URLs.
     */
    private function getSubscribers(string $username): array
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            return [];
        }

        $actorDataDir = $this->actorRepository->getActorDataDir($username);
        $file = $actorDataDir . '/' . self::SUBS_CSV;
        
        if (!file_exists($file)) {
            return [];
        }

        $subscribers = [];
        $fp = fopen($file, 'r');
        if ($fp === false) {
            return [];
        }

        // Sla header over
        fgetcsv($fp, 0, ',', '"', '\\');

        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
            if (!empty($row[0])) {
                $subscribers[] = $row[0];
            }
        }
        fclose($fp);

        return $subscribers;
    }

    /**
     * Verzendt een post naar alle subscribers van een actor (parallel met curl_multi_*).
     */
    private function sendPostToSubscribers(Actor $actor, array $post): void
    {
        $subscribers = $this->getSubscribers($actor->username);
        if (empty($subscribers)) {
            return;
        }

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getOutboxUrl() . '/' . time() . '/activity',
            'type' => 'Create',
            'actor' => $actor->getUrl(),
            'published' => date('c'),
            'object' => $post,
        ];

        $inboxUrls = [];
        foreach ($subscribers as $subscriberUrl) {
            $inboxUrls[] = rtrim($subscriberUrl, '/') . '/inbox';
        }

        $this->sendActivitiesParallel($actor, $inboxUrls, $activity);
    }

    /**
     * Verzendt een single ActivityPub activity naar een inbox.
     */
    public function sendActivity(string $inboxUrl, array $activity): void
    {
        $actor = $this->actorRepository->getFirst();
        if ($actor === null) {
            return; // No actor to send from
        }
        $this->sendActivitiesParallel($actor, [$inboxUrl], $activity);
    }

    /**
     * Verzendt ActivityPub-activities parallel met curl_multi_*.
     */
    private function sendActivitiesParallel(Actor $actor, array $inboxUrls, array $activity): void
    {
        $mh = curl_multi_init();
        $handles = [];

        $payload = json_encode($activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        // Create HTTP Signature headers
        $date = date('D, d M Y H:i:s \G\M\T');
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $payload, true));
        $digest = rtrim(strtr($digest, '+/', '-_'), '=');
        
        $privateKeyPem = $actor->getPrivateKeyPem();
        $keyId = $actor->getPublicKeyId();

        foreach ($inboxUrls as $inboxUrl) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $inboxUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Geen respons nodig
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            
            // Build signature for this specific request
            $target = 'post ' . parse_url($inboxUrl, PHP_URL_PATH) . (parse_url($inboxUrl, PHP_URL_QUERY) ? '?' . parse_url($inboxUrl, PHP_URL_QUERY) : '');
            $signatureString = "(request-target): $target\n" . "date: $date\n" . "digest: $digest\n";
            
            // Sign with RSA-SHA256
            $signature = '';
            $keyResource = openssl_pkey_get_private($privateKeyPem);
            if ($keyResource !== false) {
                openssl_sign($signatureString, $signature, $keyResource, OPENSSL_ALGO_SHA256);
            }
            
            $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/activity+json',
                'Accept: application/activity+json',
                'User-Agent: Minidon/1.0',
                "Date: $date",
                "Digest: $digest",
                "Signature: keyId=\"$keyId\",algorithm=\"rsa-sha256\",signature=\"$signatureEncoded\",headers=\"(request-target) date digest\"",
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);

            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }

        // Voer alle requests parallel uit
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($info = curl_multi_info_read($mh)) {
                if ($info['result'] !== CURLE_OK) {
                    error_log(sprintf(
                        "cURL error for %s: %s",
                        $inboxUrls[array_search($info['handle'], $handles, true) ?? 0],
                        curl_error($info['handle'])
                    ));
                }
            }
            if ($running) {
                curl_multi_select($mh);
            }
        } while ($running > 0);

        // Sluit alle handles
        foreach ($handles as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    // --- ActivityPub Actor ---

    /**
     * Haalt de ActivityPub actor op voor een specifieke actor.
     */
    public function getActor(string $username): ?array
    {
        $actor = $this->actorRepository->getByUsername($username);
        if ($actor === null) {
            return null;
        }
        return $actor->toArray();
    }

    /**
     * Haalt de eerste actor op (backwards compatibility).
     */
    public function getActorFirst(): ?array
    {
        $firstActor = $this->actorRepository->getFirst();
        if ($firstActor === null) {
            return null;
        }
        return $firstActor->toArray();
    }

    // --- XSLT Rendering ---

    /**
     * Renderd data met een XSLT-template.
     */
    public function renderWithXSLT(string $templateName, array $data): string
    {
        $xml = $this->arrayToXML($data);
        $xslPath = $this->config->TEMPLATE_DIR . '/' . $templateName . '.xsl';

        if (!file_exists($xslPath)) {
            throw new InvalidArgumentException("XSLT template not found: $xslPath");
        }

        $xsl = new \DOMDocument();
        $xsl->load($xslPath);

        $proc = new \XSLTProcessor();
        if (!$proc->importStylesheet($xsl)) {
            throw new \RuntimeException("Failed to load XSLT stylesheet: $templateName");
        }

        $xmlDoc = new \DOMDocument();
        $xmlDoc->loadXML($xml);

        $result = $proc->transformToXML($xmlDoc);
        if ($result === false) {
            throw new \RuntimeException("XSLT transformation failed");
        }
        return $result;
    }

    /**
     * Converteert een array naar XML.
     */
    private function arrayToXML(array $data, ?\DOMElement $parent = null, ?\DOMDocument $doc = null, string $listElementName = 'item'): string
    {
        if ($doc === null) {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $root = $doc->createElement('data');
            $doc->appendChild($root);
            $this->arrayToXML($data, $root, $doc);
            return $doc->saveXML();
        }

        foreach ($data as $key => $value) {
            // Handle numeric keys (arrays/lists) - use listElementName
            if (is_int($key)) {
                $key = $listElementName;
            }
            
            if (is_array($value)) {
                $node = $doc->createElement((string)$key);
                $parent->appendChild($node);
                // For nested arrays, pass the original key as element name for children
                $this->arrayToXML($value, $node, $doc, $listElementName);
            } else {
                $node = $doc->createElement((string)$key);
                $node->appendChild($doc->createTextNode((string)$value));
                $parent->appendChild($node);
            }
        }
        return '';
    }
}