@push('scripts')
    <script>
        // Searchable dropdowns. The party box is plain text (anyone can be paid) with earlier names as suggestions.
        $(function () {
            $('.ev-select').each(function () {
                const $select = $(this);
                $select.select2({
                    width: '100%',
                    placeholder: $select.data('placeholder') || $select.find('option[value=""]').text() || 'Select',
                    // The company can be cleared with the cross; an empty company is a general (Moon Traders) expense.
                    allowClear: !!$select.data('clearable'),
                });
            });
        });
    </script>
@endpush
