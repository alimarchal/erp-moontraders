<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:role-list', only: ['index', 'show']),
            new Middleware('permission:role-create', only: ['create', 'store']),
            new Middleware('permission:role-edit', only: ['edit', 'update']),
            new Middleware('permission:role-delete', only: ['destroy']),
            new Middleware('permission:role-sync', only: ['store', 'update']),
        ];
    }

    // Show the form for creating a new role
    public function create()
    {
        $permissions = Permission::all();

        return view('settings.roles.create', compact('permissions'));
    }

    // Store a newly created role in storage
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'required|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            // Assign permissions to the role if provided - Ensure IDs are passed as integers
            if ($request->filled('permissions')) {
                $permissionIds = collect($request->permissions)->map(fn ($id) => (int) $id)->toArray();
                $role->syncPermissions($permissionIds);
            }

            return $role;
        });

        activity()
            ->performedOn($role)
            ->event('created')
            ->withProperties(['name' => $role->name])
            ->log('Created new role');

        return redirect()->route('roles.index')->with('success', 'Role created successfully with assigned permissions!');
    }

    // Display a listing of the roles with pagination
    public function index(Request $request)
    {
        // Log the view activity
        activity()
            ->event('viewed_list')
            ->withProperties([
                'filters' => $request->get('filter', []),
                'page' => $request->get('page', 1),
            ])
            ->log('Viewed role list');

        $query = Role::with('permissions');

        // Apply filters based on request inputs
        if ($name = $request->input('filter.name')) {
            $query->where('name', 'LIKE', '%'.$name.'%');
        }

        if ($createdAt = $request->input('filter.created_at')) {
            $query->whereDate('created_at', $createdAt);
        }

        // Paginate the filtered results
        $roles = $query->paginate(10);

        // Return the view with roles data
        return view('settings.roles.index', compact('roles'));
    }

    // Display the specified role
    public function show(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('settings.roles.show', compact('role', 'permissions', 'rolePermissions'));
    }

    // Show the form for editing the specified role
    public function edit(Role $role)
    {
        // Protection: Optional, but maybe avoid editing super-admin name
        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('settings.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    // Update the specified role in storage
    public function update(Request $request, Role $role)
    {
        // Protection for super-admin
        if ($role->name === 'super-admin' && $request->name !== 'super-admin') {
            return redirect()->back()->withErrors(['name' => 'The super-admin role name cannot be changed.']);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'guard_name' => 'required|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::transaction(function () use ($role, $request) {
            $role->update([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            // Sync permissions - Ensure IDs are passed as integers for Spatie to recognize them correctly
            if ($request->has('permissions')) {
                $permissionIds = collect($request->permissions)->map(fn ($id) => (int) $id)->toArray();
                $role->syncPermissions($permissionIds);
            } else {
                $role->syncPermissions([]);
            }
        });

        activity()
            ->performedOn($role)
            ->event('updated')
            ->withProperties(['name' => $role->name])
            ->log('Updated role permissions');

        return redirect()->route('roles.index')->with('success', 'Role updated successfully with assigned permissions!');
    }

    // Remove the specified role from storage
    public function destroy(Role $role)
    {
        // Protection for super-admin
        if ($role->name === 'super-admin') {
            return redirect()->back()->withErrors(['role' => 'The super-admin role cannot be deleted.']);
        }

        DB::transaction(function () use ($role) {
            $role->delete();
        });

        activity()
            ->event('deleted')
            ->withProperties(['name' => $role->name])
            ->log('Deleted role');

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully!');
    }
}
