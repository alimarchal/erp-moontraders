<!DOCTYPE html>
<html>

<head>
    <title>Vehicles List</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
        }

        .container {
            width: 100%;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            height: 60px;
            width: auto;
            object-fit: contain;
        }

        .company-info {
            text-align: left;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }

        .document-type {
            font-size: 10px;
            color: #6b7280;
            margin: 5px 0 0 0;
        }

        .header-right {
            text-align: right;
            font-size: 10px;
            color: #374151;
        }

        .header-right div {
            margin-bottom: 3px;
        }

        .filters-section {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
        }

        .filters-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
            color: #374151;
        }

        .filters-content {
            font-size: 9px;
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table thead {
            background-color: #f3f4f6;
        }

        table thead th {
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            color: #111827;
            border: 1px solid #d1d5db;
        }

        table tbody td {
            padding: 6px 6px;
            border: 1px solid #e5e7eb;
            font-size: 9px;
            color: #374151;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }

        .page-number {
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            margin-top: 10px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                @if(file_exists(public_path('icons-images/logo (1).png')))
                <img src="{{ public_path('icons-images/logo (1).png') }}" alt="Logo" class="logo">
                @endif
                <div class="company-info">
                    <p class="company-name">{{ config('app.name', 'MoonTrader') }}</p>
                    <p class="document-type">Vehicles List</p>
                </div>
            </div>
            <div class="header-right">
                <div><strong>Generated:</strong> {{ $generatedAt->format('d M Y, h:i A') }}</div>
                <div><strong>By:</strong> {{ $generatedBy }}</div>
                <div><strong>Total Vehicles:</strong> {{ $vehicles->count() }}</div>
            </div>
        </div>

        <!-- Applied Filters -->
        @if(!empty($filters))
        <div class="filters-section">
            <div class="filters-title">Applied Filters:</div>
            <div class="filters-content">
                @foreach($filters as $key => $value)
                @if($value)
                <span style="margin-right: 15px;">
                    <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                </span>
                @endif
                @endforeach
            </div>
        </div>
        @endif

        <!-- Table -->
        @if($vehicles->count() > 0)
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">#</th>
                    <th style="width: 12%;">Vehicle Number</th>
                    <th style="width: 12%;">Registration</th>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 15%;">Company</th>
                    <th style="width: 15%;">Supplier</th>
                    <th style="width: 20%;">Driver</th>
                    <th class="text-center" style="width: 8%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vehicles as $index => $vehicle)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="uppercase"><strong>{{ $vehicle->vehicle_number }}</strong></td>
                    <td class="uppercase">{{ $vehicle->registration_number }}</td>
                    <td>{{ $vehicle->vehicle_type ?? '—' }}</td>
                    <td>{{ $vehicle->company?->company_name ?? '—' }}</td>
                    <td>{{ $vehicle->supplier?->supplier_name ?? '—' }}</td>
                    <td>
                        @if($vehicle->employee)
                        {{ $vehicle->employee->name }}
                        @if($vehicle->employee->phone)
                        <br><span style="font-size: 8px; color: #6b7280;">{{ $vehicle->employee->phone }}</span>
                        @endif
                        @elseif($vehicle->driver_name)
                        {{ $vehicle->driver_name }}
                        @if($vehicle->driver_phone)
                        <br><span style="font-size: 8px; color: #6b7280;">{{ $vehicle->driver_phone }}</span>
                        @endif
                        @else
                        <span style="color: #9ca3af;">Unassigned</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $vehicle->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $vehicle->is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="no-data">
            No vehicles found matching the selected criteria.
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div>{{ config('app.name', 'MoonTrader') }} - Vehicle Management System</div>
            <div>Page 1 of 1</div>
        </div>
    </div>
</body>

</html>