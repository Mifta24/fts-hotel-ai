@props(['subject', 'type'])

<div class="rounded-xl border border-stone-200 bg-white p-4">
    <p class="text-sm font-medium text-stone-900">Internal notes</p>
    <p class="text-xs text-stone-400">Only the hotel team sees these.</p>

    <form method="POST" action="{{ route('admin.notes.store', ['type' => $type, 'id' => $subject->id]) }}" class="mt-3">
        @csrf
        <textarea name="body" rows="2" required maxlength="2000" placeholder="e.g. Guest asked for a quiet room, called twice" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea>
        @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <button type="submit" class="mt-2 rounded-lg bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700">Add note</button>
    </form>

    <ul class="mt-4 space-y-3">
        @forelse ($subject->internalNotes as $note)
            <li class="rounded-lg bg-stone-50 px-3 py-2 text-sm">
                <p class="whitespace-pre-line text-stone-800">{{ $note->body }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $note->author?->name ?? 'Former team member' }} · {{ $note->created_at->diffForHumans() }}</p>
            </li>
        @empty
            <li class="text-sm text-stone-400">No notes yet.</li>
        @endforelse
    </ul>
</div>
