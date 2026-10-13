<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — {{ auth()->user()?->currentHotel()?->name ?? config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">
    <div class="flex min-h-screen">
        <aside class="w-56 shrink-0 border-r border-stone-200 bg-white">
            <div class="border-b border-stone-200 px-4 py-4">
                <p class="text-sm font-semibold text-stone-900">{{ auth()->user()?->currentHotel()?->name }}</p>
                <p class="text-xs text-stone-500">Admin Dashboard</p>
                @php $myHotels = auth()->user()?->hotels()->wherePivot('status', 'active')->get() ?? collect(); @endphp
                @if ($myHotels->count() > 1)
                    <form method="POST" action="{{ route('admin.switch-hotel') }}" class="mt-2">
                        @csrf
                        <select name="hotel_id" onchange="this.form.submit()" aria-label="Switch hotel" class="w-full rounded-lg border border-stone-300 bg-white px-2 py-1 text-xs">
                            @foreach ($myHotels as $option)<option value="{{ $option->id }}" @selected($option->id === auth()->user()->currentHotel()?->id)>{{ $option->name }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </div>
            <nav class="space-y-1 px-2 py-4 text-sm">
                @php
                    $currentHotel = auth()->user()?->currentHotel();
                    $badges = $currentHotel ? [
                        'admin.bookings.index' => $currentHotel->bookings()->where('status', 'pending')->count(),
                        'admin.handovers.index' => \App\Models\HandoverRequest::where('status', 'open')->whereHas('conversation', fn ($q) => $q->where('hotel_id', $currentHotel->id))->count(),
                    ] : [];
                    $navItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'ability' => null],
                        ['route' => 'admin.room-types.index', 'label' => 'Rooms', 'ability' => 'content'],
                        ['route' => 'admin.inventory.index', 'label' => 'Availability', 'ability' => 'inventory'],
                        ['route' => 'admin.knowledge-items.index', 'label' => 'Knowledge base', 'ability' => 'content'],
                        ['route' => 'admin.bookings.index', 'label' => 'Bookings', 'ability' => 'bookings.view'],
                        ['route' => 'admin.handovers.index', 'label' => 'Handovers', 'ability' => 'handovers'],
                        ['route' => 'admin.analytics.index', 'label' => 'Analytics', 'ability' => 'analytics'],
                        ['route' => 'admin.activity.index', 'label' => 'Activity', 'ability' => 'activity'],
                        ['route' => 'platform.hotels.index', 'label' => 'All hotels', 'ability' => 'platform'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    @continue($item['ability'] && ! ($item['ability'] === 'platform' ? auth()->user()?->is_platform_admin : auth()->user()?->canInHotel($item['ability'])))
                    <a
                        href="{{ route($item['route']) }}"
                        class="block rounded-lg px-3 py-2 {{ request()->routeIs($item['route'].'*') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}"
                    >{{ $item['label'] }}@if (($badges[$item['route']] ?? 0) > 0)<span class="ml-2 rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $badges[$item['route']] }}</span>@endif</a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-stone-200 p-2">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-stone-500 hover:bg-stone-100">Log out</button>
            </form>
        </aside>

        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-5xl px-6 py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-xl font-semibold text-stone-900">{{ $title ?? 'Dashboard' }}</h1>
                    {{ $actions ?? '' }}
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
