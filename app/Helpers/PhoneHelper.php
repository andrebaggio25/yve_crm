<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Apenas digitos, sem inferir pais. O numero deve vir ja com codigo internacional (E.164 sem +).
     * Nao prefixa 55 — evita duplicar DDI em wa.me e no armazenamento.
     */
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    public static function format(string $phone): string
    {
        $normalized = self::normalize($phone);
        
        if (strlen($normalized) === 13) {
            return preg_replace('/^(\d{2})(\d{2})(\d{5})(\d{4})$/', '+$1 ($2) $3-$4', $normalized);
        }
        
        if (strlen($normalized) === 12) {
            return preg_replace('/^(\d{2})(\d{2})(\d{4})(\d{4})$/', '+$1 ($2) $3-$4', $normalized);
        }
        
        return $phone;
    }

    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);
        $len = strlen($normalized);

        return $len >= 8 && $len <= 15;
    }

    public static function isMobile(string $phone): bool
    {
        $normalized = self::normalize($phone);
        
        if (strlen($normalized) === 13) {
            $ddd = substr($normalized, 2, 2);
            $firstDigit = substr($normalized, 4, 1);
            return $firstDigit === '9';
        }
        
        return false;
    }

    /**
     * Link wa.me com numero internacional completo (somente digitos).
     * Nao remove codigo de pais — necessario para ES, PT, etc.
     */
    public static function getWhatsAppLink(string $phone, ?string $message = null): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return 'https://wa.me/';
        }

        $url = 'https://wa.me/' . $digits;
        if ($message !== null && $message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }
}
