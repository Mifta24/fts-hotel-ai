<x-admin-layout title="My account">
    <p class="mb-4 text-sm text-stone-600">{{ auth()->user()->name }} · {{ auth()->user()->email }} · role: {{ ucwords(str_replace('_', ' ', (string) auth()->user()->currentRole())) ?: '—' }}</p>

    <form method="POST" action="{{ route('admin.account.update') }}" class="max-w-md space-y-3 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        @csrf @method('PUT')
        <p class="font-medium text-stone-900">Change password</p>
        @foreach (['current_password' => 'Current password', 'password' => 'New password (at least 10 characters)', 'password_confirmation' => 'Repeat the new password'] as $name => $label)
            <label class="block">{{ $label }}<input type="password" name="{{ $name }}" required autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            @error($name)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @endforeach
        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Change password</button>
    </form>
</x-admin-layout>
