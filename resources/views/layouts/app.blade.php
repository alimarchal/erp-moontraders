<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('icons-images/logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <script src="{{ url('apexcharts/apexcharts.js') }}"></script>
    <link href="{{ url('select2/select2.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ url('jsandcss/daterangepicker.min.css') }}">
    <script src="{{ url('jsandcss/moment.min.js') }}"></script>
    <script src="{{ url('jsandcss/knockout-3.5.1.js') }}" defer></script>
    <script src="{{ url('jsandcss/daterangepicker.min.js') }}" defer></script>
    @stack('header')

    <style>
        .select2 {
            /*width:100%!important;*/
            width: auto !important;
            display: block;
        }

        .select2-container .select2-selection--single {
            height: auto;
            /* Reset the height if necessary */
            padding: 0.63rem 1rem;
            margin-top: 0.25rem;
            /* This should match Tailwind's py-2 px-4 */
            line-height: 1.25;
            /* Adjust based on Tailwind's line height for consistency */
            /*font-size: 0.875rem; !* Matches Tailwind's text-sm *!*/
            border: 1px solid #d1d5db;
            /* Tailwind's border-gray-300 */
            border-radius: 0.375rem;
            /* Tailwind's rounded-md */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            /* Tailwind's shadow-sm */
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 1.25;
            /* Aligns text vertically */
            padding-left: 0;
            /* Adjust if needed */
            padding-right: 0;
            /* Adjust if needed */
        }

        /*.select2-selection__arrow*/
        .select2-container .select2-selection--single {
            height: auto;
            /* Ensure the arrow aligns with the adjusted height */
            right: 0.5rem;
            /* Align the arrow similarly to Tailwind's padding */

        }

        .select2-selection__arrow {
            top: 8px !important;
            right: 10px !important;
        }
    </style>
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
</head>

<body class="font-sans antialiased">
    <x-banner />

    <div class="min-h-screen bg-gray-100">
        @livewire('navigation-menu')

        <!-- Page Heading -->
        @if (isset($header))
        <header class="bg-white shadow print:hidden">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
        @endif

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>
    <script src="{{ url('select2/jquery-3.5.1.js') }}"></script>
    <script src="{{ url('select2/select2.min.js') }}" defer></script>
    <script>
        $(document).ready(function () {
                $('.select2').select2({
                width: '100%',
                placeholder: 'Select an option',
                allowClear: false,
            });

            // Handle label clicks for Select2 elements
            $('label').on('click', function(e) {
                var forId = $(this).attr('for');
                if (forId) {
                    var $element = $('#' + forId);
                    if ($element.hasClass('select2') || $element.hasClass('select2-hidden-accessible')) {
                        e.preventDefault();
                        $element.select2('open');
                    }
                }
            });
        });

            $('form').submit(function(){
                // If x-button does not render as a traditional submit button, target it directly by ID or class
                $('#submit-btn').attr('disabled', 'disabled');
            });
    </script>
    @stack('modals')

    {{-- Allow views to push inline scripts -- e.g. @push('scripts') -- so they are rendered here --}}
    @stack('scripts')

    @livewireScripts
</body>

</html>