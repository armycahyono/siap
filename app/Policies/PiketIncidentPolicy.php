<?php

namespace App\Policies;

use App\Models\PiketIncident;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PiketIncidentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any piket incidents.
     */
    public function viewAny(User $user, ?string $academicYear = null): bool
    {
        return $user->canViewPiketCases($academicYear);
    }

    /**
     * Determine whether the user can view the specific incident.
     */
    public function view(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        if ($user->canManagePiketCases($ay)) {
            return true;
        }

        if ($incident->recorded_by === $user->id) {
            return true;
        }

        if ($incident->classroom_id && $user->isHomeroomTeacher($incident->classroom, $ay)) {
            return true;
        }

        if ($incident->follow_up_target === 'rujuk_bk' && $user->isCounselingBK($ay)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can record/create a piket incident.
     */
    public function create(User $user, ?string $academicYear = null): bool
    {
        return $user->canRecordPiketIncident($academicYear);
    }

    /**
     * Determine whether the user can update the incident data.
     */
    public function update(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        if ($user->isSuperAdmin() || $user->isPrincipal() || $user->isWakaKesiswaan($ay)) {
            return true;
        }

        // Verified incident data cannot be arbitrarily edited once sanctioned
        if ($incident->isVerified()) {
            return false;
        }

        if ($user->isDisciplineTeam($ay) || $user->isDisciplineCoordinator($ay)) {
            return true;
        }

        return $incident->recorded_by === $user->id && $incident->status === 'tercatat';
    }

    /**
     * Determine whether the user can update the handling status.
     */
    public function updateStatus(User $user, PiketIncident $incident, ?string $newStatus = null): bool
    {
        $ay = $incident->academic_year;

        if ($newStatus !== null) {
            return $user->canTransitionIncidentStatus($incident, $newStatus, $ay);
        }

        return $user->canManagePiketCases($ay);
    }

    /**
     * Determine whether the user can verify the incident and assign disciplinary sanctions.
     */
    public function verify(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        return $user->canVerifyDiscipline($ay);
    }

    /**
     * Determine whether the user can delete the incident.
     */
    public function delete(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        return $user->canDeletePiketIncident($incident, $ay);
    }

    /**
     * Determine whether the user can view the private incident evidence photo.
     */
    public function viewPhoto(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        if ($user->isSuperAdmin() || $user->isPrincipal()) {
            return true;
        }

        if ($user->isWakaKesiswaan($ay) || $user->isDisciplineCoordinator($ay) || $user->isDisciplineTeam($ay)) {
            return true;
        }

        if ($incident->recorded_by === $user->id) {
            return true;
        }

        if ($incident->classroom_id && $user->isHomeroomTeacher($incident->classroom, $ay)) {
            return true;
        }

        if ($incident->follow_up_target === 'rujuk_bk' && $user->isCounselingBK($ay)) {
            return true;
        }

        if ($user->hasPermission('manage_piket_cases', $ay) || $user->hasPermission('operate_piket', $ay) || $user->isPiketOfficerToday()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can export the official parental summons letter.
     */
    public function exportSuratPanggilan(User $user, PiketIncident $incident): bool
    {
        $ay = $incident->academic_year;

        return $user->isSuperAdmin()
            || $user->isPrincipal()
            || $user->isWakaKesiswaan($ay)
            || $user->isDisciplineCoordinator($ay)
            || $user->isDisciplineTeam($ay)
            || $user->canManageDiscipline($ay);
    }
}
