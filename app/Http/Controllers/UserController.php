<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller implements HasMiddleware
{
    /** Rows-per-page choices on the list ("all" shows every matching user on one page). */
    public const PER_PAGE = [10, 50, 100, 500, 'all'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:user-list', only: ['index', 'show']),
            new Middleware('permission:user-create', only: ['create', 'store']),
            new Middleware('permission:user-edit', only: ['edit', 'update']),
            new Middleware('permission:user-delete', only: ['destroy']),
            new Middleware('permission:user-bulk-update', only: ['bulkUpdate']),
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

        $requested = (string) $request->get('per_page', '10');
        $perPage = in_array($requested, array_map('strval', self::PER_PAGE), true) ? $requested : '10';

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(User::getAllowedFilters())
            ->allowedSorts(User::getAllowedSorts())
            ->allowedIncludes(User::getAllowedIncludes())
            ->with(['roles.permissions', 'permissions', 'supplier'])
            ->defaultSort('-created_at');
        $users = $users->paginate($perPage === 'all' ? max(1, $users->getEloquentBuilder()->clone()->reorder()->count()) : (int) $perPage)
            ->appends(request()->query());

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']);
        $roles = Role::orderBy('name')->get(['id', 'name']);

        return view('settings.users.index', [
            'users' => $users,
            'suppliers' => $suppliers,
            'roles' => $roles,
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
        ]);
    }

    /**
     * Headline numbers for the list. Tab counts follow the other filters
     * (search, role, supplier ...) but not the Active / Suspended tab itself.
     *
     * @return array{total: int, active: int, inactive: int, super_admins: int, no_access: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['is_active']);

        $byStatus = QueryBuilder::for(User::class, new Request(['filter' => $filters]))
            ->allowedFilters(User::getAllowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('is_active, COUNT(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus['Yes'] ?? 0),
            'inactive' => (int) ($byStatus['No'] ?? 0),
            'super_admins' => User::query()->where(fn ($q) => $q->where('is_super_admin', 'Yes')->orWhereHas('roles', fn ($r) => $r->where('name', 'super-admin')))->count(),
            'no_access' => User::query()->where('is_active', 'Yes')->doesntHave('roles')->doesntHave('permissions')->where('is_super_admin', '!=', 'Yes')->count(),
        ];
    }

    public function create()
    {
        return view('settings.users.create', $this->formData(new User([
            'is_active' => 'Yes',
            'is_super_admin' => 'No',
        ])));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
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
                'supplier_id' => $request->supplier_id,
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
        $user->load(['roles.permissions', 'permissions', 'supplier']);

        // permission id => where it comes from (extra and / or role names)
        $grants = [];
        foreach ($user->permissions as $permission) {
            $grants[(string) $permission->id]['direct'] = true;
        }
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $grants[(string) $permission->id]['roles'][] = $role->name;
            }
        }

        $activity = rescue(fn () => Activity::query()
            ->where(fn ($q) => $q->where(fn ($c) => $c->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id))
                ->orWhere(fn ($s) => $s->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)))
            ->with('causer')
            ->latest()
            ->limit(10)
            ->get(), collect(), false);

        return view('settings.users.show', [
            'user' => $user,
            'catalog' => PermissionCatalog::build(Permission::all()),
            'grants' => $grants,
            'activity' => $activity,
        ]);
    }

    public function edit(User $user)
    {
        return view('settings.users.edit', $this->formData($user));
    }

    /**
     * Everything the add / edit form needs. Permissions come arranged like the
     * Settings and Reports screens (see PermissionCatalog) so they are easy to give.
     *
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        $roles = Role::with('permissions:id')->orderBy('name')->get();
        $permissions = Permission::all();
        $isNew = ! $user->exists;

        return [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'catalog' => PermissionCatalog::build($permissions),
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'userRoles' => $isNew ? [] : $user->roles->pluck('id')->toArray(),
            'userPermissions' => $isNew ? [] : $user->permissions->pluck('id')->toArray(),
            'inheritedPermissions' => $isNew ? [] : $user->getPermissionsViaRoles()->pluck('id')->toArray(),
        ];
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
        $ids = array_filter($ids, fn ($id) => (int) $id !== auth()->id());

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

        $message = 'Users updated successfully.';
        if ($action === 'delete') {
            $message = 'Users deleted successfully (except any protected accounts).';
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
            'supplier_id' => 'nullable|exists:suppliers,id',
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
            'supplier_id' => $request->supplier_id,
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
