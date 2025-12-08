<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $statusOptions = ['1' => 'Active', '0' => 'Inactive'];

        $vehicles = QueryBuilder::for(Vehicle::query()->with(['employee', 'company', 'supplier']))
            ->allowedFilters([
                AllowedFilter::partial('vehicle_number'),
                AllowedFilter::partial('registration_number'),
                AllowedFilter::partial('vehicle_type'),
                AllowedFilter::partial('driver_name'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString();

        $employeeOptions = Employee::orderBy('name')->get(['id', 'name']);
        $companyOptions = Company::orderBy('company_name')->get(['id', 'company_name']);
        $supplierOptions = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'statusOptions' => $statusOptions,
            'employeeOptions' => $employeeOptions,
            'companyOptions' => $companyOptions,
            'supplierOptions' => $supplierOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $employeeOptions = Employee::orderBy('name')->get(['id', 'name']);
        $companyOptions = Company::orderBy('company_name')->get(['id', 'company_name']);
        $supplierOptions = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('vehicles.create', compact('employeeOptions', 'companyOptions', 'supplierOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['is_active'] = $request->has('is_active')
                ? $request->boolean('is_active')
                : true;

            $vehicle = Vehicle::create($validated);

            DB::commit();

            return redirect()
                ->route('vehicles.index')
                ->with('success', "Vehicle '{$vehicle->vehicle_number}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating vehicle', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create vehicle. Please review your input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'vehicles_vehicle_number_unique')) {
                    $message = 'The vehicle number must be unique.';
                } elseif (str_contains($e->getMessage(), 'vehicles_registration_number_unique')) {
                    $message = 'The registration number must be unique.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating vehicle', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create vehicle. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['employee', 'company', 'supplier', 'expenses']);

        return view('vehicles.show', compact('vehicle'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $employeeOptions = Employee::orderBy('name')->get(['id', 'name']);
        $companyOptions = Company::orderBy('company_name')->get(['id', 'company_name']);
        $supplierOptions = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('vehicles.edit', compact('vehicle', 'employeeOptions', 'companyOptions', 'supplierOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $validated['is_active'] = $request->has('is_active')
                ? $request->boolean('is_active')
                : $vehicle->is_active;

            $updated = $vehicle->update($validated);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the vehicle.');
            }

            DB::commit();

            return redirect()
                ->route('vehicles.index')
                ->with('success', "Vehicle '{$vehicle->vehicle_number}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating vehicle', [
                'vehicle_id' => $vehicle->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update vehicle. Please review your input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'vehicles_vehicle_number_unique')) {
                    $message = 'The vehicle number must be unique.';
                } elseif (str_contains($e->getMessage(), 'vehicles_registration_number_unique')) {
                    $message = 'The registration number must be unique.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating vehicle', [
                'vehicle_id' => $vehicle->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update vehicle. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        try {
            $vehicle->delete();

            return redirect()
                ->route('vehicles.index')
                ->with('success', "Vehicle '{$vehicle->vehicle_number}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting vehicle', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete vehicle. Please try again.');
        }
    }

    /**
     * Export vehicles list as PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            // Use the same filtering logic as index
            $vehicles = QueryBuilder::for(Vehicle::query()->with(['employee', 'company', 'supplier']))
                ->allowedFilters([
                    AllowedFilter::partial('vehicle_number'),
                    AllowedFilter::partial('registration_number'),
                    AllowedFilter::partial('vehicle_type'),
                    AllowedFilter::partial('driver_name'),
                    AllowedFilter::exact('company_id'),
                    AllowedFilter::exact('supplier_id'),
                    AllowedFilter::exact('employee_id'),
                    AllowedFilter::exact('is_active'),
                ])
                ->orderBy('id')
                ->get();

            $pdf = Pdf::loadView('vehicles.pdf', [
                'vehicles' => $vehicles,
                'generatedBy' => auth()->user()->name,
                'generatedAt' => now(),
                'filters' => $request->get('filter', []),
            ]);

            return $pdf->download('vehicles-list-'.now()->format('Y-m-d').'.pdf');
        } catch (\Throwable $e) {
            Log::error('Error generating vehicles PDF', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to generate PDF. Please try again.');
        }
    }
}
