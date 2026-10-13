<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    use ResolvesCurrentHotel;

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $logs = AuditLog::where('hotel_id', $hotel->id)
            ->with('user')
            ->latest('id')
            ->paginate(40);

        return view('admin.activity.index', compact('hotel', 'logs'));
    }
}
