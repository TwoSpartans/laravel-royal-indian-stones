<?php

namespace App\Services\Portal;

use App\Enums\PortalRole;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public function role(User $user): ?PortalRole
    {
        return $user->portal_role;
    }

    public function isActive(User $user): bool
    {
        return $user->portal_role !== null && $user->deactivated_at === null;
    }

    public function canAdminister(User $user): bool
    {
        return $this->isActive($user) && ($this->role($user)?->canAdminister() ?? false);
    }

    public function canBrowseDirectory(User $user): bool
    {
        return $this->canAdminister($user) || $this->role($user) === PortalRole::Manager;
    }

    public function canAssignRole(User $actor, PortalRole $role): bool
    {
        return $this->canAdminister($actor) && $role !== PortalRole::Employer;
    }

    public function canDeactivate(User $actor, User $subject): bool
    {
        if ($actor->is($subject) || ! $this->canAdminister($actor) || $subject->portal_role === null) {
            return false;
        }

        $subjectRole = $this->role($subject);
        if (in_array($subjectRole, [PortalRole::Employer, PortalRole::AdminHr, PortalRole::Manager], true) && $this->role($actor) !== PortalRole::Employer) {
            return false;
        }

        if ($subjectRole === PortalRole::Employer) {
            return User::query()
                ->where('portal_role', PortalRole::Employer->value)
                ->whereNull('deactivated_at')
                ->whereKeyNot($subject->id)
                ->exists();
        }

        return true;
    }

    /**
     * @return Builder<User>
     */
    public function peopleQuery(User $actor): Builder
    {
        $query = User::query()->whereNotNull('portal_role');

        if ($this->canAdminister($actor)) {
            return $query;
        }

        if ($this->role($actor) === PortalRole::Manager) {
            return $query->whereHas('profile', fn (Builder $profile) => $profile->where('manager_id', $actor->id));
        }

        return $query->whereKey($actor->id);
    }

    public function canViewUser(User $actor, User $subject): bool
    {
        if (! $this->isActive($actor) || $subject->portal_role === null) {
            return false;
        }

        return $this->peopleQuery($actor)->whereKey($subject->id)->exists();
    }

    public function canViewSensitive(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return $this->isActive($actor);
        }

        return $this->canAdminister($actor) && $subject->portal_role !== null;
    }

    public function canEditEmployment(User $actor, User $subject): bool
    {
        return $this->canAdminister($actor) && $subject->portal_role !== null && $this->canViewUser($actor, $subject);
    }

    public function canDecideLeave(User $actor, LeaveRequest $leave): bool
    {
        if (! $this->isActive($actor) || $actor->is($leave->user) || ! in_array($leave->status, ['pending_manager', 'pending_hr'], true)) {
            return false;
        }

        if ($this->canAdminister($actor)) {
            return true;
        }

        return $leave->status === 'pending_manager'
            && $this->role($actor) === PortalRole::Manager
            && $leave->user?->profile?->manager_id === $actor->id;
    }

    public function canDecideExpense(User $actor, ExpenseClaim $claim): bool
    {
        if (! $this->isActive($actor) || $actor->is($claim->user)) {
            return false;
        }

        if ($claim->status === 'submitted') {
            return $this->canAdminister($actor)
                || ($this->role($actor) === PortalRole::Manager && $claim->user?->profile?->manager_id === $actor->id);
        }

        return $claim->status === 'pending_hr' && $this->canAdminister($actor);
    }

    public function canRecordPayment(User $actor, ExpenseClaim $claim): bool
    {
        return $this->canAdminister($actor) && $claim->status === 'approved' && $claim->payment_state === 'none';
    }

    public function canViewTask(User $actor, WorkTask $task): bool
    {
        if (! $this->isActive($actor)) {
            return false;
        }

        if ($this->canAdminister($actor) || $task->owner_id === $actor->id || $task->assignee_id === $actor->id) {
            return true;
        }

        if ($this->role($actor) !== PortalRole::Manager) {
            return false;
        }

        return $this->peopleQuery($actor)->whereKey($task->assignee_id)->exists()
            || $this->peopleQuery($actor)->whereKey($task->owner_id)->exists();
    }

    public function canCreateTask(User $actor): bool
    {
        return $this->isActive($actor) && $this->role($actor) !== PortalRole::Employee;
    }

    public function canExport(User $actor): bool
    {
        return $this->canBrowseDirectory($actor);
    }

    /**
     * @return list<int>|null
     */
    public function reportUserIds(User $actor): ?array
    {
        if ($this->canAdminister($actor)) {
            return null;
        }

        if ($this->role($actor) === PortalRole::Manager) {
            return $this->peopleQuery($actor)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [$actor->id];
    }
}
