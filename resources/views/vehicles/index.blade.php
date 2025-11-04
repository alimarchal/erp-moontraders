<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <x-page-header title="Vehicles" :createRoute="route('vehicles.create')" createLabel="Add Vehicle"
                :showSearch="true" :showRefresh="true" backRoute="settings.index" />

            <div class="ml-4">
                <!-- Client-side PDF Export -->
                <x-pdf-download-button id="vehicle-pdf-btn" type="client" label="Export PDF" variant="indigo" size="sm"
                    :icon="true" />
            </div>
        </div>
    </x-slot>

    <x-filter-section :action="route('vehicles.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_vehicle_number" value="Vehicle Number" />
                <x-input id="filter_vehicle_number" name="filter[vehicle_number]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.vehicle_number')"
                    placeholder="e.g., RLF-4328" />
            </div>

            <div>
                <x-label for="filter_registration_number" value="Registration Number" />
                <x-input id="filter_registration_number" name="filter[registration_number]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.registration_number')"
                    placeholder="e.g., RLF-4328" />
            </div>

            <div>
                <x-label for="filter_vehicle_type" value="Vehicle Type" />
                <x-input id="filter_vehicle_type" name="filter[vehicle_type]" type="text" class="mt-1 block w-full"
                    :value="request('filter.vehicle_type')" placeholder="Truck, Van, Pickup" />
            </div>

            <div>
                <x-label for="filter_driver_name" value="Driver Name" />
                <x-input id="filter_driver_name" name="filter[driver_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.driver_name')" placeholder="Search by driver" />
            </div>

            <div>
                <x-label for="filter_company_id" value="Company" />
                <select id="filter_company_id" name="filter[company_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Companies</option>
                    @foreach ($companyOptions as $company)
                    <option value="{{ $company->id }}" {{ request('filter.company_id')===(string) $company->id ?
                        'selected' : '' }}>
                        {{ $company->company_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Transporter / Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($supplierOptions as $supplier)
                    <option value="{{ $supplier->id }}" {{ request('filter.supplier_id')===(string) $supplier->id ?
                        'selected' : '' }}>
                        {{ $supplier->supplier_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Assigned Driver" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Drivers</option>
                    @foreach ($employeeOptions as $employee)
                    <option value="{{ $employee->id }}" {{ request('filter.employee_id')===(string) $employee->id ?
                        'selected' : '' }}>
                        {{ $employee->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_active')===(string) $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$vehicles" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Vehicle Number'],
        ['label' => 'Registration'],
        ['label' => 'Type'],
        ['label' => 'Company'],
        ['label' => 'Supplier'],
        ['label' => 'Driver'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No vehicles found." :emptyRoute="route('vehicles.create')" emptyLinkText="Add a vehicle">
        @foreach ($vehicles as $index => $vehicle)
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $vehicles->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 font-semibold uppercase">
                {{ $vehicle->vehicle_number }}
            </td>
            <td class="py-1 px-2 uppercase">
                {{ $vehicle->registration_number }}
            </td>
            <td class="py-1 px-2">
                {{ $vehicle->vehicle_type ?? '—' }}
            </td>
            <td class="py-1 px-2">
                {{ $vehicle->company?->company_name ?? '—' }}
            </td>
            <td class="py-1 px-2">
                {{ $vehicle->supplier?->supplier_name ?? '—' }}
            </td>
            <td class="py-1 px-2">
                @if ($vehicle->employee)
                {{ $vehicle->employee->name }}
                @if ($vehicle->employee->phone)
                <span class="block text-xs text-gray-500">{{ $vehicle->employee->phone }}</span>
                @endif
                @elseif ($vehicle->driver_name)
                {{ $vehicle->driver_name }}
                @if ($vehicle->driver_phone)
                <span class="block text-xs text-gray-500">{{ $vehicle->driver_phone }}</span>
                @endif
                @else
                Unassigned
                @endif
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $vehicle->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $vehicle->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('vehicles.show', $vehicle) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                        title="View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                    <a href="{{ route('vehicles.edit', $vehicle) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>

    @push('scripts')
    <script src="{{ asset('jsandcss/pdf-generator.js') }}"></script>
    <script>
        // Client-side PDF generation for vehicles list
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare vehicle data from the blade template
            const vehicles = @json($vehicles->items());
            
            // Initialize PDF Generator with custom configuration
            const vehiclePdfGenerator = new PDFGenerator({
                buttonId: 'vehicle-pdf-btn',
                documentTitle: 'Vehicles List',
                fileName: 'vehicles-list-' + new Date().toISOString().split('T')[0] + '.pdf',
                orientation: 'l', // landscape for wide table
                format: 'a4',
                logoUrl: '{{ asset("icons-images/logo (1).png") }}',
                appName: '{{ config("app.name") }}',
                debug: true,
                
                // Custom PDF generation function
                generateFn: async function(generator) {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF(generator.config.orientation, 'pt', generator.config.format);
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    
                    let y = 20;
                    
                    // Add logo
                    await generator.addLogo(doc, 35, y);
                    
                    // Draw header
                    y = generator.drawHeader(
                        doc, 
                        'VEHICLES LIST',
                        'Generated on ' + generator.formatDate(new Date(), 'long')
                    );
                    
                    y += 10;
                    
                    // Summary info
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(9);
                    doc.setTextColor(60);
                    doc.text('Total Vehicles: ' + vehicles.length, 40, y);
                    doc.text('Generated by: {{ auth()->user()->name }}', pageWidth - 40, y, { align: 'right' });
                    
                    y += 30;
                    
                    // Prepare table data
                    const headers = [
                        { label: '#', align: 'center' },
                        { label: 'Vehicle Number', align: 'left' },
                        { label: 'Registration', align: 'left' },
                        { label: 'Type', align: 'left' },
                        { label: 'Company', align: 'left' },
                        { label: 'Supplier', align: 'left' },
                        { label: 'Driver', align: 'left' },
                        { label: 'Status', align: 'center' }
                    ];
                    
                    const rows = vehicles.map((vehicle, index) => {
                        let driverInfo = 'Unassigned';
                        if (vehicle.employee && vehicle.employee.name) {
                            driverInfo = vehicle.employee.name;
                        } else if (vehicle.driver_name) {
                            driverInfo = vehicle.driver_name;
                        }
                        
                        return [
                            index + 1,
                            (vehicle.vehicle_number || '—').toUpperCase(),
                            (vehicle.registration_number || '—').toUpperCase(),
                            vehicle.vehicle_type || '—',
                            vehicle.company?.company_name || '—',
                            vehicle.supplier?.supplier_name || '—',
                            driverInfo,
                            vehicle.is_active ? 'ACTIVE' : 'INACTIVE'
                        ];
                    });
                    
                    // Draw table with custom column widths for landscape
                    const columnWidths = [40, 100, 100, 80, 120, 120, 120, 70];
                    y = generator.drawTable(doc, headers, rows, y, {
                        margin: 40,
                        rowHeight: 22,
                        fontSize: 8,
                        columnWidths: columnWidths
                    });
                    
                    // Draw footer
                    generator.drawFooter(doc, '{{ config("app.name") }} - Vehicle Management System');
                    
                    // Save the PDF
                    doc.save(generator.config.fileName);
                    generator.log('PDF saved successfully');
                }
            });
        });
    </script>
    @endpush
</x-app-layout>