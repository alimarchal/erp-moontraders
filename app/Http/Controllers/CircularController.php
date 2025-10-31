<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Circular;
use App\Models\Division;
use Illuminate\Http\Request;
use App\Helpers\FileStorageHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Alimarchal\IdGenerator\IdGenerator;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreCircularRequest;
use App\Http\Requests\UpdateCircularRequest;

/**
 * CircularController handles CRUD operations for circulars
 * Manages file uploads, division-based organization, and secure access
 */
class CircularController extends Controller
{
    /**
     * Display paginated list of circulars with filtering capabilities
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request, IdGenerator $idGenerator)
    {

        // Build query with filters using Spatie QueryBuilder
        $circulars = QueryBuilder::for(Circular::class)
            ->allowedFilters([
                AllowedFilter::exact('division_id'),           // Filter by division
                AllowedFilter::partial('circular_no'),         // Search by circular number
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                })
            ])
            ->with(['user', 'division', 'updatedBy'])          // Eager load relationships
            ->latest()                                                   // Order by newest first
            ->paginate(10);                                    // Paginate results

        return view('circulars.index', compact('circulars'));
    }

    /**
     * Show form to create new circular
     * 
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $divisions = Division::all(); // Get divisions for dropdown
        return view('circulars.create', compact('divisions'));
    }

    /**
     * Store new circular with file attachment
     * Uses transaction for data consistency
     * 
     * @param StoreCircularRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreCircularRequest $request)
    {
        // Start database transaction
        DB::beginTransaction();


        // Create folder path based on division name
        $folderName = 'Circulars/' . Division::find($request->division_id)->name;

        try {
            // Get validated data from form request
            $validated = $request->validated();
            //$circular_number_auto_generated = generateUniqueId('circular', 'circulars', 'circular_number');
            $validated['circular_number'] = generateUniqueId('circular', 'circulars', 'circular_number');



            // Handle file upload if attachment provided
            if ($request->hasFile('attachment')) {
                $validated['attachment'] = FileStorageHelper::storeSinglePrivateFile(
                    $request->file('attachment'),
                    $folderName,
                    $validated['circular_no'] ?? null  // Use circular number as subfolder
                );
            }

            // Create circular record in database
            $circular = Circular::create($validated);

            // Commit transaction if everything successful
            DB::commit();

            return redirect()
                ->route('circulars.index')
                ->with('success', "Circular '{$circular->title}' created successfully with number: {$circular->circular_no}");

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on database error
            DB::rollBack();

            // Handle duplicate circular number error
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'circular_no')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Circular number already exists. Please use a different number.');
            }

            // Handle other database errors
            return redirect()->back()
                ->withInput()
                ->with('error', 'Database error occurred. Please try again.');

        } catch (\Exception $e) {
            // Rollback transaction on any other error
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create circular. Please try again.');
        }
    }

    /**
     * Update existing circular with optional file replacement
     * Uses transaction for data consistency
     * 
     * @param UpdateCircularRequest $request
     * @param Circular $circular
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateCircularRequest $request, Circular $circular)
    {
        // Start database transaction
        DB::beginTransaction();

        // Create folder path based on division name
        $folderName = 'Circulars/' . Division::find($request->division_id)->name;

        try {
            // Get validated data from form request
            $validated = $request->validated();

            // Handle file replacement if new attachment uploaded
            if ($request->hasFile('attachment')) {
                // Delete old file if exists
                if ($circular->attachment) {
                    FileStorageHelper::deleteFile($circular->attachment);
                }




                // Handle file upload update if attachment provided
                if ($request->hasFile('attachment')) {
                    $validated['attachment'] = FileStorageHelper::storeSinglePrivateFile(
                        $request->file('attachment'),
                        $folderName,
                        $validated['circular_no'] ?? null  // Use circular number as subfolder
                    );
                }
            }

            // Update circular record
            $isUpdated = $circular->update($validated);

            // Check if any changes were actually made
            if (!$isUpdated) {
                DB::rollBack();
                return redirect()->back()
                    ->with('info', 'No changes were made to the circular.');
            }

            // Commit transaction if update successful
            DB::commit();

            return redirect()
                ->route('circulars.index')
                ->with('success', "Circular '{$circular->title}' updated successfully.");

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on database error
            DB::rollBack();

            // Handle duplicate circular number error
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'circular_no')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Circular number already exists. Please use a different number.');
            }

            // Log database errors for debugging
            Log::error('Database error updating circular', [
                'circular_id' => $circular->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Database error occurred. Please try again.');

        } catch (\Exception $e) {
            // Rollback transaction on any other error
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update circular. Please try again.');
        }
    }

    /**
     * Show form to edit existing circular
     * 
     * @param Circular $circular
     * @return \Illuminate\View\View
     */
    public function edit(Circular $circular)
    {
        $divisions = Division::all(); // Get divisions for dropdown
        $users = User::all();         // Get users for dropdown
        return view('circulars.edit', compact('circular', 'divisions', 'users'));
    }
}