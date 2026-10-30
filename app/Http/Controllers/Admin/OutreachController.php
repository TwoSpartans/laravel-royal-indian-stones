<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Outreach\PageQueue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public function index(Request $request, PageQueue $pages): View
    {
        $gap = $request->string('gap')->toString();
        if (! array_key_exists($gap, PageQueue::GAPS)) {
            $gap = '';
        }

        $rows = $pages->rows();
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row['reasons'] as $reason) {
                $counts[$reason['key']] = ($counts[$reason['key']] ?? 0) + 1;
            }
        }

        $visible = $gap === ''
            ? $rows
            : array_values(array_filter(
                $rows,
                fn (array $row): bool => collect($row['reasons'])->contains('key', $gap),
            ));

        return view('admin.outreach.pages', [
            'pages' => $visible,
            'total' => count($rows),
            'gap' => $gap,
            'counts' => $counts,
            'gaps' => PageQueue::GAPS,
        ]);
    }
}
