@props([
    'id',
    'name',
    'label' => null,
    'placeholder' => null,
    'autocomplete' => 'current-password',
    'required' => false,
    'autofocus' => false,
    'leadingIcon' => null,
    'errorMessages' => [],
])

@php
    $hasError = count($errorMessages) > 0;
@endphp

<div>
    @if ($label)
        <x-input-label for="{{ $id }}" :value="$label" />
    @endif

    <div class="relative" data-password-field>
        @if ($leadingIcon)
            <x-dynamic-component :component="$leadingIcon"
                class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
        @endif

        <input id="{{ $id }}" name="{{ $name }}" type="password" data-password-input
            autocomplete="{{ $autocomplete }}" @required($required) @autofocus($autofocus)
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['block w-full pr-12', 'pl-11' => $leadingIcon]) }}>

        <button type="button" data-password-toggle aria-controls="{{ $id }}" aria-label="Show password"
            aria-pressed="false"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
            <x-heroicon-o-eye data-password-toggle-show class="hidden h-5 w-5" aria-hidden="true" />
            <x-heroicon-o-eye-slash data-password-toggle-hide class="h-5 w-5" aria-hidden="true" />
        </button>
    </div>

    @if ($hasError)
        <x-input-error id="{{ $id }}-error" :messages="$errorMessages" class="mt-2" />
    @endif
</div>
