<?php

namespace App\Helpers;

/**
 * Log NDJSON para sessao de debug (Cursor). Nao registrar segredos nem PII completo.
 */
class DebugAgentLog
{
    private const SESSION = 'ac6f82';

    private const REL_PATH = '.cursor/debug-ac6f82.log';

    public static function write(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . '/.cursor';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $root . '/' . self::REL_PATH;
        $line = json_encode([
            'sessionId' => self::SESSION,
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /** Metadados do destino sem expor numero/JID completo. */
    public static function maskRecipient(string $recipient): array
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return ['kind' => 'empty'];
        }
        if (str_contains($recipient, '@')) {
            $parts = explode('@', $recipient, 2);
            $local = $parts[0] ?? '';
            $domain = $parts[1] ?? '';

            return [
                'kind' => 'jid',
                'domain' => $domain,
                'local_len' => strlen($local),
            ];
        }
        $digits = preg_replace('/\D/', '', $recipient) ?? '';

        return [
            'kind' => 'digits',
            'len' => strlen($digits),
        ];
    }
}
