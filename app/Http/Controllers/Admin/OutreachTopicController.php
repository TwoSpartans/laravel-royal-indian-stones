<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OutreachTopicIntent;
use App\Http\Controllers\Controller;
use App\Models\OutreachTopic;
use App\Support\OutreachPageAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutreachTopicController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.outreach.topics', [
            'topics' => OutreachTopic::query()->orderBy('phrase')->get(),
            'editing' => $this->editing($request),
            'intents' => OutreachTopicIntent::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OutreachTopic::query()->create($this->validated($request));

        return redirect()->route('admin.outreach.topics.index')->with('status', 'Topic saved.');
    }

    public function update(Request $request, OutreachTopic $topic): RedirectResponse
    {
        $topic->update($this->validated($request));

        return redirect()->route('admin.outreach.topics.index')->with('status', 'Topic updated.');
    }

    public function destroy(OutreachTopic $topic): RedirectResponse
    {
        $topic->delete();

        return redirect()->route('admin.outreach.topics.index')->with('status', 'Topic deleted.');
    }

    private function editing(Request $request): ?OutreachTopic
    {
        if (! $request->filled('edit')) {
            return null;
        }

        return OutreachTopic::query()->find($request->integer('edit'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'phrase' => ['required', 'string', 'max:160'],
            'intent' => ['required', Rule::enum(OutreachTopicIntent::class)],
            'target_url' => OutreachPageAddress::rules(),
            'country' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'phrase.required' => 'Write the phrase a buyer types.',
            'target_url.regex' => OutreachPageAddress::message(),
        ]);
    }
}
