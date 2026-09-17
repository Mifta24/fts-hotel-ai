<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentHotel;

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $stats = [
            'room_types' => $hotel->roomTypes()->count(),
            'knowledge_items' => $hotel->knowledgeItems()->count(),
            'pending_bookings' => $hotel->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotel->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $recentBookings = $hotel->bookings()->latest()->take(5)->with('roomType')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotel->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'hotel' => $hotel,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'openHandovers' => $openHandovers,
        ]);
    }
}
