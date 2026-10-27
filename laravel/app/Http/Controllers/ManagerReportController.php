<?php

namespace App\Http\Controllers;

use App\Services\ManagerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerReportController extends Controller
{
    public function index(Request $request, ManagerReportService $reports): View|RedirectResponse
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        if (
            ! empty($validated['from_date'])
            && ! empty($validated['to_date'])
            && $validated['from_date'] > $validated['to_date']
        ) {
            return back()->withErrors(['to_date' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.']);
        }

        $report = $reports->summary($validated['from_date'] ?? null, $validated['to_date'] ?? null);

        return view('manager.reports.index', compact('report'));
    }

    public function export(Request $request, ManagerReportService $reports): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        if (
            ! empty($validated['from_date'])
            && ! empty($validated['to_date'])
            && $validated['from_date'] > $validated['to_date']
        ) {
            return back()->withErrors(['to_date' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.']);
        }

        $report = $reports->report($validated['from_date'] ?? null, $validated['to_date'] ?? null);
        $filename = sprintf('bao-cao-don-hang-%s-den-%s.xlsx', $report['from_date'], $report['to_date']);

        return response()->streamDownload(
            fn (): mixed => $reports->writeXlsx($report),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
