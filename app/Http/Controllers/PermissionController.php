<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('can:view permissions', only: ['index', 'show']),
            new Middleware('can:create permissions', only: ['create', 'store']),
            new Middleware('can:edit permissions', only: ['edit', 'update']),
            new Middleware('can:delete permissions', only: ['destroy']),
        ];
    }

    // Show the form for creating a new permission
    public function create()
    {
        return view('settings.permissions.create');
    }

    // Store a newly created permission in storage
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        $permission = DB::transaction(function () use ($request) {
            // Create the permission
            return Permission::create([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);
        });

        activity()
            ->performedOn($permission)
            ->event('created')
            ->withProperties(['name' => $permission->name])
            ->log('Created new permission');

        // Redirect with success message
        return redirect()->route('permissions.index')->with('success', 'Permission created successfully!');
    }

    // Display a listing of the permissions with pagination
    public function index(Request $request)
    {
        // Log the view activity
        activity()
            ->event('viewed_list')
            ->withProperties([
                'filters' => $request->get('filter', []),
                'page' => $request->get('page', 1),
            ])
            ->log('Viewed permission list');

        $query = Permission::with('roles');

        // Apply filters based on request inputs
        if ($name = $request->input('filter.name')) {
            $query->where('name', 'LIKE', '%'.$name.'%');
        }

        if ($createdAt = $request->input('filter.created_at')) {
            $query->whereDate('created_at', $createdAt);
        }

        // Paginate the filtered results
        $permissions = $query->paginate(10);

        // Return the view with permissions data
        return view('settings.permissions.index', compact('permissions'));
    }

    // Show the form for editing the specified permission
    public function edit(Permission $permission)
    {
        return view('settings.permissions.edit', compact('permission'));
    }

    // Update the specified permission in storage
    public function update(Request $request, Permission $permission)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,'.$permission->id,
        ]);

        DB::transaction(function () use ($permission, $request) {
            // Update the permission
            $permission->update([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);
        });

        // Redirect with success message
        return redirect()->route('permissions.index')->with('success', 'Permission updated successfully!');
    }

    // Remove the specified permission from storage
    public function destroy(Permission $permission)
    {
        // Prevent deletion of critical permissions
        $criticalPermissions = ['view users', 'edit users', 'create users', 'delete users', 'view roles', 'edit roles'];

        if (in_array($permission->name, $criticalPermissions)) {
            return redirect()->back()->withErrors(['permission' => 'Cannot delete critical system permission: '.$permission->name]);
        }

        DB::transaction(function () use ($permission) {
            // Delete the permission
            $permission->delete();
        });

        // Redirect with success message
        return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully!');
    }
}
