<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OutreachMentionStatus;
use App\Http\Controllers\Controller;
use App\Models\OutreachMention;
use App\Support\OutreachPageAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutreachMentionController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.outreach.mentions', [
            'mentions' => OutreachMention::query()->latest('updated_at')->get(),
            'editing' => $this->editing($request),
            'statuses' => OutreachMentionStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OutreachMention::query()->create($this->validated($request));

        return redirect()->route('admin.outreach.mentions.index')->with('status', 'Mention saved.');
    }

    public function update(Request $request, OutreachMention $mention): RedirectResponse
    {
        $mention->update($this->validated($request));

        return redirect()->route('admin.outreach.mentions.index')->with('status', 'Mention updated.');
    }

    public function destroy(OutreachMention $mention): RedirectResponse
    {
        $mention->delete();

        return redirect()->route('admin.outreach.mentions.index')->with('status', 'Mention deleted.');
    }

    private function editing(Request $request): ?OutreachMention
    {
        if (! $request->filled('edit')) {
            return null;
        }

        return OutreachMention::query()->find($request->integer('edit'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'publication' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'offered_url' => OutreachPageAddress::rules(),
            'status' => ['required', Rule::enum(OutreachMentionStatus::class)],
            'published_url' => ['nullable', 'string', 'max:255', 'url:http,https', 'required_if:status,published'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'publication.required' => 'Name the person or publication.',
            'offered_url.regex' => OutreachPageAddress::message(),
            'published_url.required_if' => 'Add the address where the piece was published.',
            'published_url.url' => 'Enter a full http address for the published piece.',
        ]);
    }
}
