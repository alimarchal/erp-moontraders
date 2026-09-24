{{--
    Role permissions (roles create / edit). Same matrix as the user form:
    permissions arranged like the Settings and Reports screens, posted as permissions[] ids.
--}}
@php
    $selectedPermissionIds = array_map('strval', old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : []));
    $catalog = \App\Services\PermissionCatalog::build($permissions ?? \Spatie\Permission\Models\Permission::all());
@endphp

@include('settings.partials.ui-style')

<div class="uf-card" x-data="permissionMatrix({ permissions: {{ \Illuminate\Support\Js::from($selectedPermissionIds) }} })">
    <header class="uf-card-head">
        <div>
            <h3 class="uf-card-title">Permissions <span class="ak-count" x-text="permissions.length + ' given'"></span></h3>
            <p class="uf-card-sub" style="margin-left:0">Arranged like the Settings and Reports pages. “List” opens a screen; add Create / Edit / Post only where needed.</p>
        </div>
    </header>
    @include('settings.partials.permission-matrix', ['catalog' => $catalog, 'withRoles' => false])
</div>
