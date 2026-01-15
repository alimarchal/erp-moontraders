<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view users', only: ['index', 'show']),
            new Middleware('can:create users', only: ['create', 'store']),
            new Middleware('can:edit users', only: ['edit', 'update']),
            new Middleware('can:delete users', only: ['destroy']),
            new Middleware('can:assign permissions', only: ['store', 'update']),
        ];
    }

    public function index(Request $request)
    {
        // Log the view activity
        activity()
            ->event('viewed_list')
            ->withProperties([
                'filters' => $request->get('filter', []),
                'sort' => $request->get('sort'),
                'page' => $request->get('page', 1),
            ])
            ->log('Viewed user list');

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(User::getAllowedFilters())
            ->allowedSorts(User::getAllowedSorts())
            ->allowedIncludes(User::getAllowedIncludes())
            ->with(['roles.permissions', 'permissions'])
            ->defaultSort('-created_at')
            ->paginate(request('per_page', 10))
            ->appends(request()->query());

        return view('settings.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        $permissions = Permission::all();

        return view('settings.users.create', compact('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'is_super_admin' => 'required|in:Yes,No',
            'is_active' => 'required|in:Yes,No',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Security: Only super-admins can create other super-admins
        $isSuperAdmin = $request->is_super_admin;
        if ($isSuperAdmin === 'Yes' && ! (auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'))) {
            $isSuperAdmin = 'No';
        }

        DB::transaction(function () use ($request, $isSuperAdmin) {
            $user = User::create([
                'name' => $request->name,
                'designation' => $request->designation,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_super_admin' => $isSuperAdmin,
                'is_active' => $request->is_active,
            ]);

            // Assign roles if provided (convert IDs to names for spatie/permission)
            if ($request->filled('roles')) {
                $roleNames = Role::whereIn('id', $request->roles)->pluck('name')->toArray();
                $user->syncRoles($roleNames);
            }

            // Assign individual permissions if provided (convert IDs to names)
            if ($request->filled('permissions')) {
                $permissionNames = Permission::whereIn('id', $request->permissions)->pluck('name')->toArray();
                $user->syncPermissions($permissionNames);
            }
        });

        return redirect()->route('users.index')->with('success', 'User created successfully with assigned roles and permissions.');
    }

    public function show(User $user)
    {
        $user->load(['roles.permissions', 'permissions']);

        return view('settings.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $permissions = Permission::all();
        $userRoles = $user->roles->pluck('id')->toArray();
        $userPermissions = $user->permissions->pluck('id')->toArray();
        $inheritedPermissions = $user->getPermissionsViaRoles()->pluck('id')->toArray();

        return view('settings.users.edit', compact('user', 'roles', 'permissions', 'userRoles', 'userPermissions', 'inheritedPermissions'));
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        $ids = $request->ids;
        $action = $request->action;

        // Prevent self-action on current user
        $ids = array_filter($ids, fn($id) => (int)$id !== auth()->id());

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No valid users selected for this action.');
        }

        DB::transaction(function () use ($ids, $action) {
            if ($action === 'activate') {
                User::whereIn('id', $ids)->update(['is_active' => 'Yes']);
            } elseif ($action === 'deactivate') {
                User::whereIn('id', $ids)->update(['is_active' => 'No']);
            } elseif ($action === 'delete') {
                // Safeguard: Do not delete last super-admin
                $superAdminCount = User::role('super-admin')->count();
                $usersToDelete = User::whereIn('id', $ids)->get();
                foreach ($usersToDelete as $user) {
                    if ($user->hasRole('super-admin') && $superAdminCount <= 1) {
                        continue; // Skip deleting last super admin
                    }
                    $user->delete();
                }
            }
        });

        $message = "Users updated successfully.";
        if ($action === 'delete') {
            $message = "Users deleted successfully (except any protected accounts).";
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function update(Request $request, User $user)
    {
        // Prevent self-deletion protection
        if ($user->id === auth()->id() && $request->is_active === 'No') {
            return redirect()->back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        // Prevent removal of super-admin role from a super-admin user
        if ($user->hasRole('super-admin')) {
            $submittedRoleIds = $request->input('roles', []);
            $superAdminRole = Role::where('name', 'super-admin')->first();
            if ($superAdminRole && (! in_array($superAdminRole->id, $submittedRoleIds))) {
                return redirect()->back()->withErrors(['roles' => 'You cannot remove the super-admin role from a super-admin user.']);
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'is_super_admin' => 'required|in:Yes,No',
            'is_active' => 'required|in:Yes,No',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Security: Only super-admins can change super-admin status
        $isSuperAdmin = $request->is_super_admin;
        if ($isSuperAdmin !== $user->is_super_admin && ! (auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'))) {
            $isSuperAdmin = $user->is_super_admin;
        }

        $updateData = [
            'name' => $request->name,
            'designation' => $request->designation,
            'email' => $request->email,
            'is_super_admin' => $isSuperAdmin,
            'is_active' => $request->is_active,
        ];

        // Update password only if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($user, $updateData, $request) {
            $user->update($updateData);

            // Sync roles (IDs -> names) if provided, otherwise clear all roles
            if ($request->has('roles')) {
                $roleIds = $request->roles ?? [];
                $roleNames = empty($roleIds) ? [] : Role::whereIn('id', $roleIds)->pluck('name')->toArray();
                $user->syncRoles($roleNames);
            } else {
                $user->syncRoles([]);
            }

            // Sync individual permissions (IDs -> names) if provided, otherwise clear all permissions
            if ($request->has('permissions')) {
                $permIds = $request->permissions ?? [];
                $permissionNames = empty($permIds) ? [] : Permission::whereIn('id', $permIds)->pluck('name')->toArray();
                $user->syncPermissions($permissionNames);
            } else {
                $user->syncPermissions([]);
            }
        });

        return redirect()->route('users.index')->with('success', 'User updated successfully with assigned roles and permissions.');
    }

    public function destroy(User $user)
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        // Prevent deletion of super admin if it's the last one
        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->back()->withErrors(['user' => 'Cannot delete the last super admin user.']);
        }

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
