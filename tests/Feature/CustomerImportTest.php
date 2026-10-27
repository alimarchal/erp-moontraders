<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['customer-list', 'customer-create'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['customer-list', 'customer-create']);
    $this->actingAs($this->user);
});

function customerCsv(array $rows): UploadedFile
{
    $header = 'Customer Code,Customer Name,Phone,Email,Sub Locality,City,Channel Type,Category,Status';
    $lines = array_map(fn ($row) => implode(',', $row), $rows);

    return UploadedFile::fake()->createWithContent('customers.csv', implode("\n", [$header, ...$lines]));
}

function runCustomerImport(array $rows, array $options = [])
{
    return test()->post(route('customers.import'), $options + [
        'import_file' => customerCsv($rows),
        'duplicate_mode' => 'skip',
    ]);
}

it('shows the import page and downloads the template', function () {
    $this->get(route('customers.import.form'))->assertSuccessful()->assertSee('Import customers from Excel');
    $this->get(route('customers.import.template'))->assertDownload('customers_import_template.xlsx');
});

it('imports new shops and generates codes when missing', function () {
    runCustomerImport([
        ['', 'Al Rehman Store', '0300-1111111', '', 'Satellite Town', 'Muzaffarabad', 'General Store', 'B', 'Active'],
        ['', 'City Pharmacy', '0300-2222222', '', 'Chowk', 'Muzaffarabad', 'pharmacy', '', 'Inactive'],
    ])->assertRedirect(route('customers.import.form'))
        ->assertSessionHas('import_result.counts.created', 2);

    $store = Customer::where('customer_name', 'Al Rehman Store')->first();
    expect($store->customer_code)->toBe('CUST-0001')
        ->and($store->customer_category)->toBe('B')
        ->and(Customer::where('customer_name', 'City Pharmacy')->first())
        ->channel_type->toBe('Pharmacy')
        ->is_active->toBeFalse();
});

it('skips rows whose shop code already exists, in the system or in the file', function () {
    Customer::factory()->create(['customer_code' => 'C-1', 'customer_name' => 'Existing', 'phone' => '0300-5555555']);

    runCustomerImport([
        ['c-1', 'Other Name', '', '', '', '', '', '', ''],
        ['N-1', 'Fresh Shop', '', '', '', '', '', '', ''],
        ['N-1', 'Fresh Shop Again', '', '', '', '', '', '', ''],
    ])->assertSessionHas('import_result.counts', ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'skipped' => 2, 'errors' => 0]);

    expect(Customer::count())->toBe(2);
});

it('does not treat same name or phone with a different code as a duplicate', function () {
    Customer::factory()->create(['customer_code' => 'C-1', 'customer_name' => 'Same Shop', 'phone' => '0300-5555555']);

    runCustomerImport([
        ['C-2', 'Same Shop', '0300-5555555', '', '', '', '', '', ''],
    ])->assertSessionHas('import_result.counts.created', 1);

    expect(Customer::count())->toBe(2);
});

it('updates existing customers in update mode without blanking fields', function () {
    $customer = Customer::factory()->create(['customer_code' => 'C-9', 'customer_name' => 'Old Name', 'city' => 'Kotli']);

    runCustomerImport([['C-9', 'New Name', '0311-9999999', '', '', '', '', '', '']], ['duplicate_mode' => 'update'])
        ->assertSessionHas('import_result.counts.updated', 1);

    expect($customer->fresh())
        ->customer_name->toBe('New Name')
        ->phone->toBe('0311-9999999')
        ->city->toBe('Kotli');
});

it('reports row errors without importing them', function () {
    runCustomerImport([
        ['', '', '', '', '', '', '', '', ''],
        ['', 'Bad Channel', '', '', '', '', 'Kiosk', '', ''],
        ['', 'Good Shop', '', '', '', '', '', '', ''],
    ])->assertSessionHas('import_result.counts.errors', 1)
        ->assertSessionHas('import_result.counts.created', 1);

    expect(Customer::pluck('customer_name')->all())->toBe(['Good Shop']);
});

it('saves nothing when check only is selected', function () {
    runCustomerImport([['', 'Dry Shop', '', '', '', '', '', '', '']], ['dry_run' => 1])
        ->assertSessionHas('import_result.counts.created', 1)
        ->assertSessionHas('import_result.dry_run', true);

    expect(Customer::count())->toBe(0);
});

it('requires the customer-create permission', function () {
    $this->user->revokePermissionTo('customer-create');

    runCustomerImport([['', 'Shop', '', '', '', '', '', '', '']])->assertForbidden();
    $this->get(route('customers.import.form'))->assertForbidden();
});

it('lists customers with the new search box', function () {
    Customer::factory()->create(['customer_name' => 'Zeta Traders']);
    Customer::factory()->create(['customer_name' => 'Alpha Mart']);

    $this->get(route('customers.index', ['filter' => ['search' => 'Zeta']]))
        ->assertSuccessful()
        ->assertSee('Zeta Traders')
        ->assertDontSee('Alpha Mart');
});

it('renders the redesigned create and edit forms', function () {
    $this->user->givePermissionTo(Permission::findOrCreate('customer-edit'));
    $customer = Customer::factory()->create(['customer_name' => 'Form Shop']);

    $this->get(route('customers.create'))->assertSuccessful()->assertSee('Shop details')->assertSee('Create customer');
    $this->get(route('customers.edit', $customer))->assertSuccessful()->assertSee('Edit Customer: Form Shop')->assertSee('Save changes');
});

it('renders the redesigned customer view page', function () {
    $customer = Customer::factory()->create(['customer_name' => 'View Shop', 'city' => 'Kotli']);

    $this->get(route('customers.show', $customer))
        ->assertSuccessful()
        ->assertSee('View Shop')
        ->assertSee('Credit available')
        ->assertSee('Kotli');
});

it('keeps column defaults when optional numeric fields are left blank on create', function () {
    $this->post(route('customers.store'), [
        'customer_code' => 'BLANK-1',
        'customer_name' => 'Blank Fields Shop',
        'channel_type' => 'General Store',
        'customer_category' => 'C',
        'credit_limit' => '',
        'payment_terms' => '',
        'country' => '',
        'is_active' => 1,
    ])->assertRedirect(route('customers.index'));

    expect(Customer::where('customer_code', 'BLANK-1')->first())
        ->not->toBeNull()
        ->payment_terms->toBe(30)
        ->country->toBe('Pakistan');
});

it('lists each change as old to new, marks unchanged shops and saves nothing on a check only', function () {
    Customer::factory()->create(['customer_code' => 'C-1', 'customer_name' => 'Shop One', 'phone' => '0300-1111111', 'city' => 'Muzaffarabad']);
    Customer::factory()->create(['customer_code' => 'C-2', 'customer_name' => 'Shop Two', 'phone' => '0300-2222222', 'city' => 'Muzaffarabad']);

    runCustomerImport([
        ['C-1', 'Shop One', '0300-9999999', '', '', 'Muzaffarabad', '', '', ''],
        ['C-2', 'Shop Two', '0300-2222222', '', '', 'Muzaffarabad', '', '', ''],
        ['C-3', 'Brand New', '', '', '', '', '', '', ''],
    ], ['update_existing' => '1', 'dry_run' => '1'])
        ->assertSessionHas('import_result.counts.updated', 1)
        ->assertSessionHas('import_result.counts.unchanged', 1)
        ->assertSessionHas('import_result.counts.created', 1);

    $report = collect(session('import_result.report'));
    expect($report->firstWhere('name', 'Shop One')['message'])->toBe('Phone 0300-1111111 → 0300-9999999')
        ->and($report->firstWhere('name', 'Shop Two')['action'])->toBe('unchanged');

    expect(Customer::where('customer_code', 'C-1')->value('phone'))->toBe('0300-1111111')
        ->and(Customer::where('customer_code', 'C-3')->exists())->toBeFalse();

    runCustomerImport([['C-1', 'Shop One', '0300-9999999', '', '', 'Muzaffarabad', '', '', '']], ['update_existing' => '1']);
    expect(Customer::where('customer_code', 'C-1')->value('phone'))->toBe('0300-9999999');
});

it('reports file columns it does not recognise', function () {
    $file = UploadedFile::fake()->createWithContent('customers.csv', "Customer Code,Customer Name,Fone Number\nN-9,Typo Shop,0300-1\n");

    test()->post(route('customers.import'), ['import_file' => $file, 'duplicate_mode' => 'skip', 'dry_run' => '1'])
        ->assertSessionHas('import_result.ignored_columns', ['fone number']);
});

it('uses searchable dropdowns on the customer and product screens and shows activate or deactivate buttons', function () {
    Permission::findOrCreate('customer-edit');
    Permission::findOrCreate('product-list');
    $this->user->givePermissionTo(['customer-edit', 'product-list']);
    Customer::factory()->create(['customer_name' => 'Active Shop', 'is_active' => true]);
    Customer::factory()->create(['customer_name' => 'Dormant Shop', 'is_active' => false]);

    $this->get(route('customers.index'))->assertSuccessful()
        ->assertSee('class="js-select2"', false)
        ->assertSee('select.js-select2', false)
        ->assertSee('Deactivate customer')
        ->assertSee('Activate customer')
        ->assertDontSee('customers.destroy');

    $this->get(route('customers.create'))->assertSuccessful()->assertSee('class="js-select2"', false);
    $this->get(route('products.index'))->assertSuccessful()->assertSee('class="js-select2"', false);
});

it('shows the customer help page and its link only to users who can add customers or are super admins', function () {
    Permission::findOrCreate('customer-edit');
    Permission::findOrCreate('customer-list');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('customer-list');
    $this->actingAs($viewer)->get(route('customers.index'))->assertSuccessful()->assertDontSee('How customers work');
    $this->get(route('customers.help'))->assertForbidden();

    $this->actingAs($this->user)->get(route('customers.index'))->assertSee('How customers work');
    $this->get(route('customers.help'))->assertSuccessful()
        ->assertSee('Customers are never deleted')
        ->assertSee('Update customers that already exist');

    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']))->get(route('customers.help'))->assertSuccessful();
});
