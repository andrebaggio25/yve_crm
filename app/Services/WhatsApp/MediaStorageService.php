<?php

namespace App\Services\WhatsApp;

use App\Core\App;

/**
 * Persiste bytes de midia WhatsApp em storage/media/whatsapp/...
 */
class MediaStorageService
{
    private const ALLOWED_PREFIXES = [
        'image/',
        'video/',
        'audio/',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument',
        'application/vnd.ms-excel',
        'text/plain',
    ];

    /**
     * @return array{relative_path: string, absolute_path: string, size: int, ext: string}
     */
    public static function store(int $tenantId, string $direction, string $bytes, string $mime, ?string $originalName = null): array
    {
        $mime = strtolower(trim($mime));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        if (!self::mimeAllowed($mime)) {
            throw new \InvalidArgumentException('Tipo de arquivo nao permitido: ' . $mime);
        }

        $ext = self::extensionFromMime($mime);
        if ($originalName !== null && $originalName !== '') {
            $base = pathinfo($originalName, PATHINFO_EXTENSION);
            if (is_string($base) && $base !== '' && preg_match('/^[a-z0-9]{1,8}$/i', $base)) {
                $ext = strtolower($base);
            }
        }

        $uuid = bin2hex(random_bytes(16));
        $y = date('Y');
        $m = date('m');
        $dirRel = "media/whatsapp/{$tenantId}/{$direction}/{$y}/{$m}";
        $dirAbs = App::storagePath($dirRel);
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            throw new \RuntimeException('Nao foi possivel criar diretorio de midia');
        }

        $fileRel = "{$dirRel}/{$uuid}.{$ext}";
        $fileAbs = App::storagePath($fileRel);
        $written = file_put_contents($fileAbs, $bytes);
        if ($written === false) {
            throw new \RuntimeException('Falha ao gravar arquivo de midia');
        }

        return [
            'relative_path' => $fileRel,
            'absolute_path' => $fileAbs,
            'size' => (int) $written,
            'ext' => $ext,
        ];
    }

    public static function mimeAllowed(string $mime): bool
    {
        foreach (self::ALLOWED_PREFIXES as $p) {
            if (str_starts_with($mime, $p)) {
                return true;
            }
        }

        return false;
    }

    public static function extensionFromMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/mp3' => 'mp3',
            'audio/webm' => 'webm',
            'audio/aac' => 'aac',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
        ];

        return $map[$mime] ?? 'bin';
    }

    public static function absolutePathForRelative(string $relativePath): string
    {
        return App::storagePath($relativePath);
    }
}
