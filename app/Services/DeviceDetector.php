<?php

namespace App\Services;

use Illuminate\Http\Request;

class DeviceDetector
{
    /**
     * Determine if the current request is from a mobile device (smartphone).
     */
    public static function isMobile(?Request $request = null): bool
    {
        $request = $request ?? request();

        // 1. Manual override via query parameter or session (useful for preview & testing)
        if ($request && $request->has('view_mode')) {
            $mode = strtolower((string) $request->query('view_mode'));
            if (in_array($mode, ['mobile', 'web'])) {
                session(['view_mode' => $mode]);

                return $mode === 'mobile';
            }
        }

        if (session()->has('view_mode')) {
            return session('view_mode') === 'mobile';
        }

        // 2. User-Agent regex check
        $userAgent = $request ? $request->header('User-Agent', '') : '';
        if (empty($userAgent)) {
            return false;
        }

        return (bool) preg_match(
            '/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i',
            $userAgent
        );
    }

    /**
     * Determine if the current request is from a desktop / laptop computer.
     */
    public static function isDesktop(?Request $request = null): bool
    {
        return ! self::isMobile($request);
    }
}
