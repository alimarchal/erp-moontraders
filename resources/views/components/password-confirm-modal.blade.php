@props([
'id' => 'passwordModal',
'title' => 'Confirm Action',
'message' => 'Enter your password to confirm this action.',
'warningClass' => 'text-gray-600 dark:text-gray-400',
'confirmButtonText' => 'Confirm',
'confirmButtonClass' => 'bg-emerald-600 hover:bg-emerald-700',
])

<!-- Password Confirmation Modal -->
<div id="{{ $id }}" style="display: none;"
    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $title }}</h3>
            <p class="text-sm {{ $warningClass }} mb-4">
                {{ $message }}
            </p>
            <div class="mb-4">
                <label for="{{ $id }}_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    🔒 Enter your password to confirm:
                </label>
                <input type="password" id="{{ $id }}_input" autocomplete="off"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100"
                    placeholder="Password">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="window.closePasswordModal('{{ $id }}')"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="button" onclick="window.submitPasswordModal('{{ $id }}')"
                    class="px-4 py-2 {{ $confirmButtonClass }} text-white rounded-md">
                    {{ $confirmButtonText }}
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    // Global functions for password modal management
    window.showPasswordModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            const input = document.getElementById(modalId + '_input');
            if (input) {
                input.value = ''; // Clear previous value
                setTimeout(() => input.focus(), 100);
            }
        }
    };

    window.closePasswordModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            const input = document.getElementById(modalId + '_input');
            if (input) {
                input.value = '';
            }
        }
    };

    window.submitPasswordModal = function(modalId) {
        const input = document.getElementById(modalId + '_input');
        const password = input ? input.value : '';
        
        if (!password || password.trim() === '') {
            alert('Password is required to confirm this action.');
            return;
        }

        // Trigger custom event with password
        const event = new CustomEvent('passwordConfirmed', { 
            detail: { modalId, password } 
        });
        document.dispatchEvent(event);
        
        window.closePasswordModal(modalId);
    };

    // ESC key closes all modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('[id$="Modal"]').forEach(modal => {
                if (modal.style.display === 'flex') {
                    window.closePasswordModal(modal.id);
                }
            });
        }
    });

    // Enter key submits in password inputs
    document.addEventListener('keypress', function(event) {
        if (event.key === 'Enter' && event.target.type === 'password') {
            const modalId = event.target.id.replace('_input', '');
            window.submitPasswordModal(modalId);
        }
    });
</script>
@endpush
@endonce