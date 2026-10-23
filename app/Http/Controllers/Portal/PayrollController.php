<?php

namespace App\Http\Controllers\Portal;

use App\Models\PayrollAdjustment;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\PayslipPreparer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $people = $this->access->peopleQuery($actor)->with('profile')->orderBy('name')->get();

        return view('portal.payroll.index', [
            'people' => $people,
            'structures' => SalaryStructure::query()->whereIn('user_id', $people->modelKeys())->orderByDesc('effective_on')->get()->groupBy('user_id'),
            'payslips' => Payslip::query()->whereIn('user_id', $people->modelKeys())->orderByDesc('period')->get()->groupBy('user_id'),
            'calculationsEnabled' => app(PayslipPreparer::class)->calculationsEnabled(),
            'statutory' => config('portal.payroll.statutory'),
        ]);
    }

    public function structure(Request $request, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'effective_on' => ['required', 'date'],
            'basic' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'hra' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'special' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $person = $this->access->peopleQuery($actor)->whereKey($data['user_id'])->first();
        abort_unless($person instanceof User, 404);
        $components = [];
        foreach (['basic', 'hra', 'special'] as $key) {
            if (($data[$key] ?? '') === '') {
                continue;
            }
            $paise = Money::rupeesToPaise($data[$key]);
            if ($paise === null) {
                return back()->withErrors([$key => 'Enter this amount in rupees.'])->withInput();
            }
            $components[$key] = $paise;
        }

        $structure = SalaryStructure::query()->create([
            'user_id' => $person->id,
            'effective_on' => $data['effective_on'],
            'components' => $components,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $audit->write($actor, 'payroll.structure_saved', $structure, ['user_id' => $person->id], $request->ip());

        return back()->with('status', 'Salary structure saved. It is encrypted at rest and is not a payslip.');
    }

    public function adjustment(Request $request, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'regex:/^-?\d{1,8}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $person = $this->access->peopleQuery($actor)->whereKey($data['user_id'])->first();
        abort_unless($person instanceof User, 404);
        $negative = str_starts_with($data['amount'], '-');
        $paise = Money::rupeesToPaise(ltrim($data['amount'], '-'));
        if ($paise === null) {
            return back()->withErrors(['amount' => 'Enter the adjustment in rupees.'])->withInput();
        }

        $adjustment = PayrollAdjustment::query()->create([
            'user_id' => $person->id,
            'period' => $data['period'],
            'amount_paise' => $negative ? -$paise : $paise,
            'reason' => $data['reason'],
            'status' => 'approved',
            'decided_by' => $actor->id,
        ]);
        $audit->write($actor, 'payroll.adjustment_recorded', $adjustment, [], $request->ip());

        return back()->with('status', 'Approved adjustment recorded. It will be included when a payslip is prepared.');
    }

    public function prepare(Request $request, PayslipPreparer $preparer): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'period' => ['required', 'date_format:Y-m'],
        ]);
        $person = $this->access->peopleQuery($actor)->whereKey($data['user_id'])->first();
        abort_unless($person instanceof User, 404);
        $preparer->prepare($actor, $person, $data['period'], $request->ip());

        return back()->with('status', $preparer->calculationsEnabled()
            ? 'Draft payslip prepared. Statutory lines are still off, and nothing has been paid.'
            : 'Payroll inputs were saved. Calculations stay withheld until a qualified reviewer enables them.');
    }

    public function publish(Request $request, Payslip $payslip, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        abort_unless(in_array($payslip->status, ['draft', 'review_required'], true), 403);
        $payslip->forceFill([
            'status' => 'published',
            'published_at' => now(),
        ])->save();
        $audit->write($actor, 'payroll.published', $payslip, ['period' => $payslip->period], $request->ip());

        return back()->with('status', 'Payslip published to the employee. Payment state remains unrecorded.');
    }

    public function show(Request $request, Payslip $payslip, AuditWriter $audit): View
    {
        $actor = $this->actor($request);
        $owner = User::query()->find($payslip->user_id);
        $allowed = $owner instanceof User && (
            $this->access->canAdminister($actor)
            || ($actor->is($owner) && $payslip->status === 'published')
        );
        abort_unless($allowed, 404);
        $audit->write($actor, 'payroll.payslip_viewed', $payslip, [], $request->ip());

        return view('portal.payslip', ['payslip' => $payslip, 'owner' => $owner]);
    }

    public function payment(Request $request, Payslip $payslip): RedirectResponse
    {
        $request->validate([
            'payment_state' => ['required', Rule::in(['arranged_outside_system'])],
        ], [
            'payment_state.in' => 'A payslip cannot be marked sent. No payment provider is connected.',
        ]);

        return back()->withErrors([
            'payment_state' => 'Salary payouts are not recorded from this screen. Connect a reviewed payment provider before any salary movement is tracked.',
        ]);
    }
}
