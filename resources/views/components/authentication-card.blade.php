<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0"
    style="background-image: url(/icons-images/background.jpg); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div>
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
        {{ $slot }}
    </div>
</div>