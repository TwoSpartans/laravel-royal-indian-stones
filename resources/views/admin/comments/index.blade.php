@extends('admin.layout')

@section('pageTitle', 'Comments')
@section('pageDescription', 'Reader comments waiting for a decision.')

@section('content')
@forelse($comments as $comment)
  <div class="card mb-3 shadow-sm"><div class="card-body row">
    <div class="col-md-4">
      <p><strong>Article:</strong> {{ $comment->post?->title }}</p>
      <p><strong>Name:</strong> {{ $comment->name }}</p>
      <p><strong>Email:</strong> {{ $comment->email }}</p>
      <p><strong>Status:</strong> {{ $comment->approved ? 'Approved' : 'Waiting' }}</p>
    </div>
    <div class="col-md-8">
      <textarea class="form-control" rows="4" readonly>{{ $comment->body }}</textarea>
      <div class="mt-3 d-flex gap-2">
        @unless($comment->approved)
          <form method="post" action="{{ route('admin.comments.approve', $comment) }}">@csrf<button class="btn btn-sm btn-dark text-warning" type="submit">Approve</button></form>
        @endunless
        <form method="post" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('Delete this comment?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
      </div>
    </div>
  </div></div>
@empty
  <div class="alert alert-info text-center">No comments yet.</div>
@endforelse
@endsection
