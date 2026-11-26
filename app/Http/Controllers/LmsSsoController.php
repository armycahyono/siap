<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LmsSsoController extends Controller
{
    public function launch(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = Str::random(64);

        Cache::put("lms_sso_code:{$code}", $user->id, now()->addSeconds(60));

        $lmsBaseUrl = rtrim((string) config('services.lms.url', 'http://lms.test'), '/');

        return redirect()->away("{$lmsBaseUrl}/sso/callback?code={$code}");
    }
}
