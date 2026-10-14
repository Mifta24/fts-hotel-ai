<x-admin-layout title="Team">
    @if ($errors->any())
        <ul class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr><th class="px-4 py-3">Person</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Access</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($members as $member)
                    <tr>
                        <td class="px-4 py-3"><p class="font-medium text-stone-900">{{ $member->name }}@if ($member->id === auth()->id()) <span class="text-xs text-stone-400">(you)</span>@endif</p><p class="text-xs text-stone-500">{{ $member->email }}</p></td>
                        <td class="px-4 py-3">
                            <select name="role" form="member-{{ $member->id }}" class="rounded-lg border border-stone-300 px-2 py-1">
                                @foreach ($roles as $role)<option value="{{ $role }}" @selected($member->pivot->role === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>@endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <select name="status" form="member-{{ $member->id }}" class="rounded-lg border border-stone-300 px-2 py-1">
                                <option value="active" @selected($member->pivot->status === 'active')>Active</option>
                                <option value="suspended" @selected($member->pivot->status === 'suspended')>Suspended</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="submit" form="member-{{ $member->id }}" class="text-stone-700 hover:underline">Save</button>
                            <form method="POST" action="{{ route('admin.team.destroy', $member) }}" class="inline" onsubmit="return confirm('Remove this person from the team?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @foreach ($members as $member)
        <form method="POST" action="{{ route('admin.team.update', $member) }}" id="member-{{ $member->id }}" class="hidden">@csrf @method('PUT')</form>
    @endforeach

    <p class="mt-3 text-xs text-stone-500"><strong>Owner</strong> does everything, including settings and the team. <strong>Front desk</strong> handles availability, bookings, chats and analytics. <strong>Staff</strong> reads bookings and answers chats.</p>

    <form method="POST" action="{{ route('admin.team.store') }}" class="mt-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        @csrf
        <p class="font-medium text-stone-900">Add someone</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-4">
            <label class="block sm:col-span-2">Email<input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Name (for a new account)<input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            <label class="block">Role
                <select name="role" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
                    @foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', 'staff') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>@endforeach
                </select>
            </label>
        </div>
        <button type="submit" class="mt-4 rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Add to the team</button>
    </form>
</x-admin-layout>
