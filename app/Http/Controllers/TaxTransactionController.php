<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxTransactionRequest;
use App\Http\Requests\UpdateTaxTransactionRequest;
use App\Models\TaxTransaction;

class TaxTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxTransactionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxTransaction $taxTransaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxTransaction $taxTransaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxTransactionRequest $request, TaxTransaction $taxTransaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxTransaction $taxTransaction)
    {
        //
    }
}
