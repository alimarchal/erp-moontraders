@if (\App\Http\Controllers\CustomerController::canViewHelp(auth()->user()))
    <a href="{{ route('customers.help') }}" class="ak-btn ak-btn-outline">How customers work</a>
@endif
