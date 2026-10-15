@extends('admin.layout')

@section('content')
<h2 class="text-center text-uppercase mb-4">Messages</h2>
@if($enquiries->isEmpty())
  <div class="alert alert-info text-center">No messages found.</div>
@endif
@foreach($enquiries as $enquiry)
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <h5 class="text-secondary mb-3">Sender Details</h5>
          <p><strong>Name:</strong> {{ $enquiry->name }}</p>
          <p><strong>Subject:</strong> {{ $enquiry->subject }}</p>
          <p><strong>Email:</strong> {{ $enquiry->email }}</p>
          <p><strong>Date:</strong> {{ $enquiry->created_at?->timezone('Asia/Kolkata')->format('d-m-Y') }}</p>
          @if($enquiry->product)<p><strong>Product:</strong> {{ $enquiry->product }}</p>@endif
          @if($enquiry->quantity)<p><strong>Quantity:</strong> {{ $enquiry->quantity }}</p>@endif
          @if($enquiry->dimensions)<p><strong>Dimensions:</strong> {{ $enquiry->dimensions }}</p>@endif
          @if($enquiry->finish)<p><strong>Finish:</strong> {{ $enquiry->finish }}</p>@endif
          @if($enquiry->destination)<p><strong>Destination:</strong> {{ $enquiry->destination }}</p>@endif
          <form method="post" action="{{ route('admin.enquiries.destroy', $enquiry) }}" class="mt-3" onsubmit="return confirm('Are you sure you want to delete this message?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash-fill me-1"></i> Delete</button>
          </form>
        </div>
        <div class="col-md-8">
          <h5 class="text-center text-decoration-underline">Message</h5>
          <textarea class="form-control mt-3" rows="7" readonly>{{ $enquiry->message }}</textarea>
        </div>
      </div>
    </div>
  </div>
@endforeach
@endsection
