<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureCanonicalDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_kurikulum_sudahonline_to_siap_sudahonline(): void
    {
        $response = $this->get('https://kurikulum.sudahonline.my.id/presensi/geotag');

        $response->assertStatus(301);
        $response->assertRedirect('https://siap.sudahonline.my.id/presensi/geotag');
    }

    public function test_redirects_kurikulum_test_to_siap_test(): void
    {
        $response = $this->get('http://kurikulum.test/login');

        $response->assertStatus(301);
        $response->assertRedirect('http://siap.test/login');
    }

    public function test_allows_siap_domain_without_redirect(): void
    {
        $response = $this->get('http://siap.test/login');

        $response->assertStatus(200);
    }
}
