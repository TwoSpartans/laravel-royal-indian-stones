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

        if (User::query()->where('email', strtolower($email))->exists()) {
            $this->error('That email already has an account. The command will not change an existing password.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
            'is_site_admin' => (bool) $this->option('with-site-admin'),
            'portal_role' => PortalRole::Employer,
            'password_set_at' => now(),
            'email_verified_at' => now(),
            'timezone' => 'Asia/Kolkata',
        ])->save();

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
        $user->profile?->forceFill([
            'data_notice_acknowledged_at' => now(),
            'data_notice_version' => config('portal.notice_version'),
        ])->save();
        $provision->balances($user);
        $provision->checklist($user, 'onboarding', config('portal.onboarding_items'));

        $this->info('Employer account created for '.$user->email.'. Sign in at /portal/login and set up an authenticator.');
        $this->line('The password was not printed.');

        return self::SUCCESS;
    }
}
