<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OutreachProfileKind;
use App\Http\Controllers\Controller;
use App\Models\OutreachProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutreachProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.outreach.profiles', [
            'profiles' => OutreachProfile::query()->orderBy('name')->get(),
            'editing' => $this->editing($request),
            'kinds' => OutreachProfileKind::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OutreachProfile::query()->create($this->validated($request));

        return redirect()->route('admin.outreach.profiles.index')->with('status', 'Profile saved.');
    }

    public function update(Request $request, OutreachProfile $profile): RedirectResponse
    {
        $profile->update($this->validated($request));

        return redirect()->route('admin.outreach.profiles.index')->with('status', 'Profile updated.');
    }

    public function destroy(OutreachProfile $profile): RedirectResponse
    {
        $profile->delete();

        return redirect()->route('admin.outreach.profiles.index')->with('status', 'Profile deleted.');
    }

    private function editing(Request $request): ?OutreachProfile
    {
        if (! $request->filled('edit')) {
            return null;
        }

        return OutreachProfile::query()->find($request->integer('edit'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:255', 'url:http,https'],
            'kind' => ['required', Rule::enum(OutreachProfileKind::class)],
            'last_checked_on' => ['nullable', 'date'],
            'gap' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Name the profile.',
            'url.required' => 'Add the profile address.',
            'url.url' => 'Enter a full http address for the profile.',
        ]);
    }
}
