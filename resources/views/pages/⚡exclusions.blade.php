<?php

use App\Models\Exclusion;
use App\Models\Organization;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Exclusions')] class extends Component {
    public string $type = 'email';
    public string $value = '';
    public string $reason = '';
    public ?string $expires_at = null;

    public function mount(): void
    {
        $this->authorize('view', $this->organization());
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    /** @return Collection<int, Exclusion> */
    #[Computed]
    public function exclusions(): Collection
    {
        return Exclusion::query()->with('creator')->orderBy('type')->orderBy('value')->get();
    }

    public function canManage(): bool
    {
        return auth()->user()->canManage($this->organization());
    }

    public function add(): void
    {
        $this->authorize('update', $this->organization());

        $validated = $this->validate([
            'type' => ['required', Rule::in(Exclusion::TYPES)],
            'value' => ['required', 'string', 'max:255', $this->type === 'email' ? 'email' : 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $value = $this->type === 'group' ? trim($validated['value']) : mb_strtolower(trim(ltrim($validated['value'], '@')));

        if (Exclusion::query()->where('type', $validated['type'])->where('value', $value)->exists()) {
            $this->addError('value', __('Already excluded.'));

            return;
        }

        Exclusion::query()->create([
            'type' => $validated['type'],
            'value' => $value,
            'reason' => $validated['reason'] ?: null,
            'created_by' => auth()->id(),
            'expires_at' => $validated['expires_at'] ?: null,
        ]);

        $this->reset('value', 'reason', 'expires_at');
        unset($this->exclusions);
        Flux::toast(variant: 'success', text: __('Exclusion added. It applies from the next scan.'));
    }

    public function remove(int $id): void
    {
        $this->authorize('update', $this->organization());
        Exclusion::query()->findOrFail($id)->delete();
        unset($this->exclusions);
        Flux::toast(text: __('Exclusion removed.'));
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Exclusions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Users matching an exclusion are never suggested for downgrade and never automated. Exclusions apply from the next scan.') }}</flux:text>
    </div>

    @if ($this->canManage())
        <flux:card>
            <form wire:submit="add" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="type" :label="__('Type')">
                    <flux:select.option value="email">{{ __('Email address') }}</flux:select.option>
                    <flux:select.option value="domain">{{ __('Email domain') }}</flux:select.option>
                    <flux:select.option value="group">{{ __('Zoom group id') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="value" :label="$type === 'email' ? __('Email') : ($type === 'domain' ? __('Domain, e.g. board.example.edu') : __('Group id from Zoom'))" required />
                <flux:input wire:model="reason" :label="__('Reason (shown in the members table)')" />
                <flux:input wire:model="expires_at" type="date" :label="__('Expires (optional)')" />
                <div class="sm:col-span-2">
                    <flux:button type="submit" variant="primary" data-test="add-exclusion">{{ __('Add exclusion') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Value') }}</flux:table.column>
            <flux:table.column>{{ __('Reason') }}</flux:table.column>
            <flux:table.column>{{ __('Expires') }}</flux:table.column>
            <flux:table.column>{{ __('Added by') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->exclusions as $exclusion)
                <flux:table.row :key="$exclusion->id">
                    <flux:table.cell>{{ ucfirst($exclusion->type) }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $exclusion->value }}</flux:table.cell>
                    <flux:table.cell>{{ $exclusion->reason ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($exclusion->expires_at)
                            {{ $exclusion->expires_at->toFormattedDateString() }} @if ($exclusion->isExpired())<flux:badge size="sm" color="zinc">{{ __('expired') }}</flux:badge>@endif
                        @else — @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $exclusion->creator?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="remove({{ $exclusion->id }})" wire:confirm="{{ __('Remove this exclusion?') }}" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No exclusions yet.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
