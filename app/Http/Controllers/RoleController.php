<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {

        return [
            new Middleware('role_or_permission:view roles', only: ['index', 'show']),
            new Middleware('role_or_permission:create roles', only: ['create', 'store']),
            new Middleware('role_or_permission:edit roles', only: ['edit', 'update']),
            new Middleware('role_or_permission:delete roles', only: ['destroy']),
            new Middleware('role_or_permission:assign permissions', only: ['store', 'update']),
        ];
    }
    // Show the form for creating a new role
    public function create()
    {
        $permissions = Permission::all();
        return view('roles.create', compact('permissions'));
    }

    // Store a newly created role in storage
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'required|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        // Assign permissions to the role if provided
        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully with assigned permissions!');
    }

    // Display a listing of the roles with pagination
    public function index(Request $request)
    {
        $query = Role::with('permissions');

        // Apply filters based on request inputs
        if ($name = $request->input('filter.name')) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        if ($createdAt = $request->input('filter.created_at')) {
            $query->whereDate('created_at', $createdAt);
        }

        // Paginate the filtered results
        $roles = $query->paginate(10);

        // Return the view with roles data
        return view('roles.index', compact('roles'));
    }


    // Show the form for editing the specified role
    public function edit(Role $role)
    {
        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        return view('roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    // Update the specified role in storage
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'guard_name' => 'required|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->update([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        // Sync permissions - this will add/remove permissions as needed
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully with assigned permissions!');
    }

    // Remove the specified role from storage
    public function destroy(Role $role)
    {
        // Prevent deletion of super-admin role if users are assigned to it
        if ($role->name === 'super-admin' && $role->users->count() > 0) {
            return redirect()->back()->withErrors(['role' => 'Cannot delete super-admin role while users are assigned to it.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully!');
    }
}
