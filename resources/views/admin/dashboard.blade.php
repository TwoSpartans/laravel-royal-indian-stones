@extends('admin.layout')

@section('pageTitle', 'Dashboard')
@section('pageDescription', 'Overview of the catalogue.')

@section('content')
<div class="admin-stats">
  <a class="admin-stat" href="{{ route('admin.enquiries') }}">
    <span class="admin-stat-icon is-blue"><i class="bi bi-envelope"></i></span>
    <span class="admin-stat-value">{{ $enquiryCount }}</span>
    <span class="admin-stat-label">Messages</span>
  </a>
  <a class="admin-stat" href="{{ route('admin.products.index') }}">
    <span class="admin-stat-icon is-purple"><i class="bi bi-box-seam"></i></span>
    <span class="admin-stat-value">{{ $productCount }}</span>
    <span class="admin-stat-label">Products</span>
  </a>
  <a class="admin-stat" href="{{ route('admin.posts.index') }}">
    <span class="admin-stat-icon is-orange"><i class="bi bi-journal-text"></i></span>
    <span class="admin-stat-value">{{ $postCount }}</span>
    <span class="admin-stat-label">Blog posts</span>
  </a>
  <a class="admin-stat" href="{{ route('admin.newsletter') }}">
    <span class="admin-stat-icon is-green"><i class="bi bi-envelope-paper"></i></span>
    <span class="admin-stat-value">{{ $subscriberCount }}</span>
    <span class="admin-stat-label">Newsletter</span>
  </a>
</div>

<div class="admin-status-card">
  <div>
    <div class="admin-status-line">
      <h2>Comments waiting</h2>
      @if(($pendingCommentCount ?? 0) > 0)
        <span class="admin-pill is-waiting">{{ $pendingCommentCount }}</span>
      @else
        <span class="admin-pill is-clear">Clear</span>
      @endif
    </div>
    <p>Reader comments that are not on the site yet.</p>
  </div>
  <a href="{{ route('admin.comments.index') }}">Review comments</a>
</div>

<div class="admin-split">
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2>Recent messages</h2>
      <a href="{{ route('admin.enquiries') }}">View all</a>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Subject</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentEnquiries as $enquiry)
            <tr>
              <td><a href="{{ route('admin.enquiries') }}">{{ $enquiry->name }}</a></td>
              <td>{{ $enquiry->subject }}</td>
              <td>{{ $enquiry->created_at?->timezone('Asia/Kolkata')->format('d-m-Y') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="admin-empty">No messages yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2>Blog</h2>
      <a href="{{ route('admin.posts.index') }}">View all</a>
    </div>
    <div class="admin-list">
      @forelse($recentPosts as $post)
        <a class="admin-list-row" href="{{ route('admin.posts.edit', $post) }}">
          <span class="admin-list-mark">{{ strtoupper(mb_substr($post->title, 0, 1)) }}</span>
          <span class="admin-list-copy">
            <strong>{{ $post->title }}</strong>
            <small>{{ $post->published_on?->format('d-m-Y') ?? 'Undated' }}</small>
          </span>
        </a>
      @empty
        <p class="admin-empty">No articles yet.</p>
      @endforelse
    </div>
  </section>
</div>
@endsection
