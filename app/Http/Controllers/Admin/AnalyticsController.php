<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Services\Concierge\ConciergeAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    use ResolvesCurrentHotel;

    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly ConciergeAnalytics $analytics) {}

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;

        return view('admin.analytics.index', [
            'hotel' => $hotel,
            'report' => $this->analytics->report($hotel, $days),
            'periods' => self::PERIODS,
        ]);
    }
}
