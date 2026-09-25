<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php($currentOrganization = app(App\Tenancy\Tenancy::class)->current())
            @if ($currentOrganization)
                <flux:dropdown position="bottom" align="start" class="px-2 pb-2">
                    <flux:button variant="subtle" size="sm" icon:trailing="chevrons-up-down" class="w-full justify-between" data-test="organization-switcher">
                        <span class="truncate">{{ $currentOrganization->name }}</span>
                    </flux:button>
                    <flux:menu>
                        <flux:menu.radio.group>
                            @foreach (auth()->user()->organizations()->orderBy('name')->get() as $organization)
                                <form method="POST" action="{{ route('organizations.switch', $organization) }}">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" :icon="$organization->is($currentOrganization) ? 'check' : null" class="w-full cursor-pointer">{{ $organization->name }}</flux:menu.item>
                                </form>
                            @endforeach
                        </flux:menu.radio.group>
                        <flux:menu.separator />
                        <flux:menu.item :href="route('organizations.create')" icon="plus" wire:navigate>{{ __('New organization') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('members')" :current="request()->routeIs('members')" wire:navigate>
                        {{ __('Members') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bolt" :href="route('automation')" :current="request()->routeIs('automation')" wire:navigate>
                        {{ __('Automation') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shield-check" :href="route('exclusions')" :current="request()->routeIs('exclusions')" wire:navigate>
                        {{ __('Exclusions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('audit')" :current="request()->routeIs('audit')" wire:navigate>
                        {{ __('Audit log') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="link" :href="route('connection.edit')" :current="request()->routeIs('connection.edit')" wire:navigate>
                        {{ __('Connection') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('billing')" :current="request()->routeIs('billing')" wire:navigate>
                        {{ __('Billing') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('organization.edit')" :current="request()->routeIs('organization.edit')" wire:navigate>
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="book-open-text" href="{{ url('/docs') }}" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @if ($currentOrganization && App\Billing\PlanResolver::overLimit($currentOrganization))
            <div class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" data-test="over-limit-banner">
                {{ App\Billing\PlanResolver::graceExpired($currentOrganization)
                    ? __('Your Zoom account has more licensed seats than your plan covers and the grace period has ended: paid features are paused.')
                    : __('Your Zoom account has outgrown your plan. Upgrade before :date to keep paid features.', ['date' => App\Billing\PlanResolver::graceEndsAt($currentOrganization)?->toFormattedDateString()]) }}
                <a href="{{ route('billing') }}" class="underline" wire:navigate>{{ __('See plans') }}</a>
            </div>
        @endif

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
