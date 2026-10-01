<?php

namespace App\Providers;

use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Policies\WlaAssessmentPolicy;
use App\Policies\WorkCalendarPolicy;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        WorkSchedule::class => WorkSchedulePolicy::class,
        WorkCalendar::class => WorkCalendarPolicy::class,
        WlaAssessment::class => WlaAssessmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
