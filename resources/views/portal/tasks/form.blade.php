@extends('portal.layout')
@section('title', 'Assign task')
@section('content')
<header class="page-head"><div><p class="eyebrow">Stone work</p><h1>{{ $task ? 'Edit task' : 'Add a task' }}</h1></div></header>
@php $role = auth()->user()->portal_role; @endphp
<p class="muted">{{ $task ? 'Only the employer can change a saved task.' : ($role === \App\Enums\PortalRole::Employee ? 'Assign this task to yourself. To change it later, add a new task.' : ($role?->isManagerLevel() ? 'Assign it to yourself or an employee. To change it later, add a new task.' : 'The saved record stays fixed unless the employer edits or deletes it.')) }}</p>
<form method="post" action="{{ $task ? route('portal.tasks.update', $task) : route('portal.tasks.store') }}" class="form-grid">
  @csrf
  @if ($task) @method('PUT') @endif
  <div class="field span-2"><label for="title">Title</label><input id="title" name="title" value="{{ old('title', $task?->title) }}" required></div>
  <div class="field"><label for="category">Category</label><select id="category" name="category" required>@foreach (config('portal.task_categories') as $value => $label)<option value="{{ $value }}" @selected(old('category', $task?->category) === $value)>{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="assignee_id">Assignee</label><select id="assignee_id" name="assignee_id" required>@foreach ($assignees as $person)<option value="{{ $person->id }}" @selected((int) old('assignee_id', $task?->assignee_id ?? auth()->id()) === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
  <div class="field"><label for="work_location_id">Location</label><select id="work_location_id" name="work_location_id"><option value="">Any</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) old('work_location_id', $task?->work_location_id) === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
  <div class="field"><label for="due_on">Due date</label><input id="due_on" name="due_on" type="date" value="{{ old('due_on', $task?->due_on?->toDateString()) }}"></div>
  <div class="field span-2"><label for="description">Details</label><textarea id="description" name="description">{{ old('description', $task?->description) }}</textarea></div>
  <button type="submit">{{ $task ? 'Save task' : 'Add task' }}</button>
</form>
@endsection
