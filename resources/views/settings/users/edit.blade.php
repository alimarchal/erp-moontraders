{{--
    Settings -> Users -> Add / Edit (users/create includes this view with a new User).

    Posts exactly the fields UserController@store / @update validate:
    name, email, designation, supplier_id, password, is_active, is_super_admin,
    roles[] (ids) and permissions[] (ids). Layout: 1 account, 2 roles,
    3 extra permissions arranged like the Settings / Reports screens,
    a live access summary, a sticky action bar and a review modal before saving.
    Styles are plain CSS (settings.partials.ui-style) -- no Tailwind rebuild needed.
--}}
@php
    $isCreate = ! $user->exists;
    $authUser = auth()->user();
    $isSelf = ! $isCreate && $user->is($authUser);
    $canManageSuperAdmin = $authUser->is_super_admin === 'Yes' || $authUser->hasRole('super-admin');

    $selectedRoleIds = array_map('strval', old('roles', $userRoles ?? []));
    $selectedPermissionIds = array_map('strval', old('permissions', $userPermissions ?? []));

    $rolePermissionMap = $roles->mapWithKeys(fn ($role) => [(string) $role->id => $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->values()]);
    $roleNames = $roles->mapWithKeys(fn ($role) => [(string) $role->id => $role->name]);

    // "Settings & operations › Users › Create" style labels for the review list.
    $permissionLabels = [];
    $moduleIds = [];
    foreach ($catalog as $module) {
        foreach ($module['sections'] as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['permissions'] as $permission) {
                    $permissionLabels[$permission['id']] = $group['label'].' › '.$permission['label'];
                    $moduleIds[$module['label']][] = $permission['id'];
                }
            }
        }
    }

    $roleHelp = [
        'super-admin' => 'Full access to everything',
        'admin' => 'Most modules; cannot delete most records',
        'accountant' => 'Accounting, ledgers and financial reports',
        'inventory-manager' => 'Stock, GRN, goods issue and inventory reports',
        'sales-manager' => 'Settlements, sales and sales reports',
        'user' => 'No default permissions; give extras below',
    ];

    $original = [
        'roles' => $isCreate ? [] : array_map('strval', $userRoles ?? []),
        'permissions' => $isCreate ? [] : array_map('strval', $userPermissions ?? []),
        'isActive' => $user->is_active ?: 'Yes',
        'isSuperAdmin' => $user->is_super_admin ?: 'No',
    ];
    $initials = collect(explode(' ', trim((string) $user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('users.index') }}">Users</a><span aria-hidden="true">›</span>
                    <span>{{ $isCreate ? 'Add' : 'Edit' }}</span>
                </nav>
                <h1 class="ak-title">{{ $isCreate ? 'Add New User' : 'Edit User: '.$user->name }}</h1>
                <p class="ak-sub">
                    @if ($isCreate)
                        Create the login, pick roles, then add any extra permissions.
                    @else
                        {{ $user->email }}
                        &middot; <span class="ak-scope">{{ $user->is_active === 'No' ? 'Inactive' : 'Active' }}</span>
                        @if ($user->roles->isNotEmpty()) &middot; {{ $user->roles->pluck('name')->implode(', ') }} @endif
                    @endif
                </p>
            </div>
            <div class="ak-head-actions">
                @unless ($isCreate)
                    @can('user-list')
                        <a href="{{ route('users.show', $user) }}" class="ak-btn ak-btn-outline">View profile</a>
                    @endcan
                @endunless
                <a href="{{ route('users.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Users</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">
                <b>Please fix the following before saving:</b>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $isCreate ? route('users.store') : route('users.update', $user) }}" id="user-form" novalidate
            x-data="userForm({
                roles: {{ \Illuminate\Support\Js::from($selectedRoleIds) }},
                permissions: {{ \Illuminate\Support\Js::from($selectedPermissionIds) }},
                roleMap: {{ \Illuminate\Support\Js::from($roleNames) }},
                rolePermissions: {{ \Illuminate\Support\Js::from($rolePermissionMap) }},
                labels: {{ \Illuminate\Support\Js::from($permissionLabels) }},
                modules: {{ \Illuminate\Support\Js::from($moduleIds) }},
                original: {{ \Illuminate\Support\Js::from($original) }},
                isActive: {{ \Illuminate\Support\Js::from(old('is_active', $original['isActive'])) }},
                isSuperAdmin: {{ \Illuminate\Support\Js::from(old('is_super_admin', $original['isSuperAdmin'])) }},
                isCreate: {{ $isCreate ? 'true' : 'false' }},
                name: {{ \Illuminate\Support\Js::from(old('name', $user->name ?? '')) }},
                email: {{ \Illuminate\Support\Js::from($isCreate ? old('email', '') : $user->email) }},
            })"
            @submit.prevent="openReview()">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            <div class="uf-grid">
                {{-- 1. Account -------------------------------------------------------- --}}
                <section class="uf-card" aria-labelledby="uf-account">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="uf-account"><span class="uf-step">1</span> Account</h2>
                            <p class="uf-card-sub">Login details and whether the user can sign in.</p>
                        </div>
                    </header>
                    <div class="uf-body">
                        <div class="uf-fields">
                            <div class="uf-field">
                                <label for="name">Full name <span class="uf-req">*</span></label>
                                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" x-model="name" required maxlength="255" autocomplete="off" @if ($isCreate) autofocus @endif>
                                @error('name') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="email">Email (login) <span class="uf-req">*</span></label>
                                @if ($isCreate)
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" x-model="email" required autocomplete="off">
                                @else
                                    <input id="email" type="email" name="email" value="{{ $user->email }}" x-model="email" readonly title="Email cannot be changed">
                                    <p class="ak-help">Email is the login and cannot be changed.</p>
                                @endif
                                @error('email') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="designation">Designation</label>
                                <input id="designation" type="text" name="designation" value="{{ old('designation', $user->designation) }}" maxlength="255" placeholder="e.g. Manager, Accountant">
                                @error('designation') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="supplier_id">Supplier</label>
                                <select id="supplier_id" name="supplier_id">
                                    <option value="">All suppliers (not linked)</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $user->supplier_id) === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                                    @endforeach
                                </select>
                                <p class="ak-help">Link to one supplier to limit the user to that supplier's records.</p>
                                @error('supplier_id') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field" x-data="{ show: false }">
                                <label for="password">{{ $isCreate ? 'Password' : 'New password' }} @if ($isCreate)<span class="uf-req">*</span>@endif</label>
                                <div class="uf-pass">
                                    <input id="password" :type="show ? 'text' : 'password'" name="password" minlength="8" autocomplete="new-password" @required($isCreate)
                                        x-model="password" placeholder="{{ $isCreate ? 'At least 8 characters' : 'Leave blank to keep current' }}">
                                    <button type="button" @click="show = !show" x-text="show ? 'Hide' : 'Show'" :aria-label="show ? 'Hide password' : 'Show password'"></button>
                                </div>
                                <p class="ak-help">Minimum 8 characters.{{ $isCreate ? '' : ' Leave blank to keep the current password.' }}</p>
                                @error('password') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-fields" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                <div class="uf-field">
                                    <label for="is_active">Status <span class="uf-req">*</span></label>
                                    <select id="is_active" name="is_active" x-model="isActive" @if ($isSelf) title="You cannot deactivate your own account" @endif>
                                        <option value="Yes">Active</option>
                                        <option value="No" @disabled($isSelf)>Suspended</option>
                                    </select>
                                    @error('is_active') <p class="uf-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="uf-field">
                                    <label for="is_super_admin">Super admin <span class="uf-req">*</span></label>
                                    @if ($canManageSuperAdmin)
                                        <select id="is_super_admin" name="is_super_admin" x-model="isSuperAdmin">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    @else
                                        {{-- Only super admins may change this; keep the current value so the form still validates. --}}
                                        <input type="hidden" name="is_super_admin" value="{{ $original['isSuperAdmin'] }}">
                                        <input id="is_super_admin" type="text" value="{{ $original['isSuperAdmin'] }}" readonly title="Only a super admin can change this">
                                    @endif
                                    @error('is_super_admin') <p class="uf-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Access summary ----------------------------------------------------- --}}
                <aside class="uf-card uf-summary" aria-labelledby="uf-summary">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="uf-summary">
                                <span class="ak-avatar" aria-hidden="true" x-text="initials()">{{ $initials ?: '+' }}</span>
                                Access summary
                            </h2>
                            <p class="uf-card-sub">Updates as you change roles and permissions.</p>
                        </div>
                    </header>
                    <div class="uf-body" style="display:flex; flex-direction:column; gap:16px">
                        <dl>
                            <div><dt>Status</dt><dd :style="isActive === 'Yes' ? 'color:#15803d' : 'color:#b91c1c'" x-text="isActive === 'Yes' ? 'Active' : 'Suspended'"></dd></div>
                            <div><dt>Super admin</dt><dd x-text="isSuperAdmin"></dd></div>
                            <div><dt>Roles</dt><dd x-text="roleNames().join(', ') || 'None'"></dd></div>
                            <div><dt>From roles</dt><dd x-text="Object.keys(inheritedMap).length"></dd></div>
                            <div><dt>Extra (individual)</dt><dd x-text="extraCount()"></dd></div>
                            <div class="uf-total"><dt><b>Total access</b></dt><dd x-text="totalCount() + ' permissions'"></dd></div>
                        </dl>
                        <div class="uf-mod">
                            <template x-for="(ids, label) in modules" :key="label">
                                <div style="display:block">
                                    <div><span x-text="label"></span><b x-text="grantedIn(ids) + ' / ' + ids.length"></b></div>
                                    <div class="uf-bar"><span :style="'width:' + (ids.length ? Math.round(grantedIn(ids) / ids.length * 100) : 0) + '%'"></span></div>
                                </div>
                            </template>
                        </div>
                        <p class="uf-note uf-note-warn" x-show="isSuperAdmin === 'Yes' || hasRole('super-admin')" x-cloak>
                            Super admin skips every permission check; the permissions below do not limit this user.
                        </p>
                        <p class="uf-note uf-note-warn" x-show="isActive === 'No'" x-cloak>Suspended users cannot sign in.</p>
                    </div>
                </aside>
            </div>

            {{-- 2. Roles ------------------------------------------------------------------ --}}
            <section class="uf-card" style="margin-top:20px" aria-labelledby="uf-roles">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="uf-roles"><span class="uf-step">2</span> Roles</h2>
                        <p class="uf-card-sub">Start with a role: it gives a ready set of permissions. Use step 3 only for what the role does not cover.</p>
                    </div>
                    @can('role-list')
                        <a href="{{ route('roles.index') }}" class="ak-btn ak-btn-ghost ak-btn-sm" target="_blank" rel="noopener">Manage roles ↗</a>
                    @endcan
                </header>
                <div class="uf-body">
                    <div class="uf-roles">
                        @foreach ($roles as $role)
                            <label class="uf-role" :class="roles.includes('{{ $role->id }}') && 'is-on'">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" x-model="roles">
                                <span>
                                    <b>{{ \Illuminate\Support\Str::headline($role->name) }}</b>
                                    <small>{{ $roleHelp[$role->name] ?? 'Custom role' }} &middot; {{ $role->permissions->count() }} permissions</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles') <p class="uf-error">{{ $message }}</p> @enderror
                    <p class="uf-note uf-note-info" style="margin-top:14px" x-show="roles.length === 0" x-cloak>
                        No role selected: the user will only have the extra permissions ticked in step 3.
                    </p>
                </div>
            </section>

            {{-- 3. Extra permissions ------------------------------------------------------- --}}
            <section class="uf-card" style="margin-top:20px" aria-labelledby="uf-perms">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="uf-perms"><span class="uf-step">3</span> Extra permissions</h2>
                        <p class="uf-card-sub">Arranged like the Settings and Reports pages. Green pills already come from a role; tick blue ones to give more.</p>
                    </div>
                </header>
                @include('settings.partials.permission-matrix', ['catalog' => $catalog, 'withRoles' => true])
                @error('permissions') <p class="uf-error" style="padding:0 20px 16px">{{ $message }}</p> @enderror
            </section>

            {{-- Sticky action bar ------------------------------------------------------------ --}}
            <div class="uf-actions" style="margin-top:20px">
                <p>
                    Fields marked <span class="uf-req">*</span> are required. You will review the changes before they are saved.
                    <span class="uf-changed" x-show="!isCreate && changeCount() > 0" x-cloak x-text="changeCount() + ' unsaved ' + (changeCount() === 1 ? 'change' : 'changes')"></span>
                </p>
                <div>
                    <a href="{{ route('users.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                    <button type="submit" class="ak-btn ak-btn-primary">Review &amp; save</button>
                </div>
            </div>

            {{-- Review modal (same pattern as the Approve modal) --------------------------- --}}
            <div class="uf-modal" x-show="review" x-cloak style="display:none" @keydown.escape.window="review = false" role="dialog" aria-modal="true" aria-labelledby="uf-review-title">
                <div class="uf-modal-bg" x-show="review" x-transition.opacity @click="review = false"></div>
                <div class="uf-modal-box" x-show="review" x-transition>
                    <div class="uf-modal-body">
                        <span class="uf-modal-icon" :class="warnings().length ? 'ak-tone-amber' : 'ak-tone-green'" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </span>
                        <div style="flex:1; min-width:0">
                            <h3 id="uf-review-title">{{ $isCreate ? 'Create this user?' : 'Save changes to '.$user->name.'?' }}</h3>
                            <div class="uf-review">
                                <dl>
                                    <div><dt>Name</dt><dd x-text="name.trim() || '—'"></dd></div>
                                    <div><dt>Email</dt><dd x-text="email.trim() || '—'" style="word-break:break-all"></dd></div>
                                    <div><dt>Status</dt><dd x-text="isActive === 'Yes' ? 'Active' : 'Suspended'"></dd></div>
                                    <div><dt>Super admin</dt><dd x-text="isSuperAdmin"></dd></div>
                                    <div class="uf-wide"><dt>Roles</dt><dd x-text="roleNames().join(', ') || 'None'"></dd></div>
                                    <div><dt>Extra permissions</dt><dd x-text="extraCount()"></dd></div>
                                    <div><dt>Total access</dt><dd x-text="totalCount()"></dd></div>
                                    <div class="uf-wide"><dt>Password</dt><dd x-text="password ? '{{ $isCreate ? 'Set' : 'Will be changed' }}' : '{{ $isCreate ? 'Not set' : 'Unchanged' }}'"></dd></div>
                                </dl>
                                <ul class="uf-diff" x-show="!isCreate && diff().length">
                                    <template x-for="line in diff().slice(0, 12)" :key="line.text">
                                        <li :class="line.type" x-text="(line.type === 'add' ? '+ ' : '− ') + line.text"></li>
                                    </template>
                                    <li x-show="diff().length > 12" x-text="'… and ' + (diff().length - 12) + ' more'"></li>
                                </ul>
                            </div>
                            <ul class="uf-warn" x-show="warnings().length">
                                <template x-for="w in warnings()" :key="w"><li>⚠ <span x-text="w"></span></li></template>
                            </ul>
                        </div>
                    </div>
                    <div class="uf-modal-foot">
                        <button type="button" class="ak-btn ak-btn-outline" @click="review = false">Back to form</button>
                        <button type="button" class="ak-btn ak-btn-success" @click="confirmSave()" :disabled="saving">
                            <span x-show="!saving">{{ $isCreate ? 'Create user' : 'Save changes' }}</span><span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function userForm(config) {
                return Object.assign(window.permissionMatrix(config), {
                    roles: config.roles.map(String),
                    labels: config.labels,
                    modules: config.modules,
                    original: config.original,
                    isActive: config.isActive || 'Yes',
                    isSuperAdmin: config.isSuperAdmin || 'No',
                    isCreate: config.isCreate,
                    name: config.name || '',
                    email: config.email || '',
                    password: '',
                    review: false,
                    saving: false,
                    init() {
                        this.refreshInherited(this.roles);
                        this.$watch('roles', (value) => this.refreshInherited(value));
                    },
                    initials() {
                        const parts = this.name.trim().split(/\s+/).filter(Boolean).slice(0, 2);
                        return parts.map((part) => part[0].toUpperCase()).join('') || '+';
                    },
                    hasRole(name) { return this.roles.some((id) => this.roleMap[id] === name); },
                    roleNames() { return this.roles.map((id) => this.roleMap[id]).filter(Boolean); },
                    extraCount() { return this.permissions.length; },
                    totalCount() {
                        const ids = new Set(Object.keys(this.inheritedMap));
                        this.permissions.forEach((id) => ids.add(id));
                        return ids.size;
                    },
                    diff() {
                        const lines = [];
                        const name = (id) => this.labels[id] || ('#' + id);
                        this.roles.filter((id) => !this.original.roles.includes(id)).forEach((id) => lines.push({ type: 'add', text: 'Role ' + this.roleMap[id] }));
                        this.original.roles.filter((id) => !this.roles.includes(id)).forEach((id) => lines.push({ type: 'rem', text: 'Role ' + this.roleMap[id] }));
                        this.permissions.filter((id) => !this.original.permissions.includes(id)).forEach((id) => lines.push({ type: 'add', text: name(id) }));
                        this.original.permissions.filter((id) => !this.permissions.includes(id)).forEach((id) => lines.push({ type: 'rem', text: name(id) }));
                        return lines;
                    },
                    changeCount() {
                        return this.diff().length
                            + (this.isActive !== this.original.isActive ? 1 : 0)
                            + (this.isSuperAdmin !== this.original.isSuperAdmin ? 1 : 0)
                            + (this.password ? 1 : 0);
                    },
                    warnings() {
                        const list = [];
                        if (this.isActive === 'No' && this.original.isActive !== 'No') list.push('The user will be suspended and can no longer sign in.');
                        if (this.isSuperAdmin !== this.original.isSuperAdmin) list.push('Super admin will change to ' + this.isSuperAdmin + '.');
                        if (this.roles.length === 0 && this.permissions.length === 0 && this.isSuperAdmin !== 'Yes') list.push('No role or permission is selected: the user can sign in but cannot open any module.');
                        if (this.original.roles.length && this.original.roles.some((id) => !this.roles.includes(id))) list.push('A role is being removed; the user loses the permissions that came only from it.');
                        return list;
                    },
                    openReview() {
                        const form = document.getElementById('user-form');
                        if (!form.reportValidity()) return;
                        this.saving = false;
                        this.review = true;
                    },
                    confirmSave() {
                        if (this.saving) return;
                        this.saving = true;
                        document.getElementById('user-form').submit();
                    },
                });
            }
        </script>
    @endpush
</x-app-layout>
