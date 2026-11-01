<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutreachIgnoredWarning;
use App\Models\Post;
use App\Models\Product;
use App\Services\Outreach\PageQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public function index(Request $request, PageQueue $pages): View
    {
        $gap = $request->string('gap')->toString();
        if (! array_key_exists($gap, PageQueue::GAPS)) {
            $gap = '';
        }
        $show = $request->string('show')->toString() === 'ignored' ? 'ignored' : 'open';

        $open = [];
        $ignored = [];
        $ignoredKeys = $this->ignoredKeys();
        foreach ($pages->warnings() as $warning) {
            $key = $warning['subject_type'].':'.$warning['subject_id'].':'.$warning['gap'];
            if (isset($ignoredKeys[$key])) {
                $ignored[] = $warning;
            } else {
                $open[] = $warning;
            }
        }

        $pool = $show === 'ignored' ? $ignored : $open;
        $counts = [];
        foreach ($pool as $warning) {
            $counts[$warning['gap']] = ($counts[$warning['gap']] ?? 0) + 1;
        }

        $visible = [];
        foreach ($pool as $warning) {
            if ($gap === '' || $warning['gap'] === $gap) {
                $visible[] = $warning;
            }
        }

        $rule = PageQueue::RULES[$gap] ?? 'Each row is one warning. The number on a filter is how many rows it shows.';
        $rule .= $show === 'ignored'
            ? ' Restore puts a warning back on the open list.'
            : ' Ignore a warning when the saved text should stay.';

        return view('admin.outreach.pages', [
            'warnings' => $visible,
            'openCount' => count($open),
            'ignoredCount' => count($ignored),
            'counts' => $counts,
            'gap' => $gap,
            'show' => $show,
            'gaps' => PageQueue::GAPS,
            'rule' => $rule,
        ]);
    }

    public function ignore(Request $request, PageQueue $pages): RedirectResponse
    {
        $data = $this->validatedWarning($request);
        abort_unless($this->warningExists($pages, $data['subject_type'], $data['subject_id'], $data['gap']), 404);

        OutreachIgnoredWarning::query()->firstOrCreate([
            'subject_type' => $data['subject_type'],
            'subject_id' => $data['subject_id'],
            'gap' => $data['gap'],
        ]);

        return $this->redirectToList($request)->with('status', 'Warning ignored.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $data = $this->validatedWarning($request);
        $warning = OutreachIgnoredWarning::query()
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->where('gap', $data['gap'])
            ->first();
        abort_if($warning === null, 404);
        $warning->delete();

        return $this->redirectToList($request)->with('status', 'Warning restored.');
    }

    /**
     * @return array{subject_type: string, subject_id: int, gap: string}
     */
    private function validatedWarning(Request $request): array
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:product,post'],
            'subject_id' => ['required', 'integer'],
            'gap' => ['required', 'string', Rule::in(array_keys(PageQueue::GAPS))],
        ], [
            'gap.in' => 'Choose a warning from the list.',
        ]);

        $exists = $data['subject_type'] === 'product'
            ? Product::query()->whereKey($data['subject_id'])->exists()
            : Post::query()->whereKey($data['subject_id'])->exists();
        abort_unless($exists, 404);

        return [
            'subject_type' => $data['subject_type'],
            'subject_id' => (int) $data['subject_id'],
            'gap' => $data['gap'],
        ];
    }

    private function warningExists(PageQueue $pages, string $type, int $id, string $gap): bool
    {
        foreach ($pages->warnings() as $warning) {
            if ($warning['subject_type'] === $type && $warning['subject_id'] === $id && $warning['gap'] === $gap) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function ignoredKeys(): array
    {
        $keys = [];
        $rows = OutreachIgnoredWarning::query()->get(['subject_type', 'subject_id', 'gap']);
        foreach ($rows as $row) {
            $keys[$row->subject_type.':'.$row->subject_id.':'.$row->gap] = true;
        }

        return $keys;
    }

    private function redirectToList(Request $request): RedirectResponse
    {
        $show = $request->string('show')->toString();
        $gap = $request->string('return_gap')->toString();

        return redirect()->route('admin.outreach.index', array_filter([
            'show' => $show === 'ignored' ? 'ignored' : null,
            'gap' => array_key_exists($gap, PageQueue::GAPS) ? $gap : null,
        ]));
    }
}
