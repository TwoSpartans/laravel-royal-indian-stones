<?php

namespace App\Services\Portal;

use App\Models\OvertimeRequest;
use App\Models\PayrollAdjustment;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\User;
use Illuminate\Support\Carbon;

class PayslipPreparer
{
    public function __construct(
        private AttendanceBoard $board,
        private AuditWriter $audit,
    ) {}

    public function calculationsEnabled(): bool
    {
        return (bool) config('portal.payroll.calculations_enabled')
            && (bool) config('portal.payroll.professional_review_confirmed');
    }

    public function prepare(User $actor, User $subject, string $period, ?string $ip): Payslip
    {
        [$year, $month] = array_map(intval(...), explode('-', $period));
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Kolkata');
        $end = $start->copy()->endOfMonth();
        $present = 0;
        $absent = 0;
        $late = 0;

        for ($day = $start->copy(); $day->lte($end) && $day->lte(now()); $day->addDay()) {
            $row = $this->board->roster(collect([$subject]), $day->copy())->first();
            if ($row === null) {
                continue;
            }
            if (in_array($row['state'], ['present', 'late', 'missed_punch'], true)) {
                $present++;
            }
            if ($row['state'] === 'absent') {
                $absent++;
            }
            if ($row['state'] === 'late') {
                $late++;
            }
        }

        $structure = SalaryStructure::query()
            ->where('user_id', $subject->id)
            ->whereDate('effective_on', '<=', $end->toDateString())
            ->orderByDesc('effective_on')
            ->first();

        $adjustment = (int) PayrollAdjustment::query()
            ->where('user_id', $subject->id)
            ->where('period', $period)
            ->where('status', 'approved')
            ->sum('amount_paise');

        $overtime = (int) OvertimeRequest::query()
            ->where('user_id', $subject->id)
            ->where('status', 'approved')
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->sum('minutes');

        $enabled = $this->calculationsEnabled();
        $components = is_array($structure?->components) ? $structure->components : [];
        $net = null;
        if ($enabled && $components !== []) {
            $monthly = 0;
            foreach ($components as $amount) {
                $monthly += (int) $amount;
            }
            $divisor = max(1, $present + $absent);
            $net = (int) round($monthly * ($present / $divisor)) + $adjustment;
        }

        $summary = [
            'present_days' => $present,
            'absent_days' => $absent,
            'late_days' => $late,
            'overtime_minutes' => $overtime,
            'adjustment_paise' => $adjustment,
            'components' => $components,
            'calculations' => $enabled ? 'prepared' : 'withheld',
            'net_paise' => $net,
            'statutory' => config('portal.payroll.statutory'),
        ];

        $disclaimer = $enabled
            ? 'Figures exclude statutory deductions until those rules are separately enabled. This document is not a bank payment.'
            : 'Pay calculations are withheld until a qualified reviewer enables them. This document lists attendance inputs only and is not a bank payment.';

        $payslip = Payslip::query()->updateOrCreate(
            ['user_id' => $subject->id, 'period' => $period],
            [
                'status' => $enabled ? 'draft' : 'review_required',
                'summary' => $summary,
                'disclaimer' => $disclaimer,
                'payment_state' => 'none',
                'prepared_by' => $actor->id,
                'published_at' => null,
            ],
        );

        $this->audit->write($actor, 'payroll.prepared', $payslip, [
            'period' => $period,
            'calculations' => $summary['calculations'],
        ], $ip);

        return $payslip;
    }
}
