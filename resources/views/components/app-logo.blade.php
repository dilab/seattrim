@props([
    'sidebar' => false,
])

{{-- Horizontal lockup: mark + "Seat" (600) "Trim" (400, brand blue). Manrope is the brand face; the app falls back to its UI font. --}}
@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'SeatTrim')" {{ $attributes }}>
        <x-slot name="logo" class="flex size-8 items-center justify-center">
            <x-app-logo-icon class="size-8 text-brand-500" />
        </x-slot>
        <x-slot name="name"><x-app-wordmark /></x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'SeatTrim')" {{ $attributes }}>
        <x-slot name="logo" class="flex size-8 items-center justify-center">
            <x-app-logo-icon class="size-8 text-brand-500" />
        </x-slot>
        <x-slot name="name"><x-app-wordmark /></x-slot>
    </flux:brand>
@endif
