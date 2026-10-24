<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInToPortal;
use Tests\TestCase;

class PortalSectionTest extends TestCase
{
    use RefreshDatabase;
    use SignsInToPortal;

    public function test_an_employee_only_sees_the_sections_the_admin_opened(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile?->forceFill(['visible_sections' => ['leave']])->save();

        $this->signIn($employee)->get('/portal')
            ->assertOk()
            ->assertDontSee(route('portal.attendance.index'), false)
            ->assertSee(route('portal.leave.index'), false);

        $this->signIn($employee)->get('/portal/attendance')
            ->assertRedirect('/portal')
            ->assertSessionHas('status', 'That section is not open for your account.');

        $this->signIn($employee)->get('/portal/leave')->assertOk();
    }
}
