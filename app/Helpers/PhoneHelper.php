<?php

namespace App\Helpers;

class PhoneHelper
{
    public static function normalize(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);
        
        if (strlen($cleaned) === 11) {
            return '55' . $cleaned;
        }
        
        if (strlen($cleaned) === 10) {
            return '55' . $cleaned;
        }
        
        if (strlen($cleaned) === 13 && strpos($cleaned, '55') === 0) {
            return $cleaned;
        }
        
        if (strlen($cleaned) === 12 && strpos($cleaned, '55') === 0) {
            return $cleaned;
        }
        
        return $cleaned;
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
        
        return strlen($normalized) >= 12 && strlen($normalized) <= 13;
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
