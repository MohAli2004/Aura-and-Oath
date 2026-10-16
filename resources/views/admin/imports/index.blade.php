@extends('layouts.admin')
@section('heading', 'AI Bulk Product Import')
@section('title', 'AI Bulk Product Import')
@section('content')
<section class="max-w-3xl border border-beige bg-[#FFFCFA] p-5 sm:p-6">
        <p class="text-sm text-taupe leading-relaxed max-w-2xl">
            Upload a Gemini JSON file, or a CSV/XLSX file exported from this page. Products are staged for review and saved as drafts. AI text is not treated as verified, and nothing is published until the barcode, price, cost, stock, image, and content approval are complete.
        </p>
        <form method="POST" action="{{ route('admin.bulk-import.upload') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="label" for="file">JSON, CSV, or XLSX</label>
                <input id="file" class="input" type="file" name="file" accept=".json,.csv,.xlsx,application/json,text/csv" required>
                @error('file')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="force" value="1">
                Import again even if this exact file was uploaded before
            </label>
            <button class="btn btn-primary" type="submit">Check file</button>
        </form>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a class="underline" href="{{ route('admin.bulk-import.schema') }}">JSON schema 1.0.0</a>
            <a class="underline" href="{{ route('admin.bulk-import.example') }}">Example file</a>
            <a class="underline" href="{{ route('admin.bulk-import.editor') }}">Open the editor</a>
        </div>
    </section>

<div class="mt-8 flex flex-wrap gap-2">
    @foreach($filters as $key => $label)
        <a href="{{ route('admin.bulk-import.editor', ['filter' => $key]) }}" class="btn {{ $filter === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="mt-4 flex flex-wrap gap-2 text-sm">
    <a class="btn btn-secondary" href="{{ route('admin.bulk-import.export', ['format' => 'csv', 'filter' => $filter]) }}">Export CSV</a>
    <a class="btn btn-secondary" href="{{ route('admin.bulk-import.export', ['format' => 'xlsx', 'filter' => $filter]) }}">Export XLSX</a>
</div>

<section class="mt-8">
    <h2 class="font-display text-2xl mb-3">Import history</h2>
    <div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
        <table class="w-full text-sm">
            <thead class="bg-beige/40 text-left">
                <tr>
                    <th class="p-3">File</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Rows</th>
                    <th class="p-3">Saved</th>
                    <th class="p-3">Failed</th>
                    <th class="p-3">When</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($batches as $batch)
                <tr class="border-t border-beige">
                    <td class="p-3">{{ $batch->original_filename }}</td>
                    <td class="p-3"><x-badge>{{ $batch->status->label() }}</x-badge></td>
                    <td class="p-3">{{ $batch->total_rows }}</td>
                    <td class="p-3">{{ $batch->success_count }}</td>
                    <td class="p-3">{{ $batch->failure_count }}</td>
                    <td class="p-3">{{ $batch->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="p-3 text-right"><a class="underline" href="{{ route('admin.bulk-import.show', $batch) }}">Review</a></td>
                </tr>
            @empty
                <tr><td class="p-3 text-taupe" colspan="7">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$batches" noun="batch" />
</section>
@endsection
