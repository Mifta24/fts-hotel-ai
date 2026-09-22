<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConciergeChatController;
use App\Http\Controllers\HotelPageController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HotelPageController::class, 'index'])->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('room-types', RoomTypeController::class)->except('show');
        Route::resource('knowledge-items', KnowledgeItemController::class)->except('show');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');

        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
        Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
        Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
    });
});

Route::prefix('{hotelSlug}')->group(function () {
    Route::get('/', [HotelPageController::class, 'show'])->name('hotel.show');
    Route::get('rooms', [HotelPageController::class, 'rooms'])->name('hotel.rooms');
    Route::get('rooms/{roomSlug}', [HotelPageController::class, 'room'])->name('hotel.room');
    Route::get('facilities', [HotelPageController::class, 'facilities'])->name('hotel.facilities');
    Route::get('facilities/{facilityId}', [HotelPageController::class, 'facility'])->whereNumber('facilityId')->name('hotel.facility');
    Route::get('info', [HotelPageController::class, 'info'])->name('hotel.info');
    Route::get('staff', [HotelPageController::class, 'staff'])->name('hotel.staff');

    Route::prefix('reservation')->name('reservation.')->middleware('throttle:20,1')->group(function () {
        Route::post('quote', [ReservationController::class, 'quote'])->name('quote');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
    });

    Route::prefix('concierge')->name('concierge.')->group(function () {
        Route::post('start', [ConciergeChatController::class, 'start'])->middleware('throttle:concierge-start')->name('start');
        Route::post('message', [ConciergeChatController::class, 'message'])->middleware('throttle:concierge-message')->name('message');
        Route::get('history', [ConciergeChatController::class, 'history'])->middleware('throttle:concierge-history')->name('history');
    });
});
