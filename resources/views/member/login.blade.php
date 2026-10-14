<x-member-layout :hotel="$hotel" :locale="$locale" :labels="$labels" :title="$labels['login_title']">
    <p class="mt-4 text-sm text-stone-600">{{ $labels['login_intro'] }}</p>

    <form method="POST" action="{{ route('member.login.send', ['hotelSlug' => $hotel->slug]) }}" class="mt-6 space-y-3">
        @csrf
        <input type="hidden" name="lang" value="{{ $locale }}">
        <label class="block text-sm font-medium">{{ $labels['email'] }}
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="120" autocomplete="email" autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white hover:bg-stone-700">{{ $labels['send_code'] }}</button>
    </form>
</x-member-layout>
