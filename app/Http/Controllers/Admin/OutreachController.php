<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public function index(): View
    {
        return view('admin.outreach.index', [
            'submissions' => SeoSubmission::query()->latest()->get(),
            'types' => SeoSubmission::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(SeoSubmission::TYPES))],
            'site_name' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        SeoSubmission::query()->create($data);

        return back()->with('status', 'Outreach note saved.');
    }

    public function destroy(SeoSubmission $submission): RedirectResponse
    {
        $submission->delete();

        return back()->with('status', 'Outreach note deleted.');
    }
}
