<?php

namespace App\Http\Controllers\Portal;

use App\Services\Portal\AuditWriter;
use App\Services\Portal\ReportBuilder;
use App\Services\Portal\SimplePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends PortalController
{
    public function index(): View
    {
        $actor = $this->actor(request());
        abort_unless($this->access->canExport($actor), 403);

        return view('portal.reports.index');
    }

    public function export(Request $request, ReportBuilder $reports, SimplePdf $pdf, AuditWriter $audit): StreamedResponse|Response|RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canExport($actor), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['attendance', 'leave', 'overtime', 'reimbursements', 'headcount', 'tasks'])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        if ($to->lt($from) || (($to->getTimestamp() - $from->getTimestamp()) / 86400) > 93) {
            return back()->withErrors(['to' => 'Choose an end date within 93 days of the start date.'])->withInput();
        }

        $built = $reports->build($actor, $data['type'], $from->toDateString(), $to->toDateString());
        $audit->write($actor, 'report.exported', null, [
            'type' => $data['type'],
            'format' => $data['format'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ], $request->ip());

        $filename = 'royal-indian-stones-'.$data['type'].'-'.$from->toDateString().'.'.$data['format'];
        if ($data['format'] === 'pdf') {
            return response($pdf->render($built['title'], $built['headings'], $built['rows']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Robots-Tag' => 'noindex',
            ]);
        }

        return response()->streamDownload(function () use ($built, $reports): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, $built['headings']);
            foreach ($built['rows'] as $row) {
                fputcsv($handle, array_map(fn (string $cell) => $reports->csvCell($cell), $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
