@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $id = $getId();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Start"
>
    <div
        {{
            \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                ->class(['fi-fo-field fi-fo-shield-captcha'])
        }}
    >
        <div
            x-data="{ isDark: document.documentElement.classList.contains('dark') }"
            x-init="
                const el = document.documentElement;
                const observer = new MutationObserver(() => { isDark = el.classList.contains('dark') });
                observer.observe(el, { attributes: true, attributeFilter: ['class'] });
            "
            class="relative flex"
        >
            <div class="relative max-w-full overflow-hidden rounded-lg">
                <img
                    alt="Kode keamanan"
                    aria-label="Kode keamanan"
                    class="fi-input-wrp block max-w-full"
                    :src="isDark ? @js($darkDataUri) : @js($lightDataUri)"
                />

                <div class="absolute right-1 top-1 flex h-9 w-9 items-center justify-center">
                    {{ $getAction('refresh') }}
                </div>
            </div>
        </div>

        <div class="mt-3">
            <x-filament::input.wrapper
                :disabled="$isDisabled"
                :valid="! $errors->has($statePath)"
                x-on:focus-input.stop="$el.querySelector('input')?.focus()"
                class="w-full"
            >
                <x-filament::input
                    :attributes="
                        \Filament\Support\prepare_inherited_attributes($getExtraInputAttributeBag())
                            ->merge([
                                'id' => $id,
                                'type' => 'text',
                                'inputmode' => 'text',
                                'autocomplete' => 'off',
                                'autocapitalize' => 'off',
                                'spellcheck' => 'false',
                                'disabled' => $isDisabled,
                                'required' => $isRequired(),
                                'placeholder' => 'Ketik kode keamanan',
                                $applyStateBindingModifiers('wire:model') => $statePath,
                            ], escape: false)
                    "
                />
            </x-filament::input.wrapper>
        </div>
    </div>
</x-dynamic-component>
