<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $labels['title'] }} · {{ $hotel->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-md px-4 py-10" id="manage" data-base="{{ url($hotel->slug.'/booking') }}" data-locale="{{ $locale }}" data-confirm-cancel="{{ $labels['confirm_cancel'] }}" data-error="{{ $labels['error'] }}">
        <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" class="text-sm text-stone-500 hover:underline">← {{ $labels['back'] }}</a>
        <h1 class="mt-4 text-2xl font-semibold">{{ $labels['title'] }}</h1>
        <p class="mt-1 text-sm text-stone-500">{{ $hotel->name }}</p>
        <p class="mt-4 text-sm text-stone-600">{{ $labels['intro'] }}</p>

        <form id="find-form" class="mt-6 space-y-3">
            <label class="block text-sm font-medium">{{ $labels['reference'] }}
                <input name="reference" required maxlength="20" autocomplete="off" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono uppercase" placeholder="BK-XXXXXX">
            </label>
            <label class="block text-sm font-medium">{{ $labels['contact'] }}
                <input name="contact" required maxlength="120" autocomplete="off" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
            </label>
            <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white hover:bg-stone-700">{{ $labels['find'] }}</button>
        </form>

        <p id="message" role="status" class="mt-4 hidden rounded-lg px-3 py-2 text-sm"></p>

        <section id="booking" class="mt-6 hidden rounded-xl border border-stone-200 bg-white p-4 text-sm">
            <p class="font-mono text-xs text-stone-500" data-f="reference"></p>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt class="text-stone-500">{{ $labels['room'] }}</dt><dd data-f="room"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">{{ $labels['stay'] }}</dt><dd data-f="stay"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">{{ $labels['total'] }}</dt><dd data-f="total"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">{{ $labels['status'] }}</dt><dd data-f="status"></dd></div>
            </dl>

            <div id="actions" class="mt-5 border-t border-stone-100 pt-4">
                <p class="font-medium">{{ $labels['new_dates'] }}</p>
                <form id="move-form" class="mt-2 grid grid-cols-2 gap-3">
                    <label class="block">{{ $labels['check_in'] }}<input type="date" name="check_in" required min="{{ now($hotel->timezone)->toDateString() }}" class="mt-1 w-full rounded-lg border border-stone-300 px-2 py-2"></label>
                    <label class="block">{{ $labels['check_out'] }}<input type="date" name="check_out" required class="mt-1 w-full rounded-lg border border-stone-300 px-2 py-2"></label>
                    <button type="submit" class="col-span-2 rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">{{ $labels['reschedule'] }}</button>
                </form>
                <button type="button" id="cancel-button" class="mt-4 w-full rounded-lg border border-red-300 px-4 py-2 font-medium text-red-700 hover:bg-red-50">{{ $labels['cancel'] }}</button>
            </div>
        </section>
    </main>

    <script>
        (() => {
            const root = document.getElementById('manage');
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const status = { pending: @json($labels['pending']), confirmed: @json($labels['confirmed']), cancelled: @json($labels['cancelled']) };
            const nightsWord = @json($labels['nights']);
            const message = document.getElementById('message');
            const card = document.getElementById('booking');
            const findForm = document.getElementById('find-form');
            const moveForm = document.getElementById('move-form');
            let credentials = null;

            const show = (text, ok) => {
                message.textContent = text;
                message.className = 'mt-4 rounded-lg px-3 py-2 text-sm ' + (ok ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
            };

            const post = async (path, body) => {
                const response = await fetch(root.dataset.base + '/' + path, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ ...credentials, ...body, locale: root.dataset.locale }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || root.dataset.error);
                return data;
            };

            const render = (booking) => {
                const set = (name, value) => { card.querySelector('[data-f="' + name + '"]').textContent = value; };
                set('reference', booking.reference);
                set('room', booking.rooms + ' × ' + booking.room);
                set('stay', booking.check_in + ' → ' + booking.check_out + ' (' + booking.nights + ' ' + nightsWord + ')');
                set('total', booking.currency + ' ' + new Intl.NumberFormat('id-ID').format(booking.total));
                set('status', status[booking.status] || booking.status);
                document.getElementById('actions').classList.toggle('hidden', !booking.changeable);
                card.classList.remove('hidden');
            };

            findForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const data = new FormData(findForm);
                credentials = { reference: data.get('reference'), contact: data.get('contact') };
                message.className = 'hidden';
                card.classList.add('hidden');
                try { render(await post('lookup', {})); } catch (error) { show(error.message, false); }
            });

            moveForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const data = new FormData(moveForm);
                try {
                    const booking = await post('reschedule', { check_in: data.get('check_in'), check_out: data.get('check_out') });
                    render(booking);
                    show(booking.message, true);
                } catch (error) { show(error.message, false); }
            });

            document.getElementById('cancel-button').addEventListener('click', async () => {
                if (!window.confirm(root.dataset.confirmCancel)) return;
                try {
                    const booking = await post('cancel', {});
                    render(booking);
                    show(booking.message, true);
                } catch (error) { show(error.message, false); }
            });
        })();
    </script>
</body>
</html>
