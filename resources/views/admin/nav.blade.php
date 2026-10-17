@php
  $messagesActive = request()->routeIs('admin.enquiries', 'admin.enquiries.*');
  $productsActive = request()->routeIs('admin.products.*');
  $postsActive = request()->routeIs('admin.posts.*');
  $categoriesActive = request()->routeIs('admin.post-categories.*');
  $commentsActive = request()->routeIs('admin.comments.*');
  $newsletterActive = request()->routeIs('admin.newsletter', 'admin.newsletter.*');
  $outreachActive = request()->routeIs('admin.outreach.*');
@endphp
<nav class="admin-nav" aria-label="Admin navigation">
  <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
    <i class="bi bi-speedometer2"></i>
    <span>Dashboard</span>
  </a>

  <details class="admin-nav-group" open>
    <summary>
      <span>Today</span>
      @if(($pendingCommentCount ?? 0) > 0)
        <span class="admin-count">{{ $pendingCommentCount > 99 ? '99+' : $pendingCommentCount }}</span>
      @endif
      <i class="bi bi-chevron-down"></i>
    </summary>
    <div>
      <a href="{{ route('admin.enquiries') }}" class="admin-nav-link {{ $messagesActive ? 'is-active' : '' }}" @if($messagesActive) aria-current="page" @endif>
        <i class="bi bi-envelope"></i>
        <span>Messages</span>
      </a>
    </div>
  </details>

  <details class="admin-nav-group" open>
    <summary>
      <span>Catalogue</span>
      <i class="bi bi-chevron-down"></i>
    </summary>
    <div>
      <a href="{{ route('admin.products.index') }}" class="admin-nav-link {{ $productsActive ? 'is-active' : '' }}" @if($productsActive) aria-current="page" @endif>
        <i class="bi bi-box-seam"></i>
        <span>Products</span>
      </a>
    </div>
  </details>

  <details class="admin-nav-group" open>
    <summary>
      <span>Content</span>
      <i class="bi bi-chevron-down"></i>
    </summary>
    <div>
      <a href="{{ route('admin.posts.index') }}" class="admin-nav-link {{ $postsActive ? 'is-active' : '' }}" @if($postsActive) aria-current="page" @endif>
        <i class="bi bi-journal-text"></i>
        <span>Blog</span>
      </a>
      <a href="{{ route('admin.post-categories.index') }}" class="admin-nav-link {{ $categoriesActive ? 'is-active' : '' }}" @if($categoriesActive) aria-current="page" @endif>
        <i class="bi bi-tags"></i>
        <span>Categories</span>
      </a>
      <a href="{{ route('admin.comments.index') }}" class="admin-nav-link {{ $commentsActive ? 'is-active' : '' }}" @if($commentsActive) aria-current="page" @endif>
        <i class="bi bi-chat-left-text"></i>
        <span>Comments</span>
        @if(($pendingCommentCount ?? 0) > 0)
          <span class="admin-count">{{ $pendingCommentCount > 99 ? '99+' : $pendingCommentCount }}</span>
        @endif
      </a>
    </div>
  </details>

  <details class="admin-nav-group" open>
    <summary>
      <span>Customers</span>
      <i class="bi bi-chevron-down"></i>
    </summary>
    <div>
      <a href="{{ route('admin.newsletter') }}" class="admin-nav-link {{ $newsletterActive ? 'is-active' : '' }}" @if($newsletterActive) aria-current="page" @endif>
        <i class="bi bi-envelope-paper"></i>
        <span>Newsletter</span>
      </a>
    </div>
  </details>

  <details class="admin-nav-group" open>
    <summary>
      <span>System</span>
      <i class="bi bi-chevron-down"></i>
    </summary>
    <div>
      <a href="{{ route('admin.outreach.index') }}" class="admin-nav-link {{ $outreachActive ? 'is-active' : '' }}" @if($outreachActive) aria-current="page" @endif>
        <i class="bi bi-send"></i>
        <span>Outreach</span>
      </a>
    </div>
  </details>
</nav>
