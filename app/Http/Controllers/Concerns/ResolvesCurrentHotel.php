<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCurrentHotel
{
    protected function currentHotel(Request $request): Hotel
    {
        $hotel = $request->user()->currentHotel();

        if (! $hotel) {
            throw new HttpException(403, 'No hotel is linked to this account yet.');
        }

        return $hotel;
    }
}
