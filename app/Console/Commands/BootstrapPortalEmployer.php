<?php

namespace App\Console\Commands;

use App\Enums\EmploymentStatus;
use App\Enums\PortalRole;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Portal\ProvisionEmployee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portal:bootstrap-employer {--with-site-admin : Also allow this person into the content studio}')]
#[Description('Create the Employer account from PORTAL_EMPLOYER_EMAIL and PORTAL_EMPLOYER_PASSWORD')]
class BootstrapPortalEmployer extends Command
{
    public function handle(ProvisionEmployee $provision): int
    {
        $email = config('portal.employer_email');
        $password = config('portal.employer_password');
        $name = config('portal.employer_name');

        if (! is_string($email) || $email === '' || ! is_string($password) || strlen($password) < 12 || ! is_string($name) || $name === '') {
            $this->error('Set PORTAL_EMPLOYER_NAME, PORTAL_EMPLOYER_EMAIL, and a PORTAL_EMPLOYER_PASSWORD of at least 12 characters.');

            return self::FAILURE;
        }

        $email = strtolower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && $user->portal_role !== null && $user->portal_role !== PortalRole::Employer) {
            $this->error('That email already belongs to '.$user->portal_role->label().'. Choose a different PORTAL_EMPLOYER_EMAIL.');

            return self::FAILURE;
        }

        $created = ! $user instanceof User;
        if (! $user instanceof User) {
            $user = new User;
        }

        $user->forceFill([
            'name' => $created ? $name : ($user->name !== '' ? $user->name : $name),
            'email' => $email,
            'password' => $password,
            'is_site_admin' => (bool) $this->option('with-site-admin') || (bool) $user->is_site_admin,
            'portal_role' => PortalRole::Employer,
            'password_set_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deactivated_at' => null,
            'timezone' => $user->timezone ?: 'Asia/Kolkata',
        ])->save();

        $this->ensureProfile($user, $provision);

        $this->info(($created ? 'Employer account created for ' : 'Employer access enabled for ').$user->email.'. Sign in at /portal/login and set up an authenticator.');
        $this->line('The password was taken from PORTAL_EMPLOYER_PASSWORD and was not printed.');

        return self::SUCCESS;
    }

    private function ensureProfile(User $user, ProvisionEmployee $provision): void
    {
        if ($user->profile === null) {
            $location = WorkLocation::query()->where('name', 'Jodhpur Head Office')->first();
            $department = Department::query()->where('name', 'Administration')->first();
            $shift = Shift::query()->where('name', 'General')->first();

            $provision->profile($user, [
                'job_title' => 'Employer',
                'department_id' => $department?->id,
                'work_location_id' => $location?->id,
                'shift_id' => $shift?->id,
                'joined_on' => now()->toDateString(),
            ], EmploymentStatus::Active);
            $provision->balances($user);
            $provision->checklist($user, 'onboarding', config('portal.onboarding_items'));
            $user->unsetRelation('profile');
        }

        $user->load('profile');
        $user->profile?->forceFill([
            'employment_status' => $user->profile->employment_status === EmploymentStatus::Invited
                ? EmploymentStatus::Active
                : $user->profile->employment_status,
            'data_notice_acknowledged_at' => $user->profile->data_notice_acknowledged_at ?? now(),
            'data_notice_version' => $user->profile->data_notice_version ?: config('portal.notice_version'),
        ])->save();
    }
}
