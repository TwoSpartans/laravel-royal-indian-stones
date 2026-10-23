<?php

namespace App\Console\Commands;

use App\Models\AccessEvent;
use App\Models\AttendancePunch;
use App\Models\AuditLog;
use App\Models\EmployeeDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portal:prune-retention')]
#[Description('Remove expired login and attendance verification events and report other retention dates')]
class PrunePortalRetention extends Command
{
    public function handle(): int
    {
        $accessMonths = (int) config('portal.retention.access_events_months');
        $deleted = AccessEvent::query()
            ->where('occurred_at', '<', now()->subMonths($accessMonths))
            ->delete();

        $this->info("Removed {$deleted} access events older than {$accessMonths} months.");
        $this->line('These records are kept until a reviewed retention decision deletes them:');
        $this->line('Audit logs past '.(int) config('portal.retention.audit_logs_months').' months: '.AuditLog::query()->where('created_at', '<', now()->subMonths((int) config('portal.retention.audit_logs_months')))->count());
        $this->line('Attendance punches past '.(int) config('portal.retention.attendance_months').' months: '.AttendancePunch::query()->where('punched_at', '<', now()->subMonths((int) config('portal.retention.attendance_months')))->count());
        $this->line('Documents past their retention date: '.EmployeeDocument::query()->whereDate('retention_until', '<', now()->toDateString())->count());
        $this->line('Payslips are not deleted by this command.');

        return self::SUCCESS;
    }
}
