<?php
declare(strict_types=1);

namespace Minidon;

use InvalidArgumentException;

final class Minidon
{
    private const POSTS_CSV = 'posts.csv';
    private const SUBS_CSV = 'subscribers.csv';

    // Properties voor Ed25519-sleutels
    private string $privateKeyPEM = '';
    private string $publicKeyPEM = '';

    public function __construct(
        private Config $config,
    ) {
        $this->setupDataDirectory();
        $this->generateKeys();
    }

    // --- Setup en Sleutelgeneratie ---

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

    /**
     * Genereert Ed25519-sleutels voor ActivityPub.
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
     * Maakt een nieuwe post aan en verzendt deze asynchroon naar alle subscribers.
     */
    public function createPost(string $content): array
    {
        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $post = [
            'id' => $this->config->POSTS_URL . '/' . time(),
            'content' => $sanitizedContent,
            'published' => date('c'),
            'author' => $this->config->ACTOR_URL,
        ];

        $this->appendToCSV($this->config->DATA_DIR . '/' . self::POSTS_CSV, $post);

        // Verzend asynchroon naar subscribers
        $this->sendPostToSubscribers($post);

        return $post;
    }

    /**
     * Haalt een post op aan de hand van de ID.
     */
    public function getPostById(string $postId): ?array
    {
        $file = $this->config->DATA_DIR . '/' . self::POSTS_CSV;
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

        // Lees alle regels tot we de post vinden
        while (($line = fgets($fp)) !== false) {
            $post = str_getcsv($line, ',', '"', '\\');
            if (isset($post[0]) && str_contains($post[0], $postId)) {
                fclose($fp);
                return array_combine($header, $post);
            }
        }

        fclose($fp);
        return null;
    }

    /**
     * Haalt de laatste post op (leest alleen de laatste regel).
     */
    public function getLastPost(): ?array
    {
        $file = $this->config->DATA_DIR . '/' . self::POSTS_CSV;
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

    // --- Subscriber Operaties ---

    /**
     * Voegt een subscriber toe.
     */
    public function addSubscriber(string $actorUrl): void
    {
        $this->appendToCSV(
            $this->config->DATA_DIR . '/' . self::SUBS_CSV,
            ['url' => $actorUrl]
        );
    }

    /**
     * Haalt alle subscribers op uit subscribers.csv.
     *
     * @return array<int, string> Lijst met subscriber URLs.
     */
    private function getSubscribers(): array
    {
        $file = $this->config->DATA_DIR . '/' . self::SUBS_CSV;
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
     * Verzendt een post naar alle subscribers (parallel met curl_multi_*).
     */
    private function sendPostToSubscribers(array $post): void
    {
        $subscribers = $this->getSubscribers();
        if (empty($subscribers)) {
            return;
        }

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->config->POSTS_URL . '/' . time() . '/activity',
            'type' => 'Create',
            'actor' => $this->config->ACTOR_URL,
            'published' => date('c'),
            'object' => $post,
        ];

        $inboxUrls = [];
        foreach ($subscribers as $subscriberUrl) {
            $inboxUrls[] = rtrim($subscriberUrl, '/') . '/inbox';
        }

        $this->sendActivitiesParallel($inboxUrls, $activity);
    }

    /**
     * Verzendt ActivityPub-activities parallel met curl_multi_*.
     */
    private function sendActivitiesParallel(array $inboxUrls, array $activity): void
    {
        $mh = curl_multi_init();
        $handles = [];

        $payload = json_encode($activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        foreach ($inboxUrls as $inboxUrl) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $inboxUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Geen respons nodig
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/activity+json',
                'Accept: application/activity+json',
                'User-Agent: Minidon/1.0',
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
     * Haalt de ActivityPub actor op.
     */
    public function getActor(): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->config->ACTOR_URL,
            'type' => 'Person',
            'name' => $this->config->ACTOR_NAME,
            'inbox' => $this->config->ACTOR_URL . '/inbox',
            'outbox' => $this->config->POSTS_URL,
            'publicKey' => [
                'id' => $this->config->ACTOR_URL . '#main-key',
                'owner' => $this->config->ACTOR_URL,
                'publicKeyPem' => $this->publicKeyPEM,
            ],
        ];
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

        return $proc->transformToXML($xmlDoc);
    }

    /**
     * Converteert een array naar XML.
     */
    private function arrayToXML(array $data, ?\DOMElement $parent = null, ?\DOMDocument $doc = null): string
    {
        if ($doc === null) {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $root = $doc->createElement('data');
            $doc->appendChild($root);
            $this->arrayToXML($data, $root, $doc);
            return $doc->saveXML();
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $node = $doc->createElement($key);
                $parent->appendChild($node);
                $this->arrayToXML($value, $node, $doc);
            } else {
                $node = $doc->createElement($key);
                $node->appendChild($doc->createTextNode((string)$value));
                $parent->appendChild($node);
            }
        }
        return '';
    }
}