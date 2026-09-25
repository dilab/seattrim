<?php

use App\Models\LicenseAction;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit log')] class extends Component {
    use WithPagination;

    #[Url] public string $action = '';
    #[Url] public string $status = '';
    #[Url] public string $source = '';
    #[Url] public string $search = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    public function mount(): void
    {
        $this->authorize('view', app(Tenancy::class)->currentOrFail());
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** @return Builder<LicenseAction> */
    public function query(): Builder
    {
        $query = LicenseAction::query()->with('performer')->latest('id');

        if ($this->action !== '') {
            $query->where('action', $this->action);
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->source !== '') {
            $query->where('source', $this->source);
        }
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn (Builder $q) => $q->where('member_email', 'like', $term)->orWhere('member_name', 'like', $term));
        }
        if ($this->from !== '') {
            $query->whereDate('created_at', '>=', $this->from);
        }
        if ($this->to !== '') {
            $query->whereDate('created_at', '<=', $this->to);
        }

        return $query;
    }

    /** @return LengthAwarePaginator<int, LicenseAction> */
    #[Computed]
    public function actions(): LengthAwarePaginator
    {
        return $this->query()->paginate(50);
    }

    public function canExport(): bool
    {
        return Features::allows(app(Tenancy::class)->currentOrFail(), Features::CSV_EXPORT);
    }

    public function exportUrl(): string
    {
        return route('audit.export', array_filter([
            'action' => $this->action, 'status' => $this->status, 'source' => $this->source,
            'search' => $this->search, 'from' => $this->from, 'to' => $this->to,
        ]));
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Audit log') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every downgrade and restore, whether it succeeded, was skipped or failed, and why. Rows are never deleted.') }}</flux:text>
        </div>
        @if ($this->canExport())
            <flux:button :href="$this->exportUrl()" icon="arrow-down-tray" variant="filled" size="sm" data-test="audit-export">{{ __('Export CSV') }}</flux:button>
        @else
            <flux:tooltip :content="Features::deniedMessage(Features::CSV_EXPORT)">
                <flux:button icon="arrow-down-tray" variant="filled" size="sm" disabled>{{ __('Export CSV') }}</flux:button>
            </flux:tooltip>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Email or name')" class="w-60" clearable />
        <flux:select wire:model.live="action" size="sm" class="w-36">
            <flux:select.option value="">{{ __('Any action') }}</flux:select.option>
            <flux:select.option value="downgrade">{{ __('Downgrade') }}</flux:select.option>
            <flux:select.option value="restore">{{ __('Restore') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="status" size="sm" class="w-36">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (['queued', 'done', 'skipped', 'failed'] as $s)
                <flux:select.option value="{{ $s }}">{{ ucfirst($s) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="source" size="sm" class="w-36">
            <flux:select.option value="">{{ __('Any source') }}</flux:select.option>
            @foreach (['manual', 'bulk', 'rule'] as $s)
                <flux:select.option value="{{ $s }}">{{ ucfirst($s) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="from" type="date" size="sm" class="w-40" />
        <flux:input wire:model.live="to" type="date" size="sm" class="w-40" />
    </div>

    <flux:table :paginate="$this->actions">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('User') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column>{{ __('By') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Detail') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->actions as $row)
                <flux:table.row :key="$row->id">
                    <flux:table.cell class="whitespace-nowrap text-xs">{{ $row->created_at?->toDayDateTimeString() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $row->member_name ?: '—' }}</div>
                        <flux:text class="text-xs">{{ $row->member_email }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst($row->action) }}@if ($row->dry_run) <flux:badge size="sm" color="zinc">{{ __('dry run') }}</flux:badge>@endif</flux:table.cell>
                    <flux:table.cell>{{ $row->source }}</flux:table.cell>
                    <flux:table.cell>{{ $row->performer?->name ?? __('rule') }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="match ($row->status) { 'done' => 'green', 'failed' => 'red', 'skipped' => 'amber', default => 'zinc' }">{{ $row->status }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="max-w-md text-xs">
                        {{ $row->reason }}
                        @if ($row->zoom_tracking_id)<div class="text-zinc-400">{{ __('tracking') }} {{ $row->zoom_tracking_id }}</div>@endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7" class="text-center text-zinc-500">{{ __('No actions yet.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
