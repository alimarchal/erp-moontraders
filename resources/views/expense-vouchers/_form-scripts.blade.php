@push('scripts')
    <script>
        // Searchable dropdowns; the party box also accepts a new name typed in.
        $(function () {
            $('.ev-select').each(function () {
                const $select = $(this);
                $select.select2({
                    width: '100%',
                    placeholder: $select.find('option[value=""]').text() || 'Select',
                    // Clearing the company goes back to a general (Moon Traders) expense.
                    allowClear: ! $select.prop('required') && $select.find('option[value=""]').length > 0,
                });
            });
            $('.ev-party').select2({ width: '100%', tags: true, placeholder: 'Party name', allowClear: true });
        });
    </script>
@endpush
