<ul class="navbar-nav align-items-lg-center gap-2">
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.enquiries') }}">Message</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.newsletter') }}">Newsletter</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.posts.index') }}">Journal</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.post-categories.index') }}">Category</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.comments.index') }}">Comment</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.products.index') }}">Products</a></li>
  <li class="nav-item"><a class="btn btn-outline-light text-uppercase nav-link-btn" href="{{ route('admin.outreach.index') }}">Outreach</a></li>
  <li class="nav-item">
    <form method="post" action="{{ route('admin.logout') }}">
      @csrf
      <button class="btn btn-warning text-dark text-uppercase fw-bold nav-link-btn" type="submit"><i class="bi bi-box-arrow-right me-1"></i> Logout</button>
    </form>
  </li>
</ul>
