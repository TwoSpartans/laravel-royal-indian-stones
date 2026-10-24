@extends('portal.layout')
@section('title', $person->name)
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">{{ $person->profile?->employee_code }}</p>
    <h1>{{ $person->name }}</h1>
    <p class="muted">{{ $person->profile?->job_title }} · {{ $person->portal_role?->label() }} · {{ $person->profile?->employment_status?->label() }}</p>
  </div>
  @if ($canEdit)
    <a class="btn" href="{{ route('portal.people.edit', $person) }}">Edit</a>
  @endif
</header>
<div class="grid cards-2">
  <section class="card">
    <h2>Work</h2>
    <p>{{ $person->profile?->department?->name }} · {{ $person->profile?->location?->name }}</p>
    <p>Shift {{ $person->profile?->shift?->name }} · Manager {{ $person->profile?->manager?->name ?? '—' }}</p>
    <p>Joined {{ $person->profile?->joined_on?->format('d M Y') ?? '—' }} · Probation ends {{ $person->profile?->probation_ends_on?->format('d M Y') ?? '—' }}</p>
    <p>Times shown in {{ \App\Support\PortalTime::timezone($person) }}</p>
    @if ($canSensitive)
      <p>Phone {{ $person->profile?->phone ?: '—' }}</p>
      <p>Address {{ $person->profile?->address ?: '—' }}</p>
      <p>{{ $person->profile?->identity_document_label ?: 'Identity document' }}: {{ $person->profile?->identity_document_number ?: '—' }}</p>
    @endif
  </section>
  <section class="card">
    <h2>Leave this year</h2>
    @forelse ($balances as $balance)
      <p>{{ $balance->type?->name }}: {{ $balance->remaining() }} of {{ $balance->allocated }} left</p>
    @empty
      <div class="empty"><strong>No balance</strong></div>
    @endforelse
    @if ($canEdit)
      <form method="post" action="{{ route('portal.people.balances.update', $person) }}">
        @csrf
        <div class="field"><label for="leave_type_id">Adjust allocation</label>
          <select id="leave_type_id" name="leave_type_id">@foreach ($balances as $balance)<option value="{{ $balance->leave_type_id }}">{{ $balance->type?->name }}</option>@endforeach</select>
        </div>
        <div class="field"><label for="allocated">Days</label><input id="allocated" name="allocated" inputmode="decimal" required></div>
        <button type="submit">Save balance</button>
      </form>
    @endif
  </section>
</div>

<section class="card" style="margin-top:0.9rem">
  <h2>Emergency contacts</h2>
  @forelse ($contacts as $contact)
    <p>{{ $contact->name }} · {{ $contact->relationship }} · {{ $contact->phone }} @if($contact->is_primary)<span class="badge">Primary</span>@endif</p>
  @empty
    <div class="empty"><strong>No emergency contact yet</strong></div>
  @endforelse
  <form method="post" action="{{ route('portal.people.contacts.store', $person) }}" class="form-grid">
    @csrf
    <div class="field"><label for="contact_name">Name</label><input id="contact_name" name="name" required></div>
    <div class="field"><label for="relationship">Relationship</label><input id="relationship" name="relationship" required></div>
    <div class="field"><label for="contact_phone">Phone</label><input id="contact_phone" name="phone" required></div>
    <div class="field check"><input id="is_primary" name="is_primary" type="checkbox" value="1"><label for="is_primary">Primary</label></div>
    <button type="submit">Add contact</button>
  </form>
</section>

@if ($canSensitive)
  <section class="card" style="margin-top:0.9rem">
    <h2>Documents</h2>
    @forelse ($documents as $document)
      <p><a href="{{ route('portal.documents.download', $document) }}">{{ $document->title }}</a> · {{ $document->category }} @if($document->expires_on)· expires {{ $document->expires_on->format('d M Y') }}@endif</p>
    @empty
      <div class="empty"><strong>No private documents</strong><p>Identity files stay off the public website.</p></div>
    @endforelse
    <form method="post" action="{{ route('portal.people.documents.store', $person) }}" enctype="multipart/form-data" class="form-grid">
      @csrf
      <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="identity">Identity</option><option value="contract">Contract</option><option value="certificate">Certificate</option><option value="other">Other</option></select></div>
      <div class="field"><label for="title">Title</label><input id="title" name="title" required></div>
      <div class="field"><label for="expires_on">Expires</label><input id="expires_on" name="expires_on" type="date"></div>
      <div class="field"><label for="identity_reference">Reference</label><input id="identity_reference" name="identity_reference" autocomplete="off"></div>
      <div class="field"><label for="file">File</label><input id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
      <button type="submit">Upload</button>
    </form>
  </section>
@endif

<section class="card" style="margin-top:0.9rem">
  <h2>Onboarding and offboarding</h2>
  @forelse ($checklist as $item)
    <form method="post" action="{{ route('portal.checklist.update', $item) }}">
      @csrf
      <button class="btn-quiet" type="submit" @disabled(! $canEdit)>{{ $item->is_complete ? 'Done' : 'Open' }} · {{ $item->label }}</button>
    </form>
  @empty
    <div class="empty"><strong>No checklist</strong></div>
  @endforelse
</section>

@if ($canEdit)
  <section class="card" style="margin-top:0.9rem">
    <h2>Recent sign-ins</h2>
    @forelse ($logins as $login)
      <p>{{ \App\Support\PortalTime::format($login->occurred_at, $person, $login->timezone) }} · {{ $login->ip_address ?: 'no address' }} · {{ $login->classification_label }}</p>
    @empty
      <div class="empty"><strong>No staff sign-in recorded yet</strong></div>
    @endforelse
  </section>
@endif

<section class="card" style="margin-top:0.9rem">
  <h2>Changes</h2>
  @forelse ($changes as $change)
    <p>{{ $change->created_at->timezone('Asia/Kolkata')->format('d M Y H:i') }} · {{ $change->summary }} · {{ $change->actor?->name }}</p>
  @empty
    <div class="empty"><strong>No role or status changes yet</strong></div>
  @endforelse
</section>

@if ($canDeactivate)
  <section class="card" style="margin-top:0.9rem">
    <h2>Access</h2>
    @if ($person->deactivated_at)
      <form method="post" action="{{ route('portal.people.reactivate', $person) }}">@csrf<button type="submit">Restore sign-in</button></form>
    @else
      <form method="post" action="{{ route('portal.people.deactivate', $person) }}">@csrf<button type="submit">Revoke sign-in</button></form>
    @endif
    <form method="post" action="{{ route('portal.people.offboard', $person) }}" class="form-grid" style="margin-top:0.8rem">
      @csrf
      <div class="field"><label for="offboarded_on">Offboard date</label><input id="offboarded_on" name="offboarded_on" type="date" required></div>
      <div class="field"><label for="note">Note</label><input id="note" name="note"></div>
      <button type="submit">Start offboarding</button>
    </form>
  </section>
@endif
@endsection
