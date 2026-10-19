<?php

namespace App\Providers;

use App\Models\EmployeePromotion;
use App\Models\WlaAssessment;
use App\Models\WlaParticipant;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Policies\EmployeePromotionPolicy;
use App\Policies\WlaAssessmentPolicy;
use App\Policies\WlaParticipantPolicy;
use App\Policies\WorkCalendarPolicy;
use App\Policies\WorkSchedulePolicy;
use App\Services\Authorization\PermissionRegistry;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        EmployeePromotion::class => EmployeePromotionPolicy::class,
        WorkSchedule::class => WorkSchedulePolicy::class,
        WorkCalendar::class => WorkCalendarPolicy::class,
        WlaAssessment::class => WlaAssessmentPolicy::class,
        WlaParticipant::class => WlaParticipantPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        foreach (array_keys(PermissionRegistry::permissions()) as $permission) {
            Gate::define($permission, fn ($user): bool => $user->hasPermission($permission));
        }
    }
}
