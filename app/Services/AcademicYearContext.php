<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use InvalidArgumentException;
use RuntimeException;

use function function_exists;

/**
 * Service that provides a clear separation of concerns for academic year handling.
 *
 * - Operational Year: The year that new data should be written to. Determined
 *   solely by the active AcademicYear record (is_active flag). No session lookup.
 * - Viewing Year: The year used for read‑only display/filtering. Respects an
 *   explicit request, then the session value, then the master fallback.
 * - Explicit Year: A year supplied directly by a business process; validated
 *   against known years but does not affect session or setting state.
 */
class AcademicYearContext
{
    /**
     * Validate the standard academic year string format (YYYY/YYYY, e.g., '2026/2027').
     */
    public static function validateFormat(string $year): bool
    {
        if (! preg_match('/^(\d{4})\/(\d{4})$/', $year, $matches)) {
            return false;
        }

        return ((int) $matches[2]) === ((int) $matches[1] + 1);
    }

    /**
     * Get the currently active (operational) AcademicYear model.
     *
     * @throws RuntimeException if no active year is configured.
     */
    public function getOperationalYear(): AcademicYear
    {
        $active = AcademicYear::getActive();
        if (! $active) {
            if (AcademicYear::count() === 0) {
                $masterYear = SchoolSetting::getMasterYear();
                if ($masterYear && self::validateFormat($masterYear)) {
                    return new AcademicYear([
                        'name' => $masterYear,
                        'is_active' => true,
                        'semester' => SchoolSetting::getSetting(true)->semester ?? 'Ganjil',
                    ]);
                }
            }
            throw new RuntimeException('Active academic year is not configured.');
        }

        return $active;
    }

    /**
     * Resolve the year that should be used for viewing / filtering.
     *
     * @param  string|null  $requestedYear  Year supplied explicitly (e.g. query param).
     * @return string Validated academic year string.
     *
     * @throws InvalidArgumentException If the supplied year does not exist.
     */
    public function getViewingYear(?string $requestedYear = null): string
    {
        // 1. Explicit request overrides everything.
        if ($requestedYear !== null) {
            return $this->resolveExplicitYear($requestedYear);
        }

        // 2. Session value (historical view) – do not fall back to operational.
        if (function_exists('session') && session()->has('active_academic_year')) {
            $sessionYear = session('active_academic_year');
            // Validate the session value; if invalid we treat it as not set.
            try {
                return $this->resolveExplicitYear($sessionYear);
            } catch (InvalidArgumentException) {
                // Ignore invalid session value and continue to fallback.
            }
        }

        // 3. Master fallback defined in SchoolSetting (or literal fallback).
        $masterYear = SchoolSetting::getMasterYear();
        // Validate master year exists in known years list.
        if (! in_array($masterYear, $this->getAvailableYears(), true)) {
            throw new RuntimeException("Master academic year '{$masterYear}' is not available.");
        }

        return $masterYear;
    }

    /**
     * Validate and return an explicit academic year.
     *
     * @return string Validated academic year.
     *
     * @throws InvalidArgumentException if the year is unknown.
     */
    public function resolveExplicitYear(string $year): string
    {
        $available = $this->getAvailableYears();
        if (! in_array($year, $available, true)) {
            if (self::validateFormat($year) && (in_array($year, $this->getAllYearsIncludingGenerated(), true) || Supervision::where('academic_year', $year)->exists())) {
                return $year;
            }
            throw new InvalidArgumentException("Academic year '{$year}' is not available.");
        }

        return $year;
    }

    /**
     * Helper: list of all known academic year strings.
     * Delegates to SchoolSetting::getAvailableAcademicYears().
     *
     * @return array<string>
     */
    /**
     * Return list of academic years that actually exist in the database.
     * This excludes any generated fallback years.
     */
    protected function getAvailableYears(): array
    {
        return AcademicYear::orderBy('name', 'desc')->pluck('name')->toArray();
    }

    /**
     * Return all years that may be presented to the user, including generated ones.
     * Kept for backward compatibility if needed elsewhere.
     */
    protected function getAllYearsIncludingGenerated(): array
    {
        return SchoolSetting::getAvailableAcademicYears();
    }
}
