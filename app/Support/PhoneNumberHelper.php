<?php

namespace App\Support;

class PhoneNumberHelper
{
    /**
     * Format any phone number into international WhatsApp format (e.g. 628123456789).
     * Returns null if the phone is empty or cannot be converted to a valid number.
     */
    public static function formatForWhatsapp(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Ambil hanya digit angka
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        // Jika diawali 0 (contoh: 081234567890 -> 6281234567890)
        if (str_starts_with($clean, '0')) {
            return '62'.substr($clean, 1);
        }

        // Jika diawali 8 tanpa 0 (contoh: 81234567890 -> 6281234567890)
        if (str_starts_with($clean, '8')) {
            return '62'.$clean;
        }

        // Jika sudah diawali 62 (contoh: 6281234567890)
        return $clean;
    }

    /**
     * Generate a direct https://wa.me/ URL.
     */
    public static function toWhatsappUrl(?string $phone, ?string $message = null): ?string
    {
        $waNumber = self::formatForWhatsapp($phone);
        if (! $waNumber) {
            return null;
        }

        $url = "https://wa.me/{$waNumber}";
        if ($message !== null && $message !== '') {
            $url .= '?text='.urlencode($message);
        }

        return $url;
    }
}
