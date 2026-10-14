<x-admin-layout title="Hotels on the platform">
    @if ($errors->any())
        <ul class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr><th class="px-4 py-3">Hotel</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Rooms</th><th class="px-4 py-3">Bookings</th><th class="px-4 py-3">AI model</th><th class="px-4 py-3">Add-ons</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($hotels as $hotel)
                    <tr>
                        <td class="px-4 py-3"><p class="font-medium text-stone-900">{{ $hotel->name }}</p><a href="{{ url($hotel->slug) }}" class="text-xs text-stone-400 hover:underline">/{{ $hotel->slug }}</a></td>
                        <td class="px-4 py-3 text-xs text-stone-600">{{ $hotel->users->filter(fn ($user) => $user->pivot->role === 'owner')->pluck('email')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $hotel->room_types_count }}</td>
                        <td class="px-4 py-3">{{ $hotel->bookings_count }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('platform.hotels.ai-model', $hotel) }}" class="flex gap-1">
                                @csrf @method('PATCH')
                                <input name="ai_model" value="{{ $hotel->setting('ai_model') }}" placeholder="default" aria-label="AI model of {{ $hotel->name }}" class="w-40 rounded-lg border border-stone-300 px-2 py-1 text-xs">
                                <button type="submit" class="text-xs text-stone-600 hover:underline">Save</button>
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            @foreach (['member' => 'Member', 'member_plus' => 'Plus'] as $feature => $featureLabel)
                                <form method="POST" action="{{ route('platform.hotels.features', $hotel) }}" class="flex items-center gap-2 {{ $loop->first ? '' : 'mt-1' }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="feature" value="{{ $feature }}">
                                    <input type="hidden" name="enabled" value="{{ $hotel->hasFeature($feature) ? 0 : 1 }}">
                                    <span class="w-14 text-xs text-stone-500">{{ $featureLabel }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $hotel->hasFeature($feature) ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">{{ $hotel->hasFeature($feature) ? 'On' : 'Off' }}</span>
                                    <button type="submit" class="text-xs text-stone-600 hover:underline" aria-label="{{ $hotel->hasFeature($feature) ? 'Switch '.$feature.' add-on off' : 'Switch '.$feature.' add-on on' }} for {{ $hotel->name }}">{{ $hotel->hasFeature($feature) ? 'Switch off' : 'Switch on' }}</button>
                                </form>
                            @endforeach
                        </td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $hotel->isPublished() ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">{{ $hotel->isPublished() ? 'Published' : 'Draft' }}</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('platform.hotels.status', $hotel) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="public_status" value="{{ $hotel->isPublished() ? 'draft' : 'published' }}">
                                <button type="submit" class="text-stone-600 hover:underline">{{ $hotel->isPublished() ? 'Unpublish' : 'Publish' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-stone-400">No hotels yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('platform.hotels.store') }}" class="mt-8 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        @csrf
        <p class="font-medium text-stone-900">Open a new hotel</p>
        <p class="text-xs text-stone-400">The hotel stays hidden from guests until it is published. The owner fills in rooms, knowledge and availability.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block">Hotel name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Web address (optional)<input name="slug" value="{{ old('slug') }}" placeholder="made from the name" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">City<input name="city" value="{{ old('city') }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Country<input name="country" value="{{ old('country') }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Currency<input name="currency" value="{{ old('currency', 'IDR') }}" maxlength="3" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 uppercase"></label>
            <label class="block">Main language
                <select name="default_locale" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
                    @foreach (['id' => 'Indonesian', 'en' => 'English', 'ja' => 'Japanese'] as $code => $name)<option value="{{ $code }}" @selected(old('default_locale', 'id') === $code)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <label class="block">Owner email<input type="email" name="owner_email" value="{{ old('owner_email') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Owner name (for a new account)<input name="owner_name" value="{{ old('owner_name') }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
        </div>
        <button type="submit" class="mt-4 rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Create hotel</button>
    </form>
</x-admin-layout>
