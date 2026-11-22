<?php

namespace App\Http\Controllers;

use App\Services\Ccr\CreateCcr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CcrController
{
    private function canCreate(Request $request): bool
    {
        return $request->user()->can('iar.create') || $request->user()->hasRole('super-admin');
    }

    public function index(Request $request): View
    {
        $records = DB::table('change_control_records')
            ->when(!$request->user()->hasRole('super-admin'), fn ($q) => $q->where('created_by', $request->user()->id))
            ->orderByDesc('id')->paginate(15);
        return view('ccr.index', compact('records'));
    }

    public function create(Request $request): View
    {
        abort_unless($this->canCreate($request), 403);
        $products = DB::table('products')->orderBy('product_name')->limit(500)->get(['id', 'sap_code', 'product_name']);
        return view('ccr.create', compact('products'));
    }

    public function store(Request $request, CreateCcr $creator): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $id = $creator->create($request->user(), $request->all());
        return redirect()->route('ccr.show', $id)->with('success', 'Change control draft created.');
    }

    public function show(Request $request, int $id): View
    {
        $record = DB::table('change_control_records')->where('id', $id)->first();
        abort_if(!$record, 404);
        abort_unless($record->created_by === $request->user()->id || $request->user()->hasRole('super-admin'), 403);
        $products = DB::table('ccr_products as cp')->join('products as p', 'p.id', '=', 'cp.product_id')
            ->where('cp.change_control_record_id', $id)->orderBy('p.product_name')
            ->get(['p.id', 'p.sap_code', 'p.product_name']);
        return view('ccr.show', compact('record', 'products'));
    }
}
