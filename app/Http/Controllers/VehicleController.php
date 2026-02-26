<?php

namespace App\Http\Controllers;

use App\Exports\VehicleExport;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:vehicle-list', only: ['index', 'show', 'exportPdf', 'exportExcel']),
            new Middleware('can:vehicle-create', only: ['create', 'store']),
            new Middleware('can:vehicle-edit', only: ['edit', 'update']),
            new Middleware('can:vehicle-delete', only: ['destroy']),
        ];
    }

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100, 250];

    private const DEFAULT_PER_PAGE = 40;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $vehicles = $this->buildFilteredQuery()
            ->paginate($perPage)
            ->withQueryString();

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
            'employeeOptions' => Employee::orderBy('name')->get(['id', 'name']),
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Export filtered vehicles to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery()->getEloquentBuilder();

        return Excel::download(new VehicleExport($query), 'vehicles.xlsx');
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
            $vehicles = $this->buildFilteredQuery()->getEloquentBuilder()->get();

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

    /**
     * Build the filtered query shared by index, export Excel, and export PDF.
     */
    private function buildFilteredQuery(): QueryBuilder
    {
        return QueryBuilder::for(Vehicle::query()->with(['employee', 'company', 'supplier']))
            ->allowedFilters([
                AllowedFilter::partial('vehicle_number'),
                AllowedFilter::partial('registration_number'),
                AllowedFilter::partial('vehicle_type'),
                AllowedFilter::partial('driver_name'),
                AllowedFilter::partial('make_model'),
                AllowedFilter::partial('driver_phone'),
                AllowedFilter::exact('year'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts([
                AllowedSort::field('vehicle_number'),
                AllowedSort::field('registration_number'),
                AllowedSort::field('vehicle_type'),
                AllowedSort::field('year'),
            ])
            ->defaultSort('id');
    }
}
