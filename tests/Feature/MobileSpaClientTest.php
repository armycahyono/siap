<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileSpaClientTest extends TestCase
{
    public function test_mobile_spa_index_returns_200_and_html(): void
    {
        $response = $this->get('/mobile');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('SIAP Mobile')
            ->assertSee('app-view-container')
            ->assertSee('js/api.js', false)
            ->assertSee('js/router.js', false)
            ->assertSee('js/app.js', false);
    }

    public function test_mobile_assets_are_served_with_correct_mime_types(): void
    {
        // 1. Core JS Scripts
        $apiJs = $this->get('/mobile/js/api.js');
        $apiJs->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $routerJs = $this->get('/mobile/js/router.js');
        $routerJs->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $appJs = $this->get('/mobile/js/app.js');
        $appJs->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        // 2. CSS
        $css = $this->get('/mobile/css/mobile.css');
        $css->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        // 3. Manifest
        $manifest = $this->get('/mobile/manifest.json');
        $manifest->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        // 4. View Page Templates
        $loginPage = $this->get('/mobile/pages/auth/login.html');
        $loginPage->assertOk()
            ->assertSee('form-mobile-login');

        $dashboardPage = $this->get('/mobile/pages/dashboard.html');
        $dashboardPage->assertOk()
            ->assertSee('dash-user-name');

        $attendancePage = $this->get('/mobile/pages/attendance.html');
        $attendancePage->assertOk()
            ->assertSee('btn-att-checkin');

        $tasksPage = $this->get('/mobile/pages/tasks.html');
        $tasksPage->assertOk()
            ->assertSee('tasks-target-date-label');

        $profilePage = $this->get('/mobile/pages/profile.html');
        $profilePage->assertOk()
            ->assertSee('prof-decrees-list');
    }
}
