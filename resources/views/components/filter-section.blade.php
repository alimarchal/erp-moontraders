@props([
'action' => '#',
'method' => 'GET',
])

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg" id="filters" style="display: none">
        <div class="p-6">
            <form method="{{ $method }}" action="{{ $action }}">
                @if($method !== 'GET')
                @csrf
                @method($method)
                @endif

                {{ $slot }}

                <!-- Submit Button -->
                <x-submit-button />
            </form>
        </div>
    </div>
</div>

@push('modals')
<script>
    const targetDiv = document.getElementById("filters");
    const btn = document.getElementById("toggle");

    function showFilters() {
        targetDiv.style.display = 'block';
        targetDiv.style.opacity = '0';
        targetDiv.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            targetDiv.style.opacity = '1';
            targetDiv.style.transform = 'translateY(0)';
        }, 10);
    }

    function hideFilters() {
        targetDiv.style.opacity = '0';
        targetDiv.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            targetDiv.style.display = 'none';
        }, 300);
    }

    btn.onclick = function(event) {
        event.stopPropagation();
        if (targetDiv.style.display === "none") {
            showFilters();
        } else {
            hideFilters();
        }
    };

    // Hide filters when clicking outside
    document.addEventListener('click', function(event) {
        if (targetDiv.style.display === 'block' && !targetDiv.contains(event.target) && event.target !== btn) {
            hideFilters();
        }
    });

    // Prevent clicks inside the filter from closing it
    targetDiv.addEventListener('click', function(event) {
        event.stopPropagation();
    });

    // Add CSS for smooth transitions
    const style = document.createElement('style');
    style.textContent = `#filters {transition: opacity 0.3s ease, transform 0.3s ease;}`;
    document.head.appendChild(style);
</script>
@endpush