<?php

use App\Enums\Role;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create your organization')] #[Layout('layouts.auth')] class extends Component {
    public string $name = '';
    public string $timezone = 'UTC';

    public function mount(): void
    {
        $this->timezone = config('app.timezone', 'UTC');
    }

    /** @return array<int, string> */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $user = Auth::user();

        $organization = DB::transaction(function () use ($validated, $user): Organization {
            $organization = Organization::create($validated);
            $organization->addMember($user, Role::Owner);
            $user->switchToOrganization($organization);

            return $organization;
        });

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create your organization')" :description="__('One organization is one Zoom account. You can add more later, for example if you manage several customers.')" />

    <form wire:submit="create" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('Organization name')" type="text" required autofocus placeholder="Acme School District" />

        <flux:select wire:model="timezone" :label="__('Timezone')" :description="__('Daily scans run at 03:00 in this timezone.')">
            @foreach ($this->timezones() as $tz)
                <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="primary" type="submit" class="w-full" data-test="create-organization-button">
            {{ __('Continue') }}
        </flux:button>
    </form>

    @if (auth()->user()->organizations()->exists())
        <flux:text class="text-center">
            <flux:link :href="route('dashboard')" wire:navigate>{{ __('Back to your current organization') }}</flux:link>
        </flux:text>
    @endif
</div>
