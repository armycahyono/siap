<?php

namespace App\Services;

use Illuminate\Contracts\View\View;

class DeviceView
{
    /**
     * Render the mobile or web view based on detected device.
     */
    public static function render(string $view, array $data = [], array $mergeData = []): View
    {
        if (DeviceDetector::isMobile()) {
            if (view()->exists("mobile.{$view}")) {
                return view("mobile.{$view}", $data, $mergeData);
            }
        } else {
            if (view()->exists("web.{$view}")) {
                return view("web.{$view}", $data, $mergeData);
            }
        }

        return view($view, $data, $mergeData);
    }
}
