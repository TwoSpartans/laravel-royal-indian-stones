@extends('admin.layout')

@section('pageTitle', 'Comments')
@section('pageDescription', 'Reader comments waiting for a decision.')

@section('content')
<form class="journal-filter" method="get" action="{{ route('admin.comments.index') }}">
  <label for="comment-status">Status</label>
  <select class="form-select" id="comment-status" name="status">
    <option value="" @selected($status === '')>All comments</option>
    <option value="pending" @selected($status === 'pending')>Pending</option>
    <option value="approved" @selected($status === 'approved')>Approved</option>
  </select>
  <button class="admin-primary-btn" type="submit">Find</button>
</form>
@forelse($comments as $comment)
  <article class="journal-comment">
    <div>
      <p><strong>{{ $comment->post?->title ?: 'Removed article' }}</strong></p>
      <p>{{ $comment->name }} · {{ $comment->email }}</p>
      <p>{{ $comment->approved ? 'Approved' : 'Pending' }}</p>
    </div>
    <div>
      <p>{{ $comment->body }}</p>
      <div class="journal-comment-actions">
        @unless($comment->approved)
          <form method="post" action="{{ route('admin.comments.approve', $comment) }}">
            @csrf
            <button class="admin-primary-btn" type="submit">Approve</button>
          </form>
        @endunless
        <form method="post" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('Delete this comment?')">
          @csrf
          @method('DELETE')
          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
        </form>
      </div>
    </div>
  </article>
@empty
  <p class="journal-empty">No comment matched.</p>
@endforelse
@endsection
