<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_an_employee_assigns_only_themselves_and_cannot_change_the_saved_task(): void
    {
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();

        $this->signIn($employee)->get('/portal/tasks')->assertOk()->assertSee('Add task');
        $this->signIn($employee)->post('/portal/tasks', $this->payload($manager->id))
            ->assertSessionHasErrors('assignee_id');
        $this->signIn($employee)->post('/portal/tasks', $this->payload($employee->id))
            ->assertRedirect();

        $task = WorkTask::query()->where('title', 'Pack the sample lot')->firstOrFail();
        $this->assertSame($employee->id, $task->owner_id);
        $this->assertSame($employee->id, $task->assignee_id);

        $this->signIn($employee)->put(route('portal.tasks.update', $task), $this->payload($employee->id))
            ->assertForbidden();
        $this->signIn($employee)->delete(route('portal.tasks.destroy', $task))
            ->assertForbidden();
        $this->assertNotNull(WorkTask::query()->find($task->id));
    }

    public function test_a_manager_assigns_an_employee_and_only_an_admin_can_delete_it(): void
    {
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->adminHr()->create();
        $employee = User::factory()->employee($manager)->create();
        $admin = User::factory()->employer()->create();

        $this->signIn($hr)->post('/portal/tasks', $this->payload($employee->id))->assertRedirect();
        $task = WorkTask::query()->where('owner_id', $hr->id)->firstOrFail();
        $this->signIn($hr)->delete(route('portal.tasks.destroy', $task))->assertForbidden();
        $this->signIn($manager)->delete(route('portal.tasks.destroy', $task))->assertForbidden();

        $this->signIn($admin)->get('/portal/tasks')->assertOk()->assertSee('Pack the sample lot')->assertSee($employee->name);
        $this->signIn($admin)->delete(route('portal.tasks.destroy', $task))->assertRedirect(route('portal.tasks.index'));
        $this->assertNull(WorkTask::query()->find($task->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $assigneeId): array
    {
        return [
            'title' => 'Pack the sample lot',
            'category' => 'packaging',
            'description' => 'Pack the buyer sample before dispatch.',
            'assignee_id' => $assigneeId,
        ];
    }
}
