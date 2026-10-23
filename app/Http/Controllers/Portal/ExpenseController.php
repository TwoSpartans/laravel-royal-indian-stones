<?php

namespace App\Http\Controllers\Portal;

use App\Models\ExpenseClaim;
use App\Models\User;
use App\Notifications\PortalStatusNotification;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\ExpenseDecision;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $status = $request->string('status')->toString();
        $claims = $this->scoped($actor)
            ->with('user')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.expenses.index', [
            'claims' => $claims,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('portal.expenses.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('portal.expense_categories')))],
            'amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'amount.regex' => 'Enter the amount in rupees, with up to two decimal places.',
        ]);

        $paise = Money::rupeesToPaise($data['amount']);
        if ($paise === null || $paise < 1) {
            return back()->withErrors(['amount' => 'Enter an amount greater than zero.'])->withInput();
        }

        $file = $request->file('receipt');
        $path = $file->storeAs('receipts/'.$actor->id, (string) str()->uuid().'.'.$file->extension(), 'portal');
        $status = $actor->profile?->manager_id ? 'submitted' : 'pending_hr';
        $claim = ExpenseClaim::query()->create([
            'user_id' => $actor->id,
            'category' => $data['category'],
            'amount_paise' => $paise,
            'incurred_on' => $data['incurred_on'],
            'description' => $data['description'],
            'status' => $status,
            'receipt_path' => $path,
            'receipt_name' => $file->getClientOriginalName(),
            'payment_state' => 'none',
        ]);

        $actor->profile?->manager?->notify(new PortalStatusNotification(
            'Expense claim waiting',
            $actor->name.' submitted '.Money::format($paise).'.',
            route('portal.expenses.show', $claim),
        ));

        return redirect()->route('portal.expenses.show', $claim)->with('status', 'Expense claim submitted with the receipt.');
    }

    public function show(Request $request, ExpenseClaim $expense): View
    {
        $actor = $this->actor($request);
        $expense->load('user.profile');
        abort_unless($this->visible($actor, $expense), 404);

        return view('portal.expenses.show', ['claim' => $expense]);
    }

    public function decide(Request $request, ExpenseClaim $expense, ExpenseDecision $decisions): RedirectResponse
    {
        $actor = $this->actor($request);
        $expense->load('user.profile');
        abort_unless($this->visible($actor, $expense), 404);
        abort_unless($this->access->canDecideExpense($actor, $expense), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $decisions->apply($actor, $expense, $data['decision'], $data['decision_note'] ?? null, $request->ip());

        return back()->with('status', 'Expense decision saved.');
    }

    public function payment(Request $request, ExpenseClaim $expense, ExpenseDecision $decisions): RedirectResponse
    {
        $actor = $this->actor($request);
        $expense->load('user');
        abort_unless($this->visible($actor, $expense), 404);
        abort_unless($this->access->canRecordPayment($actor, $expense), 403);
        $data = $request->validate([
            'payment_state' => ['required', Rule::in(['arranged_outside_system'])],
            'payment_note' => ['required', 'string', 'max:500'],
        ], [
            'payment_state.in' => 'A payment cannot be marked sent. This portal has no confirmed payout integration.',
        ]);

        $decisions->recordArrangement($actor, $expense, $data['payment_note'], $request->ip());

        return back()->with('status', 'Payment was recorded as arranged outside this portal. It is not a confirmed bank payout.');
    }

    public function receipt(Request $request, ExpenseClaim $expense, AuditWriter $audit): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->visible($actor, $expense), 404);
        abort_unless(Storage::disk('portal')->exists($expense->receipt_path), 404);
        $audit->write($actor, 'expense.receipt_downloaded', $expense, [], $request->ip());

        return Storage::disk('portal')->download($expense->receipt_path, $expense->receipt_name);
    }

    private function scoped(User $actor): Builder
    {
        $ids = $this->access->peopleQuery($actor)->pluck('id');
        if (! $ids->contains($actor->id)) {
            $ids->push($actor->id);
        }

        return ExpenseClaim::query()->whereIn('user_id', $ids->all() ?: [-1]);
    }

    private function visible(User $actor, ExpenseClaim $claim): bool
    {
        return $actor->is($claim->user) || ($claim->user !== null && $this->access->canViewUser($actor, $claim->user));
    }
}
