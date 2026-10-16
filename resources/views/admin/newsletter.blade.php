@extends('admin.layout')

@section('pageTitle', 'Newsletter')
@section('pageDescription', 'Email addresses collected from the site.')

@section('content')
<div class="my-4 d-flex justify-content-end">
  <input type="search" id="searchInput" class="form-control admin-search" placeholder="Search email...">
</div>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-hover table-striped align-middle text-center">
    <thead class="table-dark">
      <tr><th style="width: 10%">#</th><th>Email Address</th><th style="width: 15%">Action</th></tr>
    </thead>
    <tbody id="newsletterTable">
      @forelse($subscribers as $subscriber)
        <tr>
          <td><span class="badge bg-secondary">{{ $loop->iteration }}</span></td>
          <td class="text-start ps-3">{{ $subscriber->email }}</td>
          <td>
            <form method="post" action="{{ route('admin.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Are you sure you want to delete this email?')">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash-fill me-1"></i> Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-muted py-4">No email addresses found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('searchInput').addEventListener('keyup', function () {
  const term = this.value.toLowerCase();
  document.querySelectorAll('#newsletterTable tr').forEach(function (row) {
    row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
  });
});
</script>
@endpush
