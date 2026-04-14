<?php

namespace App\Core;

/**
 * Carrega variaveis de ambiente de arquivos .env (sem dependencia externa).
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $files = [
            $basePath . '/.env',
            $basePath . '/.env.local',
        ];

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            self::parseFile($file);
        }

        self::$loaded = true;
    }

    private static function parseFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^"(.*)"$/s', $value, $m)) {
                $value = stripcslashes($m[1]);
            } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = $m[1];
            }

            if ($name === '') {
                continue;
            }

            if (getenv($name) !== false) {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        return $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}
