<x-member-layout :hotel="$hotel" :locale="$locale" :labels="$labels" :title="$labels['verify_title']">
    <p class="mt-4 text-sm text-stone-600">{{ \App\Core\Member\Support\MemberLabels::fill($labels['verify_intro'], ['email' => $email, 'minutes' => \App\Core\Member\Services\LoginCodeService::TTL_MINUTES]) }}</p>

    <form method="POST" action="{{ route('member.verify.check', ['hotelSlug' => $hotel->slug]) }}" class="mt-6 space-y-3">
        @csrf
        <input type="hidden" name="lang" value="{{ $locale }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <label class="block text-sm font-medium">{{ $labels['code'] }}
            <input type="text" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-center font-mono text-2xl tracking-[0.4em]">
        </label>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" value="1" checked class="rounded border-stone-300"> {{ $labels['keep_signed_in'] }}
        </label>
        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white hover:bg-stone-700">{{ $labels['verify'] }}</button>
    </form>

    <div class="mt-4 flex items-center justify-between text-sm">
        <form method="POST" action="{{ route('member.login.send', ['hotelSlug' => $hotel->slug]) }}">
            @csrf
            <input type="hidden" name="lang" value="{{ $locale }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="text-stone-600 underline hover:text-stone-900">{{ $labels['resend'] }}</button>
        </form>
        <a href="{{ route('member.login', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" class="text-stone-600 underline hover:text-stone-900">{{ $labels['change_email'] }}</a>
    </div>
</x-member-layout>
