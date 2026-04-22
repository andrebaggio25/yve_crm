<?php

declare(strict_types=1);

use App\Core\Lang;

if (!function_exists('__')) {
    /**
     * @param array<string, string|int|float> $params
     */
    function __(string $key, array $params = []): string
    {
        return Lang::get($key, $params);
    }
}
