<?php

use Livewire\Attributes\Reactive;
use Livewire\Component;

/** Bulk-selection toolbar. Actions are wired in M5. */
new class extends Component {
    /** @var array<int, int> */
    #[Reactive]
    public array $selected = [];
}; ?>

<div>
    @if (count($selected) > 0)
        <flux:callout icon="check-circle" variant="secondary">
            <flux:callout.text>{{ trans_choice(':n member selected|:n members selected', count($selected), ['n' => count($selected)]) }}</flux:callout.text>
        </flux:callout>
    @endif
</div>
