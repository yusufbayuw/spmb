{{-- Rendered in Filament's group HEADER (once per registration), not in document rows. --}}
<span
    class="hidden"
    aria-hidden="true"
    data-spmb-group-title="{{ $title }}"
    x-init="
        // Initialize only newly rendered groups; leave manual toggles intact.
        if ($wire.defaultGroupCollapsed && ! isGroupCollapsed(@js($title))) {
            collapsedGroups.push(@js($title))
        }
    "
></span>

@if ($registration && auth()->user() && app(\App\Services\RegistrationManualEmailService::class)->canCompose(auth()->user(), $registration))
    <x-filament::button
        type="button"
        size="xs"
        color="primary"
        icon="heroicon-o-paper-airplane"
        x-on:click.stop="$event.stopPropagation()"
        wire:click.stop="openApplicantEmail({{ (int) $registration->getKey() }})"
        aria-label="Kirim pesan email kepada {{ $registration->full_name }}"
    >
        Kirim Pesan
    </x-filament::button>
@endif
