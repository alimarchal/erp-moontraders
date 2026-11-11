@include('claim-registers.partials.form-page', [
    'title' => 'Add claim',
    'subtitle' => 'Record a claim raised against a supplier, or a claim the supplier has settled.',
    'action' => route('claim-registers.store'),
    'method' => 'POST',
    'submitLabel' => 'Save claim',
])
