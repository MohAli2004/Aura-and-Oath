<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(): View
    {
        return view('admin.reports.index', [
            'stats' => $this->reports->dashboardStats(),
            'sales' => $this->reports->salesByDay(30),
            'topProducts' => $this->reports->topProducts(15),
        ]);
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        $from = $request->date('from');
        $to = $request->date('to');

        return $this->reports->exportOrdersCsv($from, $to);
    }
}
