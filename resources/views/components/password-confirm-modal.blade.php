@props([
    'id' => 'passwordModal',
    'title' => 'Confirm Action',
    'message' => 'Enter your password to confirm this action.',
    'messageDirection' => 'ltr',
    'secondaryMessage' => null,
    'secondaryMessageDirection' => 'ltr',
    'errorKey' => null,
    'warningClass' => 'text-gray-600',
    'confirmButtonText' => 'Confirm',
    'confirmButtonClass' => 'bg-emerald-600 hover:bg-emerald-700',
])

<div id="{{ $id }}" style="display: none;" class="fixed inset-0 z-50">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm"></div>

    <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
        <div class="relative w-full max-w-lg overflow-hidden rounded-lg bg-white text-left shadow-xl">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div
                        class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 sm:mx-0 sm:size-10">
                        <svg class="size-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="mt-3 w-full text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
                        <p dir="{{ $messageDirection }}" class="mt-2 whitespace-pre-line text-sm {{ $warningClass }}">
                            {{ $message }}
                        </p>
                        @if ($secondaryMessage)
                            <p dir="{{ $secondaryMessageDirection }}"
                                class="mt-3 whitespace-pre-line text-right text-base font-semibold leading-6 {{ $warningClass }}">
                                {{ $secondaryMessage }}
                            </p>
                        @endif
                        <div class="mt-4">
                            <label for="{{ $id }}_input" class="block text-sm font-medium text-gray-700">
                                Enter your password to confirm:
                            </label>
                            <input type="password" id="{{ $id }}_input" autocomplete="current-password"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Password">
                            @if ($errorKey && $errors->has($errorKey))
                                <p class="mt-2 text-sm font-medium text-red-600" role="alert">
                                    {{ $errors->first($errorKey) }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                <button type="button" onclick="window.closePasswordModal('{{ $id }}')"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" onclick="window.submitPasswordModal('{{ $id }}')"
                    class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition {{ $confirmButtonClass }}">
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
            window.showPasswordModal = function (modalId) {
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

            window.closePasswordModal = function (modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    const input = document.getElementById(modalId + '_input');
                    if (input) {
                        input.value = '';
                    }
                }
            };

            window.submitPasswordModal = function (modalId) {
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
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[id$="Modal"]').forEach(modal => {
                        if (modal.style.display === 'flex') {
                            window.closePasswordModal(modal.id);
                        }
                    });
                }
            });

            // Enter key submits in password inputs
            document.addEventListener('keypress', function (event) {
                if (event.key === 'Enter' && event.target.type === 'password') {
                    const modalId = event.target.id.replace('_input', '');
                    window.submitPasswordModal(modalId);
                }
            });
        </script>
    @endpush
@endonce

@if ($errorKey && $errors->has($errorKey))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.showPasswordModal('{{ $id }}');
            });
        </script>
    @endpush
@endif