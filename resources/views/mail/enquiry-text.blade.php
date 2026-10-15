Name: {{ $enquiry->name }}
Email: {{ $enquiry->email }}
@if($enquiry->product)
Product: {{ $enquiry->product }}
@endif
@if($enquiry->quantity)
Quantity: {{ $enquiry->quantity }}
@endif
@if($enquiry->dimensions)
Dimensions: {{ $enquiry->dimensions }}
@endif
@if($enquiry->finish)
Finish: {{ $enquiry->finish }}
@endif
@if($enquiry->destination)
Destination: {{ $enquiry->destination }}
@endif

Requirements:
{{ $enquiry->message }}
