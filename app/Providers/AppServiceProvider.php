<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Cbt\CbtUser;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Models\Student;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Observers\CbtUserObserver;
use App\Observers\LmsSyncObserver;
use App\Observers\UserObserver;
use App\Policies\PiketIncidentPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(PiketIncident::class, PiketIncidentPolicy::class);

        Paginator::defaultView('vendor.pagination.tabler');

        // Register model observers for SIAP <-> CBT real-time sync
        User::observe(UserObserver::class);
        CbtUser::observe(CbtUserObserver::class);

        // Register model observers for SIAP -> LMS real-time webhooks
        Student::observe(LmsSyncObserver::class);
        Classroom::observe(LmsSyncObserver::class);
        TeacherSubjectAssignment::observe(LmsSyncObserver::class);
        AcademicYear::observe(LmsSyncObserver::class);
        AttendanceLog::observe(LmsSyncObserver::class);

        if (request()->header('x-forwarded-proto') === 'https' || request()->isSecure() || str_ends_with(request()->getHost(), 'sudahonline.my.id')) {
            URL::forceScheme('https');
        }
    }
}
