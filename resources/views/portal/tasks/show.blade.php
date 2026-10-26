@extends('portal.layout')
@section('title', $task->title)
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">{{ config('portal.task_categories.'.$task->category) }}</p>
    <h1>{{ $task->title }}</h1>
    <p class="muted">Assigned by {{ $task->owner?->name }} · Assignee {{ $task->assignee?->name }} · Due {{ $task->due_on?->format('d M Y') ?? 'not set' }}</p>
  </div>
  @if (app(\App\Services\Portal\Access::class)->canReviseTask(auth()->user()))
    <div class="btn-row">
      <a class="btn" href="{{ route('portal.tasks.edit', $task) }}">Edit</a>
      <form method="post" action="{{ route('portal.tasks.destroy', $task) }}">@csrf @method('DELETE')<button class="btn-secondary" type="submit">Delete</button></form>
    </div>
  @endif
</header>
<section class="card">
  <p>{{ $task->description ?: 'No extra detail.' }}</p>
  <p class="help">The title, assignee, and details stay as they were saved. Add a new task if the agreement changes.</p>
  @if (app(\App\Services\Portal\Access::class)->canUpdateTaskStatus(auth()->user(), $task))
  <form method="post" action="{{ route('portal.tasks.status', $task) }}" class="btn-row">
    @csrf
    <label for="status">Status</label>
    <select id="status" name="status">@foreach (['open','in_progress','blocked','done'] as $value)<option value="{{ $value }}" @selected($task->status === $value)>{{ str_replace('_', ' ', $value) }}</option>@endforeach</select>
    <button type="submit">Update status</button>
  </form>
  @endif
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Comments</h2>
  @forelse ($task->comments as $comment)
    <p><strong>{{ $comment->user?->name }}</strong> · {{ \App\Support\PortalTime::format($comment->created_at, auth()->user()) }}<br>{{ $comment->body }}</p>
  @empty
    <div class="empty"><strong>No comments yet</strong></div>
  @endforelse
  <form method="post" action="{{ route('portal.tasks.comments.store', $task) }}">
    @csrf
    <div class="field"><label for="body">Comment</label><textarea id="body" name="body" required></textarea></div>
    <button type="submit">Add comment</button>
  </form>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Attachments</h2>
  @foreach ($task->attachments as $attachment)
    <p><a href="{{ route('portal.attachments.download', $attachment) }}">{{ $attachment->original_name }}</a></p>
  @endforeach
  <form method="post" action="{{ route('portal.tasks.attachments.store', $task) }}" enctype="multipart/form-data">
    @csrf
    <div class="field"><label for="file">File</label><input id="file" name="file" type="file" required></div>
    <button type="submit">Attach</button>
  </form>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Activity</h2>
  @foreach ($task->activities as $activity)
    <p>{{ \App\Support\PortalTime::format($activity->created_at, auth()->user()) }} · {{ $activity->description }} · {{ $activity->user?->name }}</p>
  @endforeach
</section>
@endsection
