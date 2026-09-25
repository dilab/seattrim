<?php

use App\Enums\Bucket;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomMember;
use App\Scan\Window;
use App\Support\Money;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Members')] class extends Component {
    use WithPagination;

    #[Url] public string $bucket = '';
    #[Url] public string $status = '';
    #[Url] public string $dept = '';
    #[Url] public string $window = '';
    #[Url] public string $search = '';
    #[Url] public string $sort = 'name';
    #[Url] public string $dir = 'asc';
    #[Url] public bool $eligible = false;

    /** @var array<int, int> */
    public array $selected = [];
    public bool $selectPage = false;
    public ?int $open = null;

    public function mount(): void
    {
        $this->authorize('view', $this->organization());
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['bucket', 'status', 'dept', 'window', 'search', 'eligible'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->selectPage = false;
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->dir = 'asc';
        }
    }

    /** @return Builder<ZoomMember> */
    public function query(): Builder
    {
        $query = ZoomMember::query()->present();

        if ($this->bucket !== '' && Bucket::tryFrom($this->bucket)) {
            $query->where('bucket', $this->bucket);
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->dept !== '') {
            $query->where('dept', $this->dept);
        }
        if ($this->window !== '') {
            $query->where('last_hosted_window', $this->window);
        }
        if ($this->eligible) {
            $query->where('eligible_for_downgrade', true);
        }
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn (Builder $q) => $q->where('email', 'like', $term)->orWhere('name', 'like', $term));
        }

        $sort = in_array($this->sort, ['name', 'email', 'dept', 'type', 'status', 'last_hosted_window', 'last_login_at', 'bucket'], true) ? $this->sort : 'name';
        $dir = $this->dir === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $dir)->orderBy('id');
    }

    /** @return LengthAwarePaginator<int, ZoomMember> */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        return $this->query()->paginate(25);
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function departments(): Collection
    {
        return ZoomMember::query()->present()->whereNotNull('dept')->distinct()->orderBy('dept')->pluck('dept');
    }

    /** @return array<int, string> */
    #[Computed]
    public function windows(): array
    {
        $days = Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->value('threshold_days') ?? 90;

        return [...Window::labels(max((int) $days, 90)), Window::NONE, Window::UNKNOWN];
    }

    #[Computed]
    public function openMember(): ?ZoomMember
    {
        return $this->open ? ZoomMember::query()->with('actions.performer')->find($this->open) : null;
    }

    public function show(int $id): void
    {
        $this->open = $id;
        unset($this->openMember);
        $this->modal('member')->show();
    }

    #[On('member-updated')]
    #[On('bulk-queued')]
    public function refreshList(): void
    {
        unset($this->members, $this->openMember);
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value ? $this->members->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public function money(?int $cents): string
    {
        return Money::forOrganization($this->organization(), $cents);
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Members') }}</flux:heading>
            <flux:text class="mt-1">{{ __(':n users in the last scan', ['n' => $this->members->total()]) }}</flux:text>
        </div>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" class="w-72" clearable />
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:select wire:model.live="bucket" size="sm" class="w-56" :placeholder="__('All buckets')">
            <flux:select.option value="">{{ __('All buckets') }}</flux:select.option>
            @foreach (Bucket::cases() as $b)
                <flux:select.option value="{{ $b->value }}">{{ $b->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" size="sm" class="w-40">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Deactivated') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="dept" size="sm" class="w-44">
            <flux:select.option value="">{{ __('Any department') }}</flux:select.option>
            @foreach ($this->departments as $d)
                <flux:select.option value="{{ $d }}">{{ $d }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="window" size="sm" class="w-48">
            <flux:select.option value="">{{ __('Any last hosted') }}</flux:select.option>
            @foreach ($this->windows as $w)
                <flux:select.option value="{{ $w }}">{{ $w === 'none' ? __('Never hosted') : ($w === 'unknown' ? __('Unknown') : __(':w days ago', ['w' => $w])) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="eligible" :label="__('Eligible for downgrade only')" />
    </div>

    <livewire:pages::members.bulk-bar :selected="$selected" wire:key="bulk-bar" />

    <flux:table :paginate="$this->members">
        <flux:table.columns>
            <flux:table.column class="w-8"><flux:checkbox wire:model.live="selectPage" /></flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'name'" :direction="$dir" wire:click="sortBy('name')">{{ __('User') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'dept'" :direction="$dir" wire:click="sortBy('dept')">{{ __('Dept') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'type'" :direction="$dir" wire:click="sortBy('type')">{{ __('License') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'status'" :direction="$dir" wire:click="sortBy('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'last_hosted_window'" :direction="$dir" wire:click="sortBy('last_hosted_window')">{{ __('Last hosted') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sort === 'bucket'" :direction="$dir" wire:click="sortBy('bucket')">{{ __('Bucket') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->members as $member)
                <flux:table.row :key="$member->id" class="cursor-pointer" data-test="member-row">
                    <flux:table.cell><flux:checkbox wire:model.live="selected" value="{{ $member->id }}" /></flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">
                        <div class="font-medium">{{ $member->name ?: '—' }}</div>
                        <flux:text class="text-xs">{{ $member->email }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">{{ $member->dept ?? '—' }}</flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">{{ $member->typeLabel() }}</flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">{{ $member->statusLabel() }}</flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">
                        @if ($member->last_hosted_window === 'none') <span class="text-amber-600">{{ __('never') }}</span>
                        @elseif ($member->last_hosted_window === 'unknown') <span class="text-zinc-400">{{ __('unknown') }}</span>
                        @else {{ __(':w days ago', ['w' => $member->last_hosted_window]) }} @endif
                    </flux:table.cell>
                    <flux:table.cell wire:click="show({{ $member->id }})">
                        <flux:badge size="sm" :color="$member->bucket->color()">{{ $member->bucket->label() }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">{{ __('No members match these filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="member" variant="flyout" class="md:w-[32rem]">
        @if ($this->openMember)
            <livewire:pages::members.detail :member="$this->openMember" :wire:key="'detail-'.$this->openMember->id" />
        @endif
    </flux:modal>
</div>
