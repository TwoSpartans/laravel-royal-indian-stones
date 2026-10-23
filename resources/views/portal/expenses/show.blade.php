@extends('portal.layout')
@section('title', 'Expense')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">{{ config('portal.expense_categories.'.$claim->category) }}</p>
    <h1>{{ \App\Support\Money::format($claim->amount_paise) }}</h1>
    <p class="muted">{{ $claim->user?->name }} · {{ $claim->incurred_on->format('d M Y') }} · {{ str_replace('_', ' ', $claim->status) }}</p>
  </div>
</header>
<section class="card">
  <p>{{ $claim->description }}</p>
  <p><a href="{{ route('portal.expenses.receipt', $claim) }}">Download receipt</a></p>
  <p>Payment record: <strong>{{ str_replace('_', ' ', $claim->payment_state) }}</strong></p>
  @if ($claim->payment_state === 'arranged_outside_system')
    <p>This was arranged outside the portal. It is not a confirmed bank payout. {{ $claim->payment_note }}</p>
  @endif
  @if (in_array($claim->status, ['submitted', 'pending_hr'], true) && ! auth()->user()->is($claim->user))
    <form method="post" action="{{ route('portal.expenses.decide', $claim) }}" class="btn-row">@csrf
      <input type="hidden" name="decision" value="approved"><button type="submit">Approve</button>
    </form>
    <form method="post" action="{{ route('portal.expenses.decide', $claim) }}">@csrf
      <input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button>
    </form>
  @endif
  @if ($claim->status === 'approved' && $claim->payment_state === 'none' && auth()->user()->portal_role?->canAdminister())
    <form method="post" action="{{ route('portal.expenses.payment', $claim) }}">
      @csrf
      <input type="hidden" name="payment_state" value="arranged_outside_system">
      <div class="field"><label for="payment_note">Note the outside arrangement</label><textarea id="payment_note" name="payment_note" required></textarea></div>
      <button type="submit">Record arrangement outside this portal</button>
      <p class="help">This does not send money and must not be read as a bank confirmation.</p>
    </form>
  @endif
</section>
@endsection
