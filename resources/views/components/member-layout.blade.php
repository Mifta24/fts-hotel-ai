@props(['hotel', 'locale', 'labels', 'title'])
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $hotel->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-md px-4 py-10">
        <div class="flex items-center justify-between text-sm text-stone-500">
            <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" class="hover:underline">← {{ $labels['back'] }}</a>
            <nav class="flex gap-2" aria-label="Language">
                @foreach (\App\Core\Member\Support\MemberLabels::SUPPORTED_LOCALES as $code)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" @class(['uppercase', 'font-semibold text-stone-900' => $code === $locale, 'hover:underline' => $code !== $locale]) @if ($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                @endforeach
            </nav>
        </div>

        <h1 class="mt-4 text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-1 text-sm text-stone-500">{{ $hotel->name }}</p>

        @if (session('status'))
            <p role="status" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="mt-4 space-y-1 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
