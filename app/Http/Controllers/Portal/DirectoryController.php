<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EmploymentStatus;
use App\Enums\PortalRole;
use App\Models\AccessEvent;
use App\Models\Department;
use App\Models\EmergencyContact;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\EmploymentChange;
use App\Models\LeaveBalance;
use App\Models\OnboardingItem;
use App\Models\PortalInvitation;
use App\Models\RegisteredDevice;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\ProvisionEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectoryController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canBrowseDirectory($actor), 403);

        $search = trim($request->string('q')->toString());
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
        $people = $this->access->peopleQuery($actor)
            ->with('profile.department', 'profile.location', 'profile.manager', 'profile.shift')
            ->when($search !== '', function ($query) use ($like): void {
                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('profile', function ($profile) use ($like): void {
                            $profile->where('employee_code', 'like', $like)
                                ->orWhere('job_title', 'like', $like);
                        });
                });
            })
            ->when($request->filled('department_id'), fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('work_location_id'), fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('work_location_id', $request->integer('work_location_id'))))
            ->when($request->filled('employment_status'), fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('employment_status', $request->string('employment_status')->toString())))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('portal.people.index', [
            'people' => $people,
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => WorkLocation::query()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'department_id', 'work_location_id', 'employment_status']),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->access->canAdminister($this->actor($request)), 403);

        return view('portal.people.form', $this->formData($request, null));
    }

    public function store(Request $request, ProvisionEmployee $provision): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $requestedRole = PortalRole::tryFrom($request->string('portal_role')->toString());
        abort_unless($requestedRole instanceof PortalRole && $this->access->canAssignRole($actor, $requestedRole), 403);
        $data = $this->validated($request, null);
        $role = PortalRole::from($data['portal_role']);
        abort_unless($this->access->canAssignRole($actor, $role), 403);

        $result = $provision->invite($actor, $data, $role, $request->ip());
        $status = $result['mailed']
            ? 'Invitation created and sent through the configured mailer.'
            : 'Invitation created. The email could not be sent, so copy the link below.';

        return redirect()
            ->route('portal.people.show', $result['user'])
            ->with('status', $status)
            ->with('invitation_url', $result['url']);
    }

    public function show(Request $request, int $member): View
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        $person->load('profile.department', 'profile.location', 'profile.manager', 'profile.shift');

        return view('portal.people.show', [
            'person' => $person,
            'contacts' => EmergencyContact::query()->where('user_id', $person->id)->orderByDesc('is_primary')->get(),
            'documents' => $this->access->canViewSensitive($actor, $person)
                ? EmployeeDocument::query()->where('user_id', $person->id)->orderByDesc('id')->get()
                : collect(),
            'checklist' => OnboardingItem::query()->where('user_id', $person->id)->orderBy('id')->get(),
            'changes' => EmploymentChange::query()->with('actor')->where('user_id', $person->id)->latest()->limit(20)->get(),
            'balance' => LeaveBalance::query()->with('type')->where('user_id', $person->id)->where('year', (int) now()->year)->whereHas('type', fn ($query) => $query->where('is_active', true))->first(),
            'salary' => $this->access->canViewSensitive($actor, $person)
                ? SalaryStructure::query()->where('user_id', $person->id)->orderByDesc('effective_on')->first()
                : null,
            'canEdit' => $this->access->canEditEmployment($actor, $person),
            'canSensitive' => $this->access->canViewSensitive($actor, $person),
            'canDeactivate' => $this->access->canDeactivate($actor, $person),
            'logins' => $this->access->canAdminister($actor)
                ? AccessEvent::query()->where('user_id', $person->id)->where('event_type', 'login')->latest('occurred_at')->limit(8)->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, int $member): View
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canEditEmployment($actor, $person), 403);

        return view('portal.people.form', $this->formData($request, $person));
    }

    public function update(Request $request, int $member, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canEditEmployment($actor, $person), 403);
        $data = $this->validated($request, $person);
        $role = PortalRole::from($data['portal_role']);
        abort_unless($person->portal_role === $role || $this->access->canAssignRole($actor, $role), 403);
        abort_if($person->portal_role === PortalRole::Employer && $role !== PortalRole::Employer, 403);

        DB::transaction(function () use ($actor, $person, $data, $role, $audit, $request): void {
            $profile = $person->profile;
            abort_unless($profile instanceof EmployeeProfile, 404);
            $before = [
                'role' => $person->portal_role?->value,
                'status' => $profile->employment_status?->value,
                'manager_id' => $profile->manager_id,
                'department_id' => $profile->department_id,
                'work_location_id' => $profile->work_location_id,
                'shift_id' => $profile->shift_id,
                'job_title' => $profile->job_title,
            ];

            $person->forceFill([
                'name' => $data['name'],
                'portal_role' => $role,
            ])->save();

            $status = EmploymentStatus::from($data['employment_status']);
            $profile->fill([
                'department_id' => $data['department_id'] ?? null,
                'work_location_id' => $data['work_location_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'job_title' => $data['job_title'],
                'employment_status' => $status,
                'joined_on' => $data['joined_on'] ?? null,
                'probation_ends_on' => $data['probation_ends_on'] ?? null,
                'phone' => $data['phone'] ?? null,
                'visible_sections' => $role === PortalRole::Employee ? array_values($data['visible_sections'] ?? []) : null,
            ]);

            if ($this->access->canViewSensitive($actor, $person)) {
                $profile->address = $data['address'] ?? null;
                $profile->identity_document_label = $data['identity_document_label'] ?? null;
                if (($data['identity_document_number'] ?? '') !== '') {
                    $profile->identity_document_number = $data['identity_document_number'];
                }
            }

            if (in_array($status, [EmploymentStatus::Inactive, EmploymentStatus::Offboarded], true)) {
                $person->forceFill(['deactivated_at' => $person->deactivated_at ?? now()])->save();
            }

            if (in_array($status, [EmploymentStatus::Active, EmploymentStatus::Probation], true)) {
                $person->forceFill(['deactivated_at' => null])->save();
            }

            $profile->save();
            EmploymentChange::query()->create([
                'user_id' => $person->id,
                'actor_id' => $actor->id,
                'summary' => 'Employment record updated',
                'properties' => ['before' => $before, 'after' => [
                    'role' => $role->value,
                    'status' => $status->value,
                    'manager_id' => $profile->manager_id,
                    'job_title' => $profile->job_title,
                ]],
            ]);
            $audit->write($actor, 'staff.updated', $person, ['role' => $role->value, 'status' => $status->value], $request->ip());
        });

        return redirect()->route('portal.people.show', $person)->with('status', 'Employment record saved.');
    }

    public function deactivate(Request $request, int $member, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canDeactivate($actor, $person), 403);

        $person->forceFill(['deactivated_at' => now()])->save();
        $audit->write($actor, 'staff.deactivated', $person, [], $request->ip());

        return back()->with('status', $person->name.' can no longer sign in.');
    }

    public function reactivate(Request $request, int $member, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canAdminister($actor), 403);
        abort_if($person->portal_role?->isManagerLevel() === true && $actor->portal_role?->isCompanyAdmin() !== true, 403);

        $person->forceFill(['deactivated_at' => null])->save();
        $person->profile?->forceFill(['employment_status' => EmploymentStatus::Active])->save();
        $audit->write($actor, 'staff.reactivated', $person, [], $request->ip());

        return back()->with('status', $person->name.' can sign in again.');
    }

    public function offboard(Request $request, int $member, ProvisionEmployee $provision, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canDeactivate($actor, $person), 403);
        $data = $request->validate([
            'offboarded_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $person->forceFill(['deactivated_at' => now()])->save();
        $person->profile?->forceFill([
            'employment_status' => EmploymentStatus::Offboarded,
            'offboarded_on' => $data['offboarded_on'],
        ])->save();
        RegisteredDevice::query()->where('user_id', $person->id)->where('status', '!=', 'revoked')->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
        PortalInvitation::query()->where('user_id', $person->id)->whereNull('accepted_at')->whereNull('revoked_at')->update([
            'revoked_at' => now(),
        ]);
        $provision->checklist($person, 'offboarding', config('portal.offboarding_items'));
        EmploymentChange::query()->create([
            'user_id' => $person->id,
            'actor_id' => $actor->id,
            'summary' => 'Offboarding started',
            'properties' => ['note' => $data['note'] ?? null],
        ]);
        $audit->write($actor, 'staff.offboarded', $person, [], $request->ip());

        return back()->with('status', 'Offboarding started and sign-in is revoked.');
    }

    public function contact(Request $request, int $member): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canViewUser($actor, $person), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            EmergencyContact::query()->where('user_id', $person->id)->update(['is_primary' => false]);
        }

        EmergencyContact::query()->create([
            'user_id' => $person->id,
            'name' => $data['name'],
            'relationship' => $data['relationship'],
            'phone' => $data['phone'],
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('status', 'Emergency contact saved.');
    }

    public function document(Request $request, int $member, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $member);
        abort_unless($this->access->canViewSensitive($actor, $person), 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(['identity', 'contract', 'certificate', 'other'])],
            'title' => ['required', 'string', 'max:160'],
            'expires_on' => ['nullable', 'date'],
            'identity_reference' => ['nullable', 'string', 'max:80'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $extension = $request->file('file')->extension();
        $path = $request->file('file')->storeAs(
            'documents/'.$person->id,
            (string) str()->uuid().'.'.$extension,
            'portal',
        );

        $months = (int) config('portal.retention.identity_documents_months_after_offboarding');
        EmployeeDocument::query()->create([
            'user_id' => $person->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'expires_on' => $data['expires_on'] ?? null,
            'retention_until' => now()->addMonths($months)->toDateString(),
            'uploaded_by' => $actor->id,
            'identity_reference' => $data['identity_reference'] ?? null,
        ]);
        $audit->write($actor, 'document.uploaded', $person, ['category' => $data['category'], 'title' => $data['title']], $request->ip());

        return back()->with('status', 'Document stored in the private staff files.');
    }

    public function downloadDocument(Request $request, EmployeeDocument $document, AuditWriter $audit): StreamedResponse
    {
        $actor = $this->actor($request);
        $owner = User::query()->find($document->user_id);
        abort_unless($owner instanceof User && $this->access->canViewSensitive($actor, $owner), 404);
        abort_unless(Storage::disk('portal')->exists($document->path), 404);
        $audit->write($actor, 'document.downloaded', $document, ['category' => $document->category], $request->ip());

        return Storage::disk('portal')->download($document->path, $document->original_name);
    }

    public function checklist(Request $request, OnboardingItem $item): RedirectResponse
    {
        $actor = $this->actor($request);
        $person = $this->person($actor, $item->user_id);
        abort_unless($this->access->canEditEmployment($actor, $person), 403);

        $complete = ! $item->is_complete;
        $item->forceFill([
            'is_complete' => $complete,
            'completed_at' => $complete ? now() : null,
            'completed_by' => $complete ? $actor->id : null,
        ])->save();

        return back()->with('status', 'Checklist updated.');
    }

    public function balance(Request $request, int $member, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $person = $this->person($actor, $member);
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'allocated' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);

        $balance = LeaveBalance::query()->firstOrCreate(
            [
                'user_id' => $person->id,
                'leave_type_id' => $data['leave_type_id'],
                'year' => (int) now()->year,
            ],
            ['allocated' => 0, 'used' => 0],
        );
        $balance->forceFill(['allocated' => $data['allocated']])->save();
        $audit->write($actor, 'leave.balance_adjusted', $balance, ['allocated' => $data['allocated']], $request->ip());

        return back()->with('status', 'Leave balance updated.');
    }

    private function person(User $actor, int $id): User
    {
        $person = $this->access->peopleQuery($actor)->whereKey($id)->first();
        abort_unless($person instanceof User, 404);

        return $person;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?User $person): array
    {
        $roles = [PortalRole::Employee, $person?->portal_role === PortalRole::AdminHr ? PortalRole::AdminHr : PortalRole::Manager];

        return [
            'person' => $person,
            'roles' => $roles,
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => WorkLocation::query()->orderBy('name')->get(),
            'shifts' => Shift::query()->orderBy('name')->get(),
            'managers' => User::query()->whereIn('portal_role', [PortalRole::Manager->value, PortalRole::AdminHr->value])->whereNull('deactivated_at')->orderBy('name')->get(),
            'statuses' => EmploymentStatus::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $person): array
    {
        $allowedRoles = ['employee', 'manager', 'admin_hr'];
        if ($person?->portal_role === PortalRole::Employer) {
            $allowedRoles[] = 'employer';
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($person?->id)],
            'portal_role' => ['required', Rule::in($allowedRoles)],
            'job_title' => ['required', 'string', 'max:120'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'manager_id' => [
                Rule::requiredIf(fn () => $request->input('portal_role') === 'employee'),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('portal_role', [PortalRole::Manager->value, PortalRole::AdminHr->value])->whereNull('deactivated_at')),
                function (string $attribute, mixed $value, \Closure $fail) use ($person): void {
                    if ($person !== null && (int) $value === $person->id) {
                        $fail('A person cannot be their own manager.');
                    }
                },
            ],
            'joined_on' => ['required', 'date'],
            'probation_ends_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'phone' => ['nullable', 'string', 'max:30'],
            'employment_status' => [$person === null ? 'nullable' : 'required', Rule::in(array_map(fn (EmploymentStatus $status) => $status->value, EmploymentStatus::cases()))],
            'address' => ['nullable', 'string', 'max:500'],
            'identity_document_label' => ['nullable', 'string', 'max:80'],
            'identity_document_number' => ['nullable', 'string', 'max:80'],
            'visible_sections' => ['nullable', 'array'],
            'visible_sections.*' => ['string', Rule::in(array_keys(config('portal.employee_sections')))],
        ], [
            'email.unique' => 'A staff account with this email already exists.',
            'manager_id.required' => 'Choose the manager for this employee.',
        ]);

        if ($request->boolean('sections_chosen')) {
            $data['visible_sections'] = array_values($request->input('visible_sections', []));
        }
        $data['email'] = strtolower($data['email']);
        $data['employment_status'] = $data['employment_status'] ?? EmploymentStatus::Invited->value;

        if ($person === null) {
            unset($data['email']);
            $data['email'] = strtolower($request->string('email')->toString());
        }

        return $data;
    }
}
