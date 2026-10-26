{{--
    Settings -> Employees -> Add / Edit (employees/create includes this view with a new Employee).

    Posts exactly the fields StoreEmployeeRequest / UpdateEmployeeRequest validate:
    employee_code, name, designation, company_name, phone, email, hire_date,
    supplier_id, company_id, warehouse_id, user_id, address and is_active.
    Same screen pattern as the user form: cards, live summary, sticky action bar
    and a review modal before saving. Styles come from settings.partials.ui-style.
--}}
@php
    $isCreate = ! $employee->exists;
    $initials = collect(explode(' ', trim((string) $employee->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $isActive = old('is_active', $employee->is_active ?? true) ? '1' : '0';
    $supplierNames = $supplierOptions->pluck('supplier_name', 'id');
    $warehouseNames = $warehouseOptions->pluck('warehouse_name', 'id');
    $userNames = $userOptions->pluck('name', 'id');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('employees.index') }}">Employees</a><span aria-hidden="true">›</span>
                    <span>{{ $isCreate ? 'Add' : 'Edit' }}</span>
                </nav>
                <h1 class="ak-title">{{ $isCreate ? 'Add New Employee' : 'Edit Employee: '.$employee->name }}</h1>
                <p class="ak-sub">
                    @if ($isCreate)
                        Enter the employee's details, then assign a supplier, warehouse and login.
                    @else
                        {{ $employee->employee_code }}
                        &middot; <span class="ak-scope">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                        @if ($employee->designation) &middot; {{ $employee->designation }} @endif
                    @endif
                </p>
            </div>
            <div class="ak-head-actions">
                @unless ($isCreate)
                    @can('employee-list')
                        <a href="{{ route('employees.show', $employee) }}" class="ak-btn ak-btn-outline">View profile</a>
                    @endcan
                @endunless
                <a href="{{ route('employees.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Employees</a>
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

        <form method="POST" action="{{ $isCreate ? route('employees.store') : route('employees.update', $employee) }}" id="employee-form" novalidate
            x-data="employeeForm({
                isCreate: {{ $isCreate ? 'true' : 'false' }},
                code: {{ \Illuminate\Support\Js::from(old('employee_code', $employee->employee_code ?? '')) }},
                name: {{ \Illuminate\Support\Js::from(old('name', $employee->name ?? '')) }},
                designation: {{ \Illuminate\Support\Js::from(old('designation', $employee->designation ?? '')) }},
                supplierId: {{ \Illuminate\Support\Js::from((string) old('supplier_id', $employee->supplier_id ?? '')) }},
                warehouseId: {{ \Illuminate\Support\Js::from((string) old('warehouse_id', $employee->warehouse_id ?? '')) }},
                userId: {{ \Illuminate\Support\Js::from((string) old('user_id', $employee->user_id ?? '')) }},
                isActive: {{ \Illuminate\Support\Js::from($isActive) }},
                suppliers: {{ \Illuminate\Support\Js::from($supplierNames) }},
                warehouses: {{ \Illuminate\Support\Js::from($warehouseNames) }},
                users: {{ \Illuminate\Support\Js::from($userNames) }},
                originalActive: {{ \Illuminate\Support\Js::from($isCreate ? '1' : ($employee->is_active ? '1' : '0')) }},
            })"
            @submit.prevent="openReview()">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            <div class="uf-grid">
                {{-- 1. Details -------------------------------------------------------- --}}
                <section class="uf-card" aria-labelledby="uf-details">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="uf-details"><span class="uf-step">1</span> Details</h2>
                            <p class="uf-card-sub">Who the employee is and how to reach them.</p>
                        </div>
                    </header>
                    <div class="uf-body">
                        <div class="uf-fields">
                            <div class="uf-field">
                                <label for="employee_code">Employee code <span class="uf-req">*</span></label>
                                <input id="employee_code" type="text" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" x-model="code" required maxlength="20" autocomplete="off" style="text-transform:uppercase" placeholder="EMP001" @if ($isCreate) autofocus @endif>
                                <p class="ak-help">Letters, numbers, dashes, underscores or dots. Must be unique.</p>
                                @error('employee_code') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="name">Full name <span class="uf-req">*</span></label>
                                <input id="name" type="text" name="name" value="{{ old('name', $employee->name) }}" x-model="name" required maxlength="255" autocomplete="off" placeholder="John Doe">
                                @error('name') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="designation">Designation</label>
                                <input id="designation" type="text" name="designation" value="{{ old('designation', $employee->designation) }}" x-model="designation" maxlength="255" placeholder="e.g. Salesman, Driver, Supervisor">
                                @error('designation') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="category">Category</label>
                                <input id="category" type="text" name="category" value="{{ old('category', $employee->category) }}" maxlength="100" list="employee-categories" autocomplete="off" placeholder="e.g. Corporate, Powder, Liquid">
                                <datalist id="employee-categories">
                                    <option value="Corporate"></option>
                                    <option value="Powder"></option>
                                    <option value="Liquid"></option>
                                </datalist>
                                @error('category') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="company_name">Company / Principal</label>
                                <input id="company_name" type="text" name="company_name" value="{{ old('company_name', $employee->company_name) }}" maxlength="255" placeholder="Nestle Pakistan">
                                @error('company_name') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="phone">Phone</label>
                                <input id="phone" type="text" name="phone" value="{{ old('phone', $employee->phone) }}" maxlength="30" placeholder="03XX-1234567">
                                @error('phone') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="email">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $employee->email) }}" maxlength="255" autocomplete="off" placeholder="john.doe@example.com">
                                @error('email') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="hire_date">Hire date</label>
                                <input id="hire_date" type="date" name="hire_date" value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                                @error('hire_date') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="uf-field">
                                <label for="is_active">Status <span class="uf-req">*</span></label>
                                <select id="is_active" name="is_active" x-model="isActive">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                @error('is_active') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Summary ------------------------------------------------------------ --}}
                <aside class="uf-card uf-summary" aria-labelledby="uf-summary">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="uf-summary">
                                <span class="ak-avatar" aria-hidden="true" x-text="initials()">{{ $initials ?: '+' }}</span>
                                Summary
                            </h2>
                            <p class="uf-card-sub">Updates as you fill in the form.</p>
                        </div>
                    </header>
                    <div class="uf-body" style="display:flex; flex-direction:column; gap:16px">
                        <dl>
                            <div><dt>Code</dt><dd x-text="code.trim().toUpperCase() || '—'"></dd></div>
                            <div><dt>Name</dt><dd x-text="name.trim() || '—'"></dd></div>
                            <div><dt>Designation</dt><dd x-text="designation.trim() || '—'"></dd></div>
                            <div><dt>Status</dt><dd :style="isActive === '1' ? 'color:#15803d' : 'color:#b91c1c'" x-text="isActive === '1' ? 'Active' : 'Inactive'"></dd></div>
                            <div><dt>Supplier</dt><dd x-text="suppliers[supplierId] || 'None'"></dd></div>
                            <div><dt>Warehouse</dt><dd x-text="warehouses[warehouseId] || 'Not assigned'"></dd></div>
                            <div class="uf-total"><dt><b>Linked user</b></dt><dd x-text="users[userId] || 'None'"></dd></div>
                        </dl>
                        <p class="uf-note uf-note-warn" x-show="isActive === '0'" x-cloak>Inactive employees are hidden from the pickers on new documents.</p>
                    </div>
                </aside>
            </div>

            {{-- 2. Assignment --------------------------------------------------------------- --}}
            <section class="uf-card" style="margin-top:20px" aria-labelledby="uf-assign">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="uf-assign"><span class="uf-step">2</span> Assignment</h2>
                        <p class="uf-card-sub">Where the employee works and which login they use. All optional.</p>
                    </div>
                </header>
                <div class="uf-body">
                    <div class="uf-fields">
                        <div class="uf-field">
                            <label for="supplier_id">Supplier</label>
                            <select id="supplier_id" name="supplier_id" x-model="supplierId">
                                <option value="">No supplier</option>
                                @foreach ($supplierOptions as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $employee->supplier_id) === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <p class="uf-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="uf-field">
                            <label for="company_id">Company entity</label>
                            <select id="company_id" name="company_id">
                                <option value="">Not assigned</option>
                                @foreach ($companyOptions as $company)
                                    <option value="{{ $company->id }}" @selected((string) old('company_id', $employee->company_id) === (string) $company->id)>{{ $company->company_name }}</option>
                                @endforeach
                            </select>
                            @error('company_id') <p class="uf-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="uf-field">
                            <label for="warehouse_id">Primary warehouse</label>
                            <select id="warehouse_id" name="warehouse_id" x-model="warehouseId">
                                <option value="">Not assigned</option>
                                @foreach ($warehouseOptions as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $employee->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id') <p class="uf-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="uf-field">
                            <label for="user_id">Linked user</label>
                            <select id="user_id" name="user_id" x-model="userId">
                                <option value="">No linked user</option>
                                @foreach ($userOptions as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id', $employee->user_id) === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p class="ak-help">The login this employee uses, if any.</p>
                            @error('user_id') <p class="uf-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="uf-field" style="margin-top:16px">
                        <label for="address">Address / notes</label>
                        <textarea id="address" name="address" rows="3" placeholder="Office address, remarks, etc.">{{ old('address', $employee->address) }}</textarea>
                        @error('address') <p class="uf-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Sticky action bar ------------------------------------------------------------ --}}
            <div class="uf-actions" style="margin-top:20px">
                <p>
                    Fields marked <span class="uf-req">*</span> are required. You will review the details before they are saved.
                    <span class="uf-changed" x-show="!isCreate && isActive !== originalActive" x-cloak>Status changed</span>
                </p>
                <div>
                    <a href="{{ route('employees.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                    <button type="submit" class="ak-btn ak-btn-primary">Review &amp; save</button>
                </div>
            </div>

            {{-- Review modal ------------------------------------------------------------------ --}}
            <div class="uf-modal" x-show="review" x-cloak style="display:none" @keydown.escape.window="review = false" role="dialog" aria-modal="true" aria-labelledby="uf-review-title">
                <div class="uf-modal-bg" x-show="review" x-transition.opacity @click="review = false"></div>
                <div class="uf-modal-box" x-show="review" x-transition>
                    <div class="uf-modal-body">
                        <span class="uf-modal-icon ak-tone-green" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </span>
                        <div style="flex:1; min-width:0">
                            <h3 id="uf-review-title">{{ $isCreate ? 'Create this employee?' : 'Save changes to '.$employee->name.'?' }}</h3>
                            <div class="uf-review">
                                <dl>
                                    <div><dt>Code</dt><dd x-text="code.trim().toUpperCase() || '—'"></dd></div>
                                    <div><dt>Name</dt><dd x-text="name.trim() || '—'"></dd></div>
                                    <div><dt>Designation</dt><dd x-text="designation.trim() || '—'"></dd></div>
                                    <div><dt>Status</dt><dd x-text="isActive === '1' ? 'Active' : 'Inactive'"></dd></div>
                                    <div><dt>Supplier</dt><dd x-text="suppliers[supplierId] || 'None'"></dd></div>
                                    <div><dt>Warehouse</dt><dd x-text="warehouses[warehouseId] || 'Not assigned'"></dd></div>
                                    <div class="uf-wide"><dt>Linked user</dt><dd x-text="users[userId] || 'None'"></dd></div>
                                </dl>
                            </div>
                            <ul class="uf-warn" x-show="isActive === '0' && originalActive !== '0'">
                                <li>⚠ The employee will be marked inactive.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="uf-modal-foot">
                        <button type="button" class="ak-btn ak-btn-outline" @click="review = false">Back to form</button>
                        <button type="button" class="ak-btn ak-btn-success" @click="confirmSave()" :disabled="saving">
                            <span x-show="!saving">{{ $isCreate ? 'Create employee' : 'Save changes' }}</span><span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function employeeForm(config) {
                return {
                    isCreate: config.isCreate,
                    code: config.code || '',
                    name: config.name || '',
                    designation: config.designation || '',
                    supplierId: config.supplierId || '',
                    warehouseId: config.warehouseId || '',
                    userId: config.userId || '',
                    isActive: config.isActive || '1',
                    originalActive: config.originalActive,
                    suppliers: config.suppliers,
                    warehouses: config.warehouses,
                    users: config.users,
                    review: false,
                    saving: false,
                    initials() {
                        const parts = this.name.trim().split(/\s+/).filter(Boolean).slice(0, 2);
                        return parts.map((part) => part[0].toUpperCase()).join('') || '+';
                    },
                    openReview() {
                        const form = document.getElementById('employee-form');
                        if (!form.reportValidity()) return;
                        this.saving = false;
                        this.review = true;
                    },
                    confirmSave() {
                        if (this.saving) return;
                        this.saving = true;
                        document.getElementById('employee-form').submit();
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
