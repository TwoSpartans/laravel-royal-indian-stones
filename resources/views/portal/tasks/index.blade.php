@extends('portal.layout')
@section('title', 'Tasks')
@section('content')
<header class="page-head">
  <div><p class="eyebrow">Stone work</p><h1>Tasks</h1><p class="muted">Add the work you agreed to do. The record stays as it was saved. The employer can edit or delete it.</p></div>
  <a class="btn" href="{{ route('portal.tasks.create') }}">Add task</a>
</header>
<form class="filters" method="get">
  <div class="field"><label for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
  <div class="field"><label for="category">Category</label>
    <select id="category" name="category"><option value="">All</option>@foreach (config('portal.task_categories') as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status"><option value="">All</option>@foreach (['open','in_progress','blocked','done'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ str_replace('_', ' ', $value) }}</option>@endforeach</select>
  </div>
  <button type="submit">Filter</button>
</form>
<div class="table-wrap">
  @if ($tasks->isEmpty())
    <div class="empty"><strong>No tasks in this view</strong><p>Assign sourcing, inspection, packing, dispatch, or export paperwork.</p></div>
  @else
    <table>
      <thead><tr><th>Task</th><th>Category</th><th>Assigned by</th><th>Assignee</th><th>Due</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($tasks as $task)
          <tr>
            <td data-label="Task"><a href="{{ route('portal.tasks.show', $task) }}">{{ $task->title }}</a></td>
            <td data-label="Category">{{ config('portal.task_categories.'.$task->category) }}</td>
            <td data-label="Assigned by">{{ $task->owner?->name }}</td>
            <td data-label="Assignee">{{ $task->assignee?->name }}</td>
            <td data-label="Due">{{ $task->due_on?->format('d M Y') ?? '—' }}</td>
            <td data-label="Status">{{ str_replace('_', ' ', $task->status) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
