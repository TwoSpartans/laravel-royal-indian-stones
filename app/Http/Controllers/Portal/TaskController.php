<?php

namespace App\Http\Controllers\Portal;

use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkTask;
use App\Services\Portal\AuditWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $tasks = $this->visible($actor)
            ->with('assignee', 'owner')
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->string('q')->toString())).'%';
                $query->where('title', 'like', $like);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.tasks.index', [
            'tasks' => $tasks,
            'filters' => $request->only(['q', 'category', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canCreateTask($actor), 403);

        return view('portal.tasks.form', [
            'task' => null,
            'assignees' => $this->assignees($actor),
            'locations' => WorkLocation::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canCreateTask($actor), 403);
        $data = $this->validated($request, $actor);

        $task = WorkTask::query()->create([
            ...$data,
            'owner_id' => $actor->id,
            'status' => 'open',
        ]);
        TaskActivity::query()->create([
            'work_task_id' => $task->id,
            'user_id' => $actor->id,
            'description' => 'Task opened',
        ]);

        return redirect()->route('portal.tasks.show', $task)->with('status', 'Task assigned.');
    }

    public function show(Request $request, WorkTask $task): View
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canViewTask($actor, $task), 404);
        $task->load('owner', 'assignee', 'location', 'comments.user', 'activities.user', 'attachments');

        return view('portal.tasks.show', ['task' => $task]);
    }

    public function update(Request $request, WorkTask $task): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canViewTask($actor, $task), 404);
        abort_unless($this->access->canCreateTask($actor) || $task->assignee_id === $actor->id, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'blocked', 'done'])],
        ]);

        $task->forceFill(['status' => $data['status']])->save();
        TaskActivity::query()->create([
            'work_task_id' => $task->id,
            'user_id' => $actor->id,
            'description' => 'Status set to '.str_replace('_', ' ', $data['status']),
        ]);

        return back()->with('status', 'Task status saved.');
    }

    public function comment(Request $request, WorkTask $task): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canViewTask($actor, $task), 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        TaskComment::query()->create([
            'work_task_id' => $task->id,
            'user_id' => $actor->id,
            'body' => $data['body'],
        ]);
        TaskActivity::query()->create([
            'work_task_id' => $task->id,
            'user_id' => $actor->id,
            'description' => 'Comment added',
        ]);

        return back()->with('status', 'Comment added.');
    }

    public function attachment(Request $request, WorkTask $task, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canViewTask($actor, $task), 404);
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('tasks/'.$task->id, (string) str()->uuid().'.'.$file->extension(), 'portal');
        TaskAttachment::query()->create([
            'work_task_id' => $task->id,
            'uploaded_by' => $actor->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);
        TaskActivity::query()->create([
            'work_task_id' => $task->id,
            'user_id' => $actor->id,
            'description' => 'Attachment added',
        ]);
        $audit->write($actor, 'task.attachment_uploaded', $task, [], $request->ip());

        return back()->with('status', 'Attachment stored.');
    }

    public function download(Request $request, TaskAttachment $attachment): StreamedResponse
    {
        $actor = $this->actor($request);
        $task = $attachment->task;
        abort_unless($task instanceof WorkTask && $this->access->canViewTask($actor, $task), 404);
        abort_unless(Storage::disk('portal')->exists($attachment->path), 404);

        return Storage::disk('portal')->download($attachment->path, $attachment->original_name);
    }

    private function visible(User $actor): Builder
    {
        $query = WorkTask::query();
        if ($this->access->canAdminister($actor)) {
            return $query;
        }

        $ids = $this->access->peopleQuery($actor)->pluck('id')->push($actor->id)->unique()->all();

        return $query->where(function ($inner) use ($ids): void {
            $inner->whereIn('assignee_id', $ids)->orWhereIn('owner_id', $ids);
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function assignees(User $actor): Collection
    {
        if ($this->access->canAdminister($actor)) {
            return User::query()->whereNotNull('portal_role')->whereNull('deactivated_at')->orderBy('name')->get();
        }

        return $this->access->peopleQuery($actor)->whereNull('deactivated_at')->orderBy('name')->get()->push($actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, User $actor): array
    {
        $allowed = $this->assignees($actor)->modelKeys();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(config('portal.task_categories')))],
            'description' => ['nullable', 'string', 'max:4000'],
            'assignee_id' => ['required', 'integer', Rule::in($allowed)],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        return $data;
    }
}
