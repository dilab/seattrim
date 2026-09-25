<?php

use App\Actions\License\QueueLicenseActions;
use App\Actions\License\SafetyCap;
use App\Models\LicenseAction;
use App\Models\ZoomMember;
use App\Support\Features;
use App\Tenancy\Tenancy;
use App\Zoom\Data\ZoomUser;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/** Bulk downgrade / restore with confirmation, safety cap and live batch progress. */
new class extends Component {
    /** @var array<int, int> */
    #[Reactive]
    public array $selected = [];

    public string $mode = 'downgrade';
    public bool $acknowledgeCap = false;
    public ?string $batchId = null;

    /** @return Collection<int, ZoomMember> */
    #[Computed]
    public function members(): Collection
    {
        return ZoomMember::query()->present()->whereIn('id', $this->selected)->orderBy('name')->get();
    }

    /** @return Collection<int, ZoomMember> */
    #[Computed]
    public function actionable(): Collection
    {
        return $this->members->filter(fn (ZoomMember $m) => $this->mode === 'downgrade'
            ? $m->isLicensed() && $m->eligible_for_downgrade
            : $m->type === ZoomUser::TYPE_BASIC);
    }

    public function capLimit(): int
    {
        return SafetyCap::limit();
    }

    public function exceedsCap(): bool
    {
        return $this->mode === 'downgrade' && SafetyCap::exceeds($this->actionable->count());
    }

    public function open(string $mode): void
    {
        $this->mode = $mode;
        $this->acknowledgeCap = false;
        unset($this->actionable);
        $this->modal('bulk-confirm')->show();
    }

    public function run(QueueLicenseActions $queue): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('update', $organization);
        $this->resetErrorBag();

        if (! Features::allows($organization, Features::BULK_ACTIONS)) {
            Flux::toast(variant: 'danger', text: Features::deniedMessage(Features::BULK_ACTIONS));

            return;
        }

        if ($this->exceedsCap() && ! $this->acknowledgeCap) {
            $this->addError('acknowledgeCap', __('Confirm that you want to exceed the safety cap.'));

            return;
        }

        $result = $this->mode === 'downgrade'
            ? $queue->downgrade($organization, $this->actionable, LicenseAction::SOURCE_BULK, auth()->user())
            : $queue->restore($organization, $this->actionable, LicenseAction::SOURCE_BULK, auth()->user());

        $this->batchId = $result['actions']->first()?->batch_id;
        $this->modal('bulk-confirm')->close();
        Flux::toast(text: __(':n action(s) queued.', ['n' => $result['actions']->count()]));
        $this->dispatch('bulk-queued');
    }

    /** @return array{queued: int, done: int, failed: int, skipped: int, total: int} */
    #[Computed]
    public function progress(): array
    {
        $counts = LicenseAction::query()->where('batch_id', $this->batchId)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'queued' => (int) ($counts['queued'] ?? 0),
            'done' => (int) ($counts['done'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    public function pollProgress(): void
    {
        unset($this->progress);
        if ($this->progress['queued'] === 0) {
            $this->dispatch('member-updated');
        }
    }

    public function dismissProgress(): void
    {
        $this->batchId = null;
    }
}; ?>

<div>
    @if ($batchId)
        @php($p = $this->progress)
        <flux:callout :icon="$p['queued'] > 0 ? 'loading' : 'check-circle'" :variant="$p['queued'] > 0 ? 'secondary' : ($p['failed'] > 0 ? 'warning' : 'success')" @if ($p['queued'] > 0) wire:poll.2s="pollProgress" @endif>
            <flux:callout.heading>{{ $p['queued'] > 0 ? __('Working… :done of :total done', ['done' => $p['total'] - $p['queued'], 'total' => $p['total']]) : __('Finished: :done done, :skipped skipped, :failed failed', $p) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Details for each user are in the audit log.') }} <flux:link :href="route('audit')" wire:navigate>{{ __('Open audit log') }}</flux:link></flux:callout.text>
            @if ($p['queued'] === 0)
                <flux:button size="xs" variant="ghost" class="mt-2" wire:click="dismissProgress">{{ __('Dismiss') }}</flux:button>
            @endif
        </flux:callout>
    @elseif (count($selected) > 0 && auth()->user()->canManage(app(Tenancy::class)->currentOrFail()))
        <flux:callout icon="check-circle" variant="secondary">
            <div class="flex flex-wrap items-center gap-3">
                <flux:callout.text>{{ trans_choice(':n member selected|:n members selected', count($selected), ['n' => count($selected)]) }}</flux:callout.text>
                <flux:button size="sm" variant="primary" wire:click="open('downgrade')" data-test="bulk-downgrade">{{ __('Downgrade to Basic') }}</flux:button>
                <flux:button size="sm" variant="filled" wire:click="open('restore')" data-test="bulk-restore">{{ __('Restore Licensed') }}</flux:button>
            </div>
        </flux:callout>
    @endif

    <flux:modal name="bulk-confirm" class="md:w-[32rem]">
        @php($list = $this->actionable)
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $mode === 'downgrade' ? __('Downgrade :n user(s) to Basic?', ['n' => $list->count()]) : __('Restore :n user(s) to Licensed?', ['n' => $list->count()]) }}</flux:heading>
                @if ($list->count() < count($selected))
                    <flux:text class="mt-1 text-xs">{{ __(':n of the selected users are not eligible and will be left alone.', ['n' => count($selected) - $list->count()]) }}</flux:text>
                @endif
            </div>

            <ul class="max-h-48 overflow-y-auto rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                @forelse ($list as $m)
                    <li class="flex justify-between gap-2 py-0.5"><span>{{ $m->name ?: $m->email }}</span><span class="text-zinc-500">{{ $m->email }}</span></li>
                @empty
                    <li class="text-zinc-500">{{ __('Nothing to do.') }}</li>
                @endforelse
            </ul>

            <flux:callout icon="information-circle" variant="warning">
                <flux:callout.text>{{ __('This does not reduce your Zoom bill until you lower the seat count in Zoom Billing. Each user is re-checked in Zoom right before the change and skipped if a guardrail now applies.') }}</flux:callout.text>
            </flux:callout>

            @if ($this->exceedsCap())
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.heading>{{ __('Safety cap: :limit per run', ['limit' => $this->capLimit()]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('You are about to downgrade :n users, more than max(10, 10% of licensed seats). A second confirmation is required.', ['n' => $list->count()]) }}</flux:callout.text>
                </flux:callout>
                <flux:checkbox wire:model="acknowledgeCap" :label="__('I understand and want to exceed the safety cap.')" data-test="acknowledge-cap" />
                @error('acknowledgeCap') <flux:text class="text-sm text-red-600">{{ $message }}</flux:text> @enderror
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="run" :disabled="$list->isEmpty()" data-test="bulk-confirm-run">{{ $mode === 'downgrade' ? __('Downgrade') : __('Restore') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
