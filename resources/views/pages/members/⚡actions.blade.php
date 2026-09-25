<?php

use App\Models\ZoomMember;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Downgrade / restore buttons for one member. Wired to jobs in M5. */
new class extends Component {
    #[Locked]
    public ZoomMember $member;

    public function mount(ZoomMember $member): void
    {
        $this->member = $member;
    }
}; ?>

<div>
    @if ($member->isLicensed() && $member->eligible_for_downgrade)
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.text>{{ __('Eligible for downgrade to Basic. Actions arrive in the next step of the build.') }}</flux:callout.text>
        </flux:callout>
    @endif
</div>
