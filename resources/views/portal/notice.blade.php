@extends(empty($public) && auth()->check() ? 'portal.layout' : 'portal.guest')
@section('title', 'Data notice')
@section('content')
<h1>Staff data notice</h1>
@include('portal.partials.notice')
@if (empty($public) && auth()->check())
  <form method="post" action="{{ route('portal.notice.acknowledge') }}">
    @csrf
    <div class="field check">
      <input id="acknowledge" name="acknowledge" type="checkbox" value="1" required>
      <label for="acknowledge">I understand what is recorded and who can review it.</label>
    </div>
    <button type="submit">Continue</button>
  </form>
@else
  <p><a href="{{ route('portal.login') }}">Back to sign in</a></p>
@endif
@endsection
