{{-- Searchable dropdowns: every <select class="js-select2"> becomes a Select2 box (type to find an option). --}}
@push('scripts')
    <script>
        $(function () {
            $('select.js-select2').each(function () {
                const $select = $(this);
                const hasEmpty = $select.find('option[value=""]').length > 0;

                $select.select2({
                    width: '100%',
                    placeholder: $select.find('option[value=""]').first().text() || 'Select',
                    allowClear: hasEmpty && ! $select.prop('required'),
                    dropdownParent: $select.closest('.uf-modal-box').length ? $select.closest('.uf-modal-box') : $(document.body),
                });
            });
        });
    </script>
@endpush
