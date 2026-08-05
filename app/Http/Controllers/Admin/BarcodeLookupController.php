<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodeLookupController extends Controller
{
    public function __construct(protected BarcodeService $barcodes) {}

    public function index(Request $request): View
    {
        $result = null;
        $format = null;
        $barcode = $request->get('barcode');

        if ($barcode) {
            $result = $this->barcodes->lookup($barcode);
            $format = $this->barcodes->detectFormat($barcode);
        }

        return view('admin.barcodes.lookup', compact('result', 'barcode', 'format'));
    }

    public function labels(Request $request): View
    {
        $barcodes = collect(explode(',', (string) $request->get('codes', '')))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->map(fn ($c) => $this->barcodes->lookup($c))
            ->filter();

        return view('admin.barcodes.labels', compact('barcodes'));
    }
}
