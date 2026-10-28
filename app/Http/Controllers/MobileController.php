<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class MobileController extends Controller
{
    /**
     * Tampilkan halaman aplikasi mobile standalone (SPA Client & PWA).
     */
    public function index(): Response
    {
        $htmlPath = base_path('mobile/www/index.html');

        if (! file_exists($htmlPath)) {
            abort(404, 'Mobile client index.html tidak ditemukan.');
        }

        $content = file_get_contents($htmlPath);

        // Cache-busting for scripts & styles during development/updates
        $appJsVersion = file_exists(base_path('mobile/www/js/app.js')) ? filemtime(base_path('mobile/www/js/app.js')) : time();
        $apiJsVersion = file_exists(base_path('mobile/www/js/api.js')) ? filemtime(base_path('mobile/www/js/api.js')) : time();
        $routerJsVersion = file_exists(base_path('mobile/www/js/router.js')) ? filemtime(base_path('mobile/www/js/router.js')) : time();
        $cssVersion = file_exists(base_path('mobile/www/css/mobile.css')) ? filemtime(base_path('mobile/www/css/mobile.css')) : time();

        $content = str_replace('src="js/app.js"', 'src="js/app.js?v='.$appJsVersion.'"', $content);
        $content = str_replace('src="js/api.js"', 'src="js/api.js?v='.$apiJsVersion.'"', $content);
        $content = str_replace('src="js/router.js"', 'src="js/router.js?v='.$routerJsVersion.'"', $content);
        $content = str_replace('href="css/mobile.css"', 'href="css/mobile.css?v='.$cssVersion.'"', $content);
        $content = str_replace("'sw.js'", "'sw.js?v=".$appJsVersion."'", $content);

        return response($content, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Sajikan aset statis (CSS, JS, Asset, Manifest, Pages) untuk mobile client.
     */
    public function asset(string $path): Response
    {
        $safePath = str_replace(['..', '//'], ['', '/'], $path);
        $fullPath = base_path('mobile/www/'.$safePath);

        if (! file_exists($fullPath) || is_dir($fullPath)) {
            abort(404, 'Aset mobile tidak ditemukan.');
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'html' => 'text/html; charset=UTF-8',
        ];

        $contentType = $mimeTypes[$extension] ?? (mime_content_type($fullPath) ?: 'application/octet-stream');
        $content = file_get_contents($fullPath);

        $cacheControl = ($extension === 'js' || $extension === 'css' || $extension === 'html')
            ? 'no-cache, must-revalidate'
            : 'public, max-age=3600';

        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => $cacheControl,
        ];

        if (basename($fullPath) === 'sw.js') {
            $headers['Service-Worker-Allowed'] = '/mobile/';
        }

        return response($content, 200, $headers);
    }
}
