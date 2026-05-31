<?php
declare(strict_types=1);

namespace Minidon;

/**
 * HTTP Signatures implementation for ActivityPub
 * Based on: https://w3c-ccg.github.io/activitypub/#http-signature
 * 
 * Supports Ed25519 signatures (the key type Minidon already uses)
 */
final class HttpSignature
{
    private const ALGORITHM = 'rsa-sha256';
    private const DEFAULT_HEADERS = '(request-target) date digest';

    /**
     * Create a Signature header for an outgoing request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The full URL being requested
     * @param string $privateKeyPEM The private key in PEM format
     * @param string $keyId The key ID (e.g., "https://example.com/@user#main-key")
     * @param array $extraHeaders Additional headers to include in the signature
     * @return string The Signature header value
     */
    public static function createSignature(
        string $method,
        string $url,
        string $privateKeyPEM,
        string $keyId,
        array $extraHeaders = []
    ): string {
        // Parse URL to get path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';
        $target = strtolower($method) . ' ' . $path . ($query ? '?' . $query : '');

        // Build the headers string
        $headersToSign = self::DEFAULT_HEADERS;
        if (!empty($extraHeaders)) {
            $headersToSign .= ' ' . implode(' ', $extraHeaders);
        }

        // Build the signature string
        $signatureString = self::buildSignatureString($headersToSign, [
            '(request-target)' => $target,
            'date' => date('D, d M Y H:i:s \G\M\T'),
            'digest' => self::createDigest(''), // Will be updated with actual body
        ] + $extraHeaders);

        // Sign with Ed25519
        $signature = self::sign($signatureString, $privateKeyPEM);

        // Build the Signature header
        $headerParts = [
            'keyId="' . $keyId . '",',
            'algorithm="' . self::ALGORITHM . '",',
            'signature="' . self::base64UrlEncode($signature) . '",',
            'headers="' . $headersToSign . '"',
        ];

        return implode('', $headerParts);
    }

    /**
     * Create a Signature header with body digest for POST requests
     *
     * @param string $method HTTP method
     * @param string $url The full URL
     * @param string $body The request body
     * @param string $privateKeyPEM The private key in PEM format
     * @param string $keyId The key ID
     * @return array Returns ['Signature' => header, 'Date' => date, 'Digest' => digest]
     */
    public static function createSignatureWithBody(
        string $method,
        string $url,
        string $body,
        string $privateKeyPEM,
        string $keyId
    ): array {
        $date = date('D, d M Y H:i:s \G\M\T');
        $digest = self::createDigest($body);

        $signature = self::createSignature($method, $url, $privateKeyPEM, $keyId);

        return [
            'Signature' => $signature,
            'Date' => $date,
            'Digest' => $digest,
        ];
    }

    /**
     * Verify an incoming HTTP Signature request
     *
     * @param array $server $_SERVER array
     * @param string $body The request body
     * @param callable $getPublicKey Callback to get public key by keyId: fn(string $keyId) => ?string
     * @return bool True if signature is valid
     */
    public static function verifyRequest(
        array $server,
        string $body,
        callable $getPublicKey
    ): bool {
        // Get the Signature header
        $signatureHeader = $server['HTTP_SIGNATURE'] ?? $server['HTTP_SIGNATURES'] ?? '';
        if (empty($signatureHeader)) {
            return false;
        }

        // Parse the Signature header
        $signatureData = self::parseSignatureHeader($signatureHeader);
        if ($signatureData === null) {
            return false;
        }

        // Check algorithm
        if (($signatureData['algorithm'] ?? '') !== self::ALGORITHM) {
            return false;
        }

        // Get the public key
        $publicKeyPEM = $getPublicKey($signatureData['keyId'] ?? '');
        if ($publicKeyPEM === null) {
            return false;
        }

        // Build the signature string
        $signatureString = self::buildSignatureString(
            $signatureData['headers'],
            self::getHeaderValues($server, $body, $signatureData['headers'])
        );

        // Verify the signature
        $providedSignature = self::base64UrlDecode($signatureData['signature']);
        return self::verify($signatureString, $providedSignature, $publicKeyPEM);
    }

    /**
     * Parse a Signature header into components
     */
    private static function parseSignatureHeader(string $header): ?array
    {
        $result = [
            'keyId' => null,
            'algorithm' => null,
            'signature' => null,
            'headers' => null,
        ];

        // Split by comma, but respect quotes
        $parts = self::splitHeader($header);

        foreach ($parts as $part) {
            if (preg_match('/^(\w+)="([^"]*)"$/', $part, $matches)) {
                $key = strtolower($matches[1]);
                $result[$key] = $matches[2];
            }
        }

        // All required parts must be present
        if ($result['keyId'] === null || $result['algorithm'] === null || 
            $result['signature'] === null || $result['headers'] === null) {
            return null;
        }

        return $result;
    }

    /**
     * Split a header string by commas, respecting quotes
     */
    private static function splitHeader(string $header): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($header);

        for ($i = 0; $i < $length; $i++) {
            $char = $header[$i];

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Build the signature string from headers
     */
    private static function buildSignatureString(string $headers, array $headerValues): string
    {
        $lines = [];
        foreach (explode(' ', $headers) as $headerName) {
            if (isset($headerValues[$headerName])) {
                $lines[] = $headerName . ': ' . $headerValues[$headerName];
            }
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Get header values for signing
     */
    private static function getHeaderValues(array $server, string $body, string $headers): array
    {
        $values = [];
        $headerNames = explode(' ', $headers);

        foreach ($headerNames as $headerName) {
            switch ($headerName) {
                case '(request-target)':
                    $method = $server['REQUEST_METHOD'] ?? 'POST';
                    $path = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
                    $query = parse_url($server['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';
                    $values[$headerName] = strtolower($method) . ' ' . $path . ($query ? '?' . $query : '');
                    break;

                case 'date':
                    $values[$headerName] = $server['HTTP_DATE'] ?? date('D, d M Y H:i:s \G\M\T');
                    break;

                case 'digest':
                    $values[$headerName] = self::createDigest($body);
                    break;

                default:
                    // Try to get from server headers
                    $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
                    if (isset($server[$headerKey])) {
                        $values[$headerName] = $server[$headerKey];
                    }
                    break;
            }
        }

        return $values;
    }

    /**
     * Create a SHA-256 digest header for the body
     */
    private static function createDigest(string $body): string
    {
        $hash = hash('sha256', $body, true);
        return 'SHA-256=' . self::base64UrlEncode($hash);
    }

    /**
     * Sign a string with RSA private key
     */
    private static function sign(string $data, string $privateKeyPEM): string
    {
        $key = openssl_pkey_get_private($privateKeyPEM);
        if ($key === false) {
            throw new \RuntimeException('Invalid private key');
        }

        $signature = '';
        $result = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($result === false) {
            throw new \RuntimeException('Failed to sign data: ' . openssl_error_string());
        }

        return $signature;
    }

    /**
     * Verify a signature with RSA public key
     */
    private static function verify(string $data, string $signature, string $publicKeyPEM): bool
    {
        $key = openssl_pkey_get_public($publicKeyPEM);
        if ($key === false) {
            return false;
        }

        return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Base64 URL-safe encoding
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
