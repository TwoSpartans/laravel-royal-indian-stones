@extends('portal.layout')
@section('title', 'Notices')
@section('content')
<header class="page-head"><div><p class="eyebrow">Inbox</p><h1>Status notices</h1></div></header>
@if ($notifications->isEmpty())
  <div class="empty"><strong>No notices</strong><p>Leave, expense, and attendance decisions appear here.</p></div>
@endif
@foreach ($notifications as $notification)
  <article class="card" style="margin-bottom:0.7rem">
    <h2>{{ $notification->data['title'] ?? 'Notice' }}</h2>
    <p>{{ $notification->data['body'] ?? '' }}</p>
    <form method="post" action="{{ route('portal.notifications.read', $notification->id) }}">@csrf<button type="submit">Open</button></form>
  </article>
@endforeach
{{ $notifications->links() }}
@endsection
