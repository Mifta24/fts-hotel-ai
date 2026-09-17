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
            </div>
            <nav class="space-y-1 px-2 py-4 text-sm">
                @php
                    $navItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
                        ['route' => 'admin.room-types.index', 'label' => 'Rooms'],
                        ['route' => 'admin.knowledge-items.index', 'label' => 'Knowledge base'],
                        ['route' => 'admin.bookings.index', 'label' => 'Bookings'],
                        ['route' => 'admin.handovers.index', 'label' => 'Handovers'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        class="block rounded-lg px-3 py-2 {{ request()->routeIs($item['route'].'*') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}"
                    >{{ $item['label'] }}</a>
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
