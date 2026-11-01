@php $notes = $punch->shiftNotes(); @endphp
@if ($notes !== [])
  <dl class="shift-notes">
    @foreach ($notes as $note)
      <dt>{{ $note['label'] }}</dt>
      <dd>{{ $note['value'] }}</dd>
    @endforeach
  </dl>
@endif
