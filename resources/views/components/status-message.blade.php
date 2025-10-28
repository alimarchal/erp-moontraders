@if (session('success'))
    <div {{ $attributes->merge(['class' => 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 max-w-7xl mx-auto sm:px-6 lg:px-8 mb-2 shadow-xl']) }}
         role="alert">
        <p class="font-bold">Success</p>
        <p>{{ session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div {{ $attributes->merge(['class' => 'max-w-7xl mx-auto sm:px-6 lg:px-8 bg-red-100 border-l-4 border-red-500 text-red-700 py-4 mx-6']) }}
         role="alert">
        <p class="font-bold">Error</p>
        <p>{{ session('error') }}</p>
    </div>
@endif

@if (session('warning'))
    <div {{ $attributes->merge(['class' => 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mx-6']) }}
         role="alert">
        <p class="font-bold">Warning</p>
        <p>{{ session('warning') }}</p>
    </div>
@endif

@if (session('info'))
    <div {{ $attributes->merge(['class' => 'bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mx-6']) }}
         role="alert">
        <p class="font-bold">Information</p>
        <p>{{ session('info') }}</p>
    </div>
@endif
