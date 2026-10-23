<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\OfficeNetwork;
use App\Models\RemoteWorkAuthorization;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeNetworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_classified_against_the_saved_office_range(): void
    {
        $employee = $this->employee();
        $this->network();

        $this->fromOffice()->post('/portal/login', $this->credentials($employee))->assertRedirect();
        $event = $this->latestLogin($employee);
        $this->assertSame('approved_office', $event->classification);
        $this->assertSame('approved office network', $event->classification_label);
        $this->assertSame('203.0.113.10', $event->ip_address);
    }

    public function test_an_address_outside_the_range_is_labelled_outside(): void
    {
        $employee = $this->employee();
        $this->network();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/portal/login', $this->credentials($employee));

        $event = $this->latestLogin($employee);
        $this->assertSame('outside_network', $event->classification);
        $this->assertSame('outside approved network', $event->classification_label);
    }

    public function test_missing_networks_are_unable_to_verify(): void
    {
        $employee = $this->employee();

        $this->fromOffice()->post('/portal/login', $this->credentials($employee));
        $event = $this->latestLogin($employee);
        $this->assertSame('unable_to_verify', $event->classification);
        $this->assertSame('unable to verify', $event->classification_label);
    }

    public function test_forwarded_addresses_are_ignored_unless_the_proxy_is_configured(): void
    {
        $employee = $this->employee();
        $this->network();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->post('/portal/login', $this->credentials($employee));

        $ignored = $this->latestLogin($employee);
        $this->assertSame('198.51.100.8', $ignored->ip_address);
        $this->assertFalse($ignored->forwarded_header_accepted);
        $this->assertSame('outside_network', $ignored->classification);

        $this->post('/portal/logout');
        config(['portal.trusted_proxies' => '198.51.100.8']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->post('/portal/login', $this->credentials($employee));

        $accepted = AccessEvent::query()->where('user_id', $employee->id)->where('event_type', 'login')->latest('id')->firstOrFail();
        $this->assertTrue($accepted->forwarded_header_accepted);
        $this->assertSame('203.0.113.9', $accepted->ip_address);
        $this->assertSame('approved_office', $accepted->classification);
    }

    public function test_remote_work_is_not_labelled_as_the_office(): void
    {
        $employee = $this->employee();
        $this->network();
        RemoteWorkAuthorization::query()->create([
            'user_id' => $employee->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'reason' => 'Buyer visit',
            'status' => 'approved',
            'approved_by' => $employee->id,
            'decided_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/portal/login', $this->credentials($employee));

        $event = $this->latestLogin($employee);
        $this->assertSame('remote_authorized', $event->classification);
        $this->assertSame('approved remote or field work', $event->classification_label);
        $this->assertNotSame('approved office network', $event->classification_label);
    }

    private function employee(): User
    {
        return User::factory()->employee()->create(['email' => fake()->unique()->safeEmail()]);
    }

    private function network(): void
    {
        OfficeNetwork::query()->create([
            'work_location_id' => WorkLocation::query()->where('name', 'Jodhpur Head Office')->value('id'),
            'label' => 'Office line',
            'cidr' => '203.0.113.0/24',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }

    private function fromOffice(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
    }

    private function latestLogin(User $user): AccessEvent
    {
        return AccessEvent::query()->where('user_id', $user->id)->where('event_type', 'login')->latest('id')->firstOrFail();
    }
}
