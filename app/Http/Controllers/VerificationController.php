<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function verify(string $token): View
    {
        $supervision = Supervision::with(['teacher', 'supervisor', 'principal'])
            ->where('qr_token', $token)
            ->first();

        $setting = SchoolSetting::getSetting();

        return view('verification.index', compact('supervision', 'token', 'setting'));
    }
}
