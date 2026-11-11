@include('claim-registers.partials.form-page', [
    'title' => 'Edit claim '.($claimRegister->reference_number ?? '#'.$claimRegister->id),
    'subtitle' => 'Only an entry that has not been posted can be changed.',
    'action' => route('claim-registers.update', $claimRegister),
    'method' => 'PUT',
    'submitLabel' => 'Save changes',
])
