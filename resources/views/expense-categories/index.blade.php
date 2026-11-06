<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>Categories</span></nav>
                <h1 class="ak-title">Expense categories <span class="ev-ur" lang="ur" dir="rtl">اخراجات کی اقسام</span></h1>
                <p class="ak-sub">What a voucher is for (toner, grocery, rent...). Each can carry the usual expense account.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Vouchers</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <section class="ak-card" style="padding:16px 20px">
            <h2 style="margin:0 0 10px; font-size:15px; font-weight:700">Add a category</h2>
            <form method="POST" action="{{ route('expense-categories.store') }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:end">@csrf
                <div class="ak-field"><label for="new_name">Name</label><input id="new_name" name="name" required maxlength="255" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px; min-width:240px"></div>
                <div class="ak-field"><label for="new_account">Usual expense account</label><select id="new_account" name="default_expense_account_id" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px; max-width:300px"><option value="">None</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->account_code }} - {{ $a->account_name }}</option>@endforeach</select></div>
                <button class="ak-btn ak-btn-primary" style="height:38px">Add</button>
            </form>
        </section>
        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt" style="min-width:720px">
                <thead><tr><th>Category</th><th>Usual expense account</th><th class="ak-num">Vouchers</th><th>Active</th><th></th></tr></thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td><input form="cat{{ $category->id }}" name="name" value="{{ $category->name }}" required maxlength="255" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 8px; width:100%; min-width:220px"></td>
                            <td><select form="cat{{ $category->id }}" name="default_expense_account_id" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 6px; max-width:280px"><option value="">None</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($category->default_expense_account_id === $a->id)>{{ $a->account_code }} - {{ $a->account_name }}</option>@endforeach</select></td>
                            <td class="ak-num">{{ $category->vouchers_count }}</td>
                            <td><label style="display:inline-flex; gap:6px; align-items:center"><input type="checkbox" form="cat{{ $category->id }}" name="is_active" value="1" @checked($category->is_active)> Active</label></td>
                            <td><button form="cat{{ $category->id }}" class="ak-btn ak-btn-outline" style="padding:4px 12px">Save</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach($categories as $category)<form id="cat{{ $category->id }}" method="POST" action="{{ route('expense-categories.update', $category) }}" hidden>@csrf @method('PUT')</form>@endforeach
        </section>
    </div>
</x-app-layout>
