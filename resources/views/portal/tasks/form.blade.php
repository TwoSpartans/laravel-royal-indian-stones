@extends('portal.layout')
@section('title', 'Assign task')
@section('content')
<header class="page-head"><div><p class="eyebrow">Stone work</p><h1>Assign a task</h1></div></header>
<form method="post" action="{{ route('portal.tasks.store') }}" class="form-grid">
  @csrf
  <div class="field span-2"><label for="title">Title</label><input id="title" name="title" required></div>
  <div class="field"><label for="category">Category</label><select id="category" name="category" required>@foreach (config('portal.task_categories') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="assignee_id">Assignee</label><select id="assignee_id" name="assignee_id" required>@foreach ($assignees as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
  <div class="field"><label for="work_location_id">Location</label><select id="work_location_id" name="work_location_id"><option value="">Any</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
  <div class="field"><label for="due_on">Due date</label><input id="due_on" name="due_on" type="date"></div>
  <div class="field span-2"><label for="description">Details</label><textarea id="description" name="description"></textarea></div>
  <button type="submit">Assign</button>
</form>
@endsection
