<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Members')] class extends Component {
    public string $email = '';
    public string $role = 'admin';

    public function mount(): void
    {
        $this->authorize('view', app(Tenancy::class)->currentOrFail());
    }

    /** @return Collection<int, Membership> */
    #[Computed]
    public function memberships(): Collection
    {
        return app(Tenancy::class)->currentOrFail()->memberships()->with('user')->orderBy('created_at')->get();
    }

    public function canManage(): bool
    {
        return auth()->user()->canManage(app(Tenancy::class)->currentOrFail());
    }

    public function add(): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('manageMembers', $organization);

        $validated = $this->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Viewer->value])],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            $this->addError('email', __('No SeatTrim account with that email. Ask them to sign up first, then add them.'));

            return;
        }

        if ($organization->hasMember($user)) {
            $this->addError('email', __('Already a member.'));

            return;
        }

        $organization->addMember($user, Role::from($validated['role']));
        $this->reset('email');
        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Member added.'));
    }

    public function changeRole(int $membershipId, string $role): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('manageMembers', $organization);

        $membership = $organization->memberships()->findOrFail($membershipId);
        $newRole = Role::from($role);

        if ($membership->role === Role::Owner && $newRole !== Role::Owner && $organization->memberships()->where('role', Role::Owner->value)->count() === 1) {
            Flux::toast(variant: 'danger', text: __('An organization needs at least one owner.'));

            return;
        }

        if ($newRole === Role::Owner && ! auth()->user()->isOwnerOf($organization)) {
            Flux::toast(variant: 'danger', text: __('Only an owner can promote to owner.'));

            return;
        }

        $membership->update(['role' => $newRole]);
        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function remove(int $membershipId): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('manageMembers', $organization);

        $membership = $organization->memberships()->findOrFail($membershipId);

        if ($membership->role === Role::Owner && $organization->memberships()->where('role', Role::Owner->value)->count() === 1) {
            Flux::toast(variant: 'danger', text: __('An organization needs at least one owner.'));

            return;
        }

        if ($membership->role === Role::Owner && ! auth()->user()->isOwnerOf($organization)) {
            Flux::toast(variant: 'danger', text: __('Only an owner can remove an owner.'));

            return;
        }

        $membership->delete();
        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Members') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Members')" :subheading="__('Who can see and act on this organization')">
        <div class="my-6 space-y-8">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->memberships as $membership)
                        <flux:table.row :key="$membership->id">
                            <flux:table.cell>
                                <div class="font-medium">{{ $membership->user->name }}</div>
                                <flux:text class="text-xs">{{ $membership->user->email }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($this->canManage() && $membership->user_id !== auth()->id())
                                    <flux:select size="sm" wire:change="changeRole({{ $membership->id }}, $event.target.value)">
                                        @foreach (App\Enums\Role::cases() as $r)
                                            <flux:select.option value="{{ $r->value }}" :selected="$membership->role === $r">{{ $r->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:badge size="sm" :color="$membership->role->isOwner() ? 'amber' : ($membership->role->canManage() ? 'blue' : 'zinc')">{{ $membership->role->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-end">
                                @if ($this->canManage() && $membership->user_id !== auth()->id())
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $membership->id }})" wire:confirm="{{ __('Remove this member?') }}" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if ($this->canManage())
                <form wire:submit="add" class="space-y-4">
                    <flux:heading size="lg">{{ __('Add a member') }}</flux:heading>
                    <flux:text>{{ __('They need a SeatTrim account first. Viewers can see everything but cannot change Zoom users or settings.') }}</flux:text>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required />
                    <flux:select wire:model="role" :label="__('Role')">
                        <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
                        <flux:select.option value="viewer">{{ __('Viewer') }}</flux:select.option>
                    </flux:select>
                    <flux:button variant="primary" type="submit" data-test="add-member-button">{{ __('Add member') }}</flux:button>
                </form>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
