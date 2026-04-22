<?php

namespace App\Core;

/**
 * i18n: resolução de locale (user > tenant > APP_LOCALE > es) e carregamento de chaves.
 */
class Lang
{
    private static string $locale = 'es';

    /** @var array<string, string> */
    private static array $lines = [];

    public static function supported(): array
    {
        return ['es', 'en', 'pt'];
    }

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, self::supported(), true)) {
            self::$locale = $locale;
        } else {
            self::$locale = 'es';
        }
        self::load();
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function initFromRequest(): void
    {
        $u = Session::user();
        if (is_array($u) && !empty($u['locale']) && in_array($u['locale'], self::supported(), true)) {
            self::setLocale((string) $u['locale']);

            return;
        }
        if (TenantContext::hasTenant()) {
            $t = TenantContext::getTenant();
            if (is_array($t) && !empty($t['default_locale']) && in_array($t['default_locale'], self::supported(), true)) {
                self::setLocale((string) $t['default_locale']);

                return;
            }
        }
        $def = (string) Env::get('APP_LOCALE', 'es');
        self::setLocale(in_array($def, self::supported(), true) ? $def : 'es');
    }

    private static function load(): void
    {
        $file = __DIR__ . '/../../lang/' . self::$locale . '.php';
        if (!is_readable($file)) {
            $file = __DIR__ . '/../../lang/es.php';
        }
        $data = require $file;
        self::$lines = is_array($data) ? $data : [];
    }

    public static function get(string $key, array $params = []): string
    {
        if (self::$lines === [] && self::$locale !== '') {
            self::load();
        }
        $s = self::$lines[$key] ?? $key;
        foreach ($params as $name => $value) {
            $s = str_replace(':' . $name, (string) $value, $s);
        }

        return $s;
    }
}
