<?php

namespace Kei\Lwphp\Security;

/**
 * InputSanitizer — multi-vector input defence.
 *
 * Protects against:
 *   XSS       — strips <script>, event attributes, javascript: URIs
 *   SQLi      — detects UNION/DROP/EXEC/xp_ patterns → SecurityException
 *   XXE       — rejects XML bodies with DOCTYPE declarations
 *   PATH TRAV — rejects ../ sequences
 *   NULL BYTE — rejects \x00 anywhere in input
 *   PHP INJ   — rejects PHP serialized strings (a:, O:, s: prefix)
 *   BIG FIELD — rejects single fields > MAX_FIELD_BYTES
 *
 * @throws SecurityException when an injection attempt is detected
 */
class InputSanitizer
{
    private const MAX_FIELD_BYTES = 65536; // 64 KB per field

    // Patterns that indicate SQL injection
    private const SQLI_PATTERNS = [
        '/\bunion\s+(all\s+)?select\b/i',
        '/\bdrop\s+(table|database|schema|index)\b/i',
        '/\bexec\s*\(/i',
        '/\bexecute\s*\(/i',
        '/\bxp_\w+/i',
        '/\binsert\s+into\b.*\bvalues\b/i',
        '/\bdelete\s+from\b/i',
        '/\btruncate\s+table\b/i',
        '/\balter\s+table\b/i',
        '/;\s*(drop|delete|truncate|alter|create|insert|update)\b/i',
        '/\bsleep\s*\(\s*\d+\s*\)/i',
        '/\bwaitfor\s+delay\b/i',
        '/0x[0-9a-f]{8,}/i',
    ];

    // Patterns that indicate XSS attempts — applied with preg_replace to strip
    private const XSS_STRIP_PATTERNS = [
        '/<script[\s\S]*?>[\s\S]*?<\/script>/i',   // <script>...</script>
        '/<script[^>]*>/i',                          // <script ...>
        '/javascript\s*:/i',                          // javascript:
        '/vbscript\s*:/i',                            // vbscript:
        '/data\s*:\s*text\/html/i',                   // data:text/html
        '/<!--[\s\S]*?-->/i',                         // HTML comments
    ];

    // Patterns for on* event handlers — matched separately to avoid quote escaping issues
    private const XSS_HANDLER_PATTERNS = [
        '/on[a-z]+\s*=\s*"[^"]*"/i',    // onerror="evil()"
        "/on[a-z]+\\s*=\\s*'[^']*'/i",  // onerror='evil()'
        '/on[a-z]+\s*=\s*[^"\'\s>]+/i', // onerror=evil() (no quotes)
    ];

    /**
     * Sanitize a parsed body array recursively.
     *
     * @param  array $data Raw input
     * @return array Sanitized copy
     * @throws SecurityException
     */
    public function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $cleanKey = $this->sanitizeString((string) $key, 'key');

            if (is_array($value)) {
                $out[$cleanKey] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $out[$cleanKey] = $this->sanitizeString($value, $cleanKey);
            } else {
                $out[$cleanKey] = $value; // int, float, bool, null — pass through
            }
        }
        return $out;
    }

    /**
     * Check raw body string for XXE DOCTYPE / ENTITY declarations.
     *
     * @throws SecurityException
     */
    public function checkXxe(string $rawBody): void
    {
        if (preg_match('/<!DOCTYPE\s+/i', $rawBody) || preg_match('/<!ENTITY\s+/i', $rawBody)) {
            throw new SecurityException('XXE attack detected: DOCTYPE/ENTITY declarations are forbidden.', 400);
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function sanitizeString(string $value, string $fieldName): string
    {
        // 1. Field size limit
        if (strlen($value) > self::MAX_FIELD_BYTES) {
            throw new SecurityException(
                "Field '{$fieldName}' exceeds maximum size (" . self::MAX_FIELD_BYTES . " bytes).",
                413
            );
        }

        // 2. Null byte injection
        if (str_contains($value, "\x00")) {
            throw new SecurityException("Null byte detected in field '{$fieldName}'.", 400);
        }

        // 3. Path traversal
        if (str_contains($value, '../') || str_contains($value, '..\\')) {
            throw new SecurityException("Path traversal detected in field '{$fieldName}'.", 400);
        }

        // 4. PHP object injection (e.g. O:8:"stdClass":0:{})
        if (preg_match('/^[aOCsibd]:\d+:/i', ltrim($value))) {
            throw new SecurityException("PHP serialized data detected in field '{$fieldName}'.", 400);
        }

        // 5. SQLi pattern detection
        foreach (self::SQLI_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new SecurityException(
                    "Potential SQL injection detected in field '{$fieldName}'.",
                    400
                );
            }
        }

        // 6a. XSS: strip block-level dangerous HTML
        $clean = $value;
        foreach (self::XSS_STRIP_PATTERNS as $pattern) {
            $result = preg_replace($pattern, '', $clean);
            if ($result !== null) {
                $clean = $result;
            }
        }

        // 6b. XSS: strip on* event handlers
        foreach (self::XSS_HANDLER_PATTERNS as $pattern) {
            $result = preg_replace($pattern, '', $clean);
            if ($result !== null) {
                $clean = $result;
            }
        }

        // 7. Encode remaining < > to neutralize any remaining HTML
        // We keep the raw text form so JSON output is not double-encoded
        $clean = str_replace(['<', '>'], ['&lt;', '&gt;'], $clean);

        return $clean;
    }
}
