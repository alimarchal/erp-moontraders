<?php

namespace App\Http\Controllers;

use App\Models\PromotionalCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class PromotionalCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $campaigns = QueryBuilder::for(PromotionalCampaign::query())
            ->allowedFilters([
                AllowedFilter::partial('campaign_code'),
                AllowedFilter::partial('campaign_name'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::scope('start_date_from'),
                AllowedFilter::scope('end_date_to'),
            ])
            ->defaultSort('-start_date')
            ->paginate(20)
            ->withQueryString();

        return view('promotional-campaigns.index', compact('campaigns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('promotional-campaigns.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_code' => 'required|string|max:50|unique:promotional_campaigns',
            'campaign_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'discount_type' => 'required|in:percentage,fixed_amount,special_price,buy_x_get_y',
            'discount_value' => 'nullable|numeric|min:0',
            'buy_quantity' => 'required_if:discount_type,buy_x_get_y|nullable|numeric|min:1',
            'get_quantity' => 'required_if:discount_type,buy_x_get_y|nullable|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $campaign = PromotionalCampaign::create($validated);

        return redirect()
            ->route('promotional-campaigns.index')
            ->with('success', "Campaign '{$campaign->campaign_name}' created successfully.");
    }

    /**
     * Display the specified resource.
     */
    public function show(PromotionalCampaign $promotionalCampaign)
    {
        return view('promotional-campaigns.show', compact('promotionalCampaign'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PromotionalCampaign $promotionalCampaign)
    {
        return view('promotional-campaigns.edit', compact('promotionalCampaign'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PromotionalCampaign $promotionalCampaign)
    {
        $validated = $request->validate([
            'campaign_code' => 'required|string|max:50|unique:promotional_campaigns,campaign_code,' . $promotionalCampaign->id,
            'campaign_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'discount_type' => 'required|in:percentage,fixed_amount,special_price,buy_x_get_y',
            'discount_value' => 'nullable|numeric|min:0',
            'buy_quantity' => 'required_if:discount_type,buy_x_get_y|nullable|numeric|min:1',
            'get_quantity' => 'required_if:discount_type,buy_x_get_y|nullable|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $promotionalCampaign->update($validated);

        return redirect()
            ->route('promotional-campaigns.index')
            ->with('success', "Campaign '{$promotionalCampaign->campaign_name}' updated successfully.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PromotionalCampaign $promotionalCampaign)
    {
        $promotionalCampaign->delete();

        return redirect()
            ->route('promotional-campaigns.index')
            ->with('success', "Campaign '{$promotionalCampaign->campaign_name}' deleted successfully.");
    }
}
