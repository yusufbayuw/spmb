@include('components.panel-body-end')

@auth
    @if (session()->has('spmb_registration_draft_completed'))
        <script id="spmb-registration-draft-completed" type="application/json">@json(session('spmb_registration_draft_completed'))</script>
    @endif

    <script src="{{ asset('js/registration-exit-guard.js') }}" defer></script>
@endauth
