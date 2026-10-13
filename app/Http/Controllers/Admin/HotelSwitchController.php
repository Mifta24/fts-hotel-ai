<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HotelSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['hotel_id' => ['required', 'integer']]);

        $belongs = $request->user()->hotels()->wherePivot('status', 'active')->where('hotels.id', $data['hotel_id'])->exists();
        abort_unless($belongs, 403);

        $request->session()->put('current_hotel_id', (int) $data['hotel_id']);

        return redirect()->route('admin.dashboard');
    }
}
