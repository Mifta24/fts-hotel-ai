<x-admin-layout title="Points & tiers">
    @if ($errors->any())
        <ul class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.loyalty.update') }}" class="max-w-2xl space-y-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        @csrf @method('PUT')

        <label class="block font-medium">Spend per point ({{ $hotel->currency }})
            <input type="number" step="any" min="0.01" name="spend_per_point" value="{{ old('spend_per_point', $program->spendPerPoint + 0) }}" required class="mt-1 w-48 rounded-lg border border-stone-300 px-3 py-2 font-normal">
            <span class="mt-1 block text-xs font-normal text-stone-400">A member earns 1 point for every amount like this spent on a stay. Points arrive after check-out.</span>
        </label>

        <div>
            <p class="font-medium">Tiers</p>
            <p class="text-xs text-stone-400">A member's tier is the highest one their points reach. Its discount applies to every new booking. Leave a row empty to skip it.</p>
            <table class="mt-3 w-full text-left">
                <thead class="text-xs uppercase text-stone-500"><tr><th class="py-2 pr-2">Name</th><th class="py-2 pr-2">From points</th><th class="py-2">Discount %</th></tr></thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $tier = $program->tiers[$i] ?? null; @endphp
                        <tr>
                            <td class="py-1 pr-2"><input name="tiers[{{ $i }}][name]" value="{{ old("tiers.$i.name", $tier?->name) }}" maxlength="40" aria-label="Tier {{ $i + 1 }} name" class="w-full rounded-lg border border-stone-300 px-3 py-2"></td>
                            <td class="py-1 pr-2"><input type="number" min="0" name="tiers[{{ $i }}][min_points]" value="{{ old("tiers.$i.min_points", $tier?->minPoints) }}" aria-label="Tier {{ $i + 1 }} points" class="w-full rounded-lg border border-stone-300 px-3 py-2"></td>
                            <td class="py-1"><input type="number" step="0.01" min="0" max="100" name="tiers[{{ $i }}][discount_percent]" value="{{ old("tiers.$i.discount_percent", $tier ? $tier->discountPercent + 0 : null) }}" aria-label="Tier {{ $i + 1 }} discount" class="w-full rounded-lg border border-stone-300 px-3 py-2"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Save</button>
    </form>
</x-admin-layout>
