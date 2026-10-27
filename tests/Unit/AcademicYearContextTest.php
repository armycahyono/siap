<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Services\AcademicYearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * @covers \App\Services\AcademicYearContext
 */
class AcademicYearContextTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYearContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new AcademicYearContext;

        // Seed minimal SchoolSetting (firstOrCreate in getSetting will create it)
        SchoolSetting::firstOrCreate(['id' => 1], [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        // Create a few academic years
        AcademicYear::create(['name' => '2025/2026', 'is_active' => false]);
        AcademicYear::create(['name' => '2026/2027', 'is_active' => true]); // active
        AcademicYear::create(['name' => '2027/2028', 'is_active' => false]);
    }

    /** @test */
    public function test_returns_the_active_operational_year()
    {
        $active = $this->context->getOperationalYear();
        $this->assertInstanceOf(AcademicYear::class, $active);
        $this->assertEquals('2026/2027', $active->name);
    }

    /** @test */
    public function test_throws_when_no_active_year_is_configured()
    {
        // Deactivate all years
        AcademicYear::query()->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->context->getOperationalYear();
    }

    /** @test */
    public function test_operational_year_is_not_affected_by_session()
    {
        session(['active_academic_year' => '2025/2026']);
        $active = $this->context->getOperationalYear();
        $this->assertEquals('2026/2027', $active->name);
    }

    /** @test */
    public function test_viewing_year_uses_explicit_requested_year()
    {
        $year = $this->context->getViewingYear('2025/2026');
        $this->assertEquals('2025/2026', $year);
    }

    /** @test */
    public function test_viewing_year_throws_on_invalid_explicit_year()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->context->getViewingYear('invalid-year');
    }

    /** @test */
    public function test_viewing_year_uses_session_when_no_explicit_and_valid()
    {
        session(['active_academic_year' => '2025/2026']);
        $year = $this->context->getViewingYear();
        $this->assertEquals('2025/2026', $year);
    }

    /** @test */
    public function test_viewing_year_falls_back_to_master_when_session_missing_or_invalid()
    {
        // No session value – should return master year from SchoolSetting
        $year = $this->context->getViewingYear();
        $this->assertEquals('2026/2027', $year);

        // Invalid session value – should ignore and fallback to master
        session(['active_academic_year' => '9999/9998']);
        $year = $this->context->getViewingYear();
        $this->assertEquals('2026/2027', $year);
    }

    /** @test */
    public function test_viewing_year_throws_when_master_year_is_invalid()
    {
        // Set an invalid master year in settings
        SchoolSetting::first()->update(['academic_year' => '2099/2100']);
        $this->expectException(RuntimeException::class);
        $this->context->getViewingYear();
    }

    /** @test */
    public function test_resolve_explicit_year_validates_successfully()
    {
        $resolved = $this->context->resolveExplicitYear('2027/2028');
        $this->assertEquals('2027/2028', $resolved);
    }

    /** @test */
    public function test_resolve_explicit_year_throws_on_invalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->context->resolveExplicitYear('invalid-year');
    }

    /** @test */
    public function test_getters_do_not_modify_session_or_setting()
    {
        session(['active_academic_year' => '2025/2026']);
        $originalSetting = SchoolSetting::getMasterYear();

        $this->context->getOperationalYear();
        $this->context->getViewingYear();
        $this->context->resolveExplicitYear('2025/2026');

        $this->assertEquals($originalSetting, SchoolSetting::getMasterYear());
        $this->assertEquals('2025/2026', session('active_academic_year'));
    }
}
