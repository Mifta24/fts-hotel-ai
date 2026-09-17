<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hotel AI Concierge</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-4xl px-6 py-20">
        <p class="text-xs tracking-widest text-amber-700">FTS · AI CONCIERGE</p>
        <h1 class="mt-4 text-4xl font-semibold">Selamat datang.</h1>
        <p class="mt-3 text-stone-500">Pilih hotel untuk bertemu resepsionis virtual Anda.</p>
        <div class="mt-10 grid gap-4 sm:grid-cols-2">
            @forelse($hotels as $hotel)
                <a href="{{ route('hotel.show', $hotel->slug) }}" class="rounded-2xl border border-stone-200 bg-white p-6 transition hover:border-amber-600">
                    <h2 class="text-xl font-semibold">{{ $hotel->name }} <span aria-hidden="true">↗</span></h2>
                    <p class="mt-2 text-sm text-stone-500">{{ $hotel->city }}, {{ $hotel->country }}</p>
                </a>
            @empty
                <p class="text-stone-500">Lobi virtual sedang dipersiapkan. Silakan kembali lagi nanti.</p>
            @endforelse
        </div>
    </main>
</body>
</html>
