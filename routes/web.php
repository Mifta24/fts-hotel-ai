<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\HotelSettingsController;
use App\Http\Controllers\Admin\HotelSwitchController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConciergeChatController;
use App\Http\Controllers\GuestBookingController;
use App\Http\Controllers\HotelPageController;
use App\Http\Controllers\Member\AccountController as MemberAccountController;
use App\Http\Controllers\Member\BookingController as MemberBookingController;
use App\Http\Controllers\Member\LoginController as MemberLoginController;
use App\Http\Controllers\NarrationSpeechController;
use App\Http\Controllers\Platform\HotelController as PlatformHotelController;
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
        Route::post('switch-hotel', HotelSwitchController::class)->name('switch-hotel');

        Route::middleware('hotel.can:content')->group(function () {
            Route::resource('room-types', RoomTypeController::class)->except('show');
            Route::resource('knowledge-items', KnowledgeItemController::class)->except('show');
        });

        Route::middleware('hotel.can:inventory')->group(function () {
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::put('inventory', [InventoryController::class, 'update'])->name('inventory.update');
        });

        Route::middleware('hotel.can:bookings.view')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        });
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->middleware('hotel.can:bookings.manage')->name('bookings.status');

        Route::middleware('hotel.can:handovers')->group(function () {
            Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
            Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
            Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
            Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
        });

        Route::middleware('hotel.can:members')->group(function () {
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::get('members/{member}', [MemberController::class, 'show'])->whereNumber('member')->name('members.show');
        });

        Route::post('notes/{type}/{id}', [NoteController::class, 'store'])->whereIn('type', ['booking', 'handover', 'member'])->whereNumber('id')->name('notes.store');

        Route::middleware('hotel.can:settings')->group(function () {
            Route::get('settings', [HotelSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [HotelSettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('hotel.can:team')->group(function () {
            Route::get('team', [TeamController::class, 'index'])->name('team.index');
            Route::post('team', [TeamController::class, 'store'])->name('team.store');
            Route::put('team/{member}', [TeamController::class, 'update'])->name('team.update');
            Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
        });

        Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');

        Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('hotel.can:analytics')->name('analytics.index');
        Route::get('activity', [ActivityController::class, 'index'])->middleware('hotel.can:activity')->name('activity.index');
    });
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform.admin'])->group(function () {
    Route::get('hotels', [PlatformHotelController::class, 'index'])->name('hotels.index');
    Route::post('hotels', [PlatformHotelController::class, 'store'])->name('hotels.store');
    Route::patch('hotels/{hotel}/ai-model', [PlatformHotelController::class, 'updateAiModel'])->name('hotels.ai-model');
    Route::patch('hotels/{hotel}/status', [PlatformHotelController::class, 'updateStatus'])->name('hotels.status');
    Route::patch('hotels/{hotel}/features', [PlatformHotelController::class, 'updateFeature'])->name('hotels.features');
});

Route::post('narration/speech', NarrationSpeechController::class)->middleware('throttle:narration-speech')->name('narration.speech');

Route::prefix('{hotelSlug}')->where(['hotelSlug' => '(?!(?:up|admin|platform|narration)$)[^/]+'])->group(function () {
    Route::get('/', [HotelPageController::class, 'show'])->name('hotel.show');
    Route::get('rooms', [HotelPageController::class, 'rooms'])->name('hotel.rooms');
    Route::get('rooms/{roomSlug}', [HotelPageController::class, 'room'])->name('hotel.room');
    Route::get('facilities', [HotelPageController::class, 'facilities'])->name('hotel.facilities');
    Route::get('facilities/{facilityId}', [HotelPageController::class, 'facility'])->whereNumber('facilityId')->name('hotel.facility');
    Route::get('info', [HotelPageController::class, 'info'])->name('hotel.info');
    Route::get('staff', [HotelPageController::class, 'staff'])->name('hotel.staff');
    Route::get('reservation', [HotelPageController::class, 'reservationScene'])->name('hotel.reservation');

    Route::get('booking', [GuestBookingController::class, 'page'])->name('hotel.booking');
    Route::prefix('booking')->name('booking.')->middleware('throttle:booking-manage')->group(function () {
        Route::post('lookup', [GuestBookingController::class, 'lookup'])->name('lookup');
        Route::post('reschedule', [GuestBookingController::class, 'reschedule'])->name('reschedule');
        Route::post('cancel', [GuestBookingController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('member')->name('member.')->middleware('member.enabled')->group(function () {
        Route::get('login', [MemberLoginController::class, 'create'])->name('login');
        Route::post('login', [MemberLoginController::class, 'send'])->middleware('throttle:member-code')->name('login.send');
        Route::get('verify', [MemberLoginController::class, 'verifyForm'])->name('verify');
        Route::post('verify', [MemberLoginController::class, 'verify'])->middleware('throttle:member-verify')->name('verify.check');

        Route::middleware('member.auth')->group(function () {
            Route::get('/', [MemberAccountController::class, 'show'])->name('account');
            Route::put('profile', [MemberAccountController::class, 'updateProfile'])->name('profile');
            Route::post('logout', [MemberLoginController::class, 'destroy'])->name('logout');
            Route::post('bookings/{reference}/reschedule', [MemberBookingController::class, 'reschedule'])->name('bookings.reschedule');
            Route::post('bookings/{reference}/cancel', [MemberBookingController::class, 'cancel'])->name('bookings.cancel');
        });
    });

    Route::prefix('reservation')->name('reservation.')->middleware('throttle:20,1')->group(function () {
        Route::post('quote', [ReservationController::class, 'quote'])->name('quote');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
    });

    Route::prefix('concierge')->name('concierge.')->group(function () {
        Route::post('start', [ConciergeChatController::class, 'start'])->middleware('throttle:concierge-start')->name('start');
        Route::post('message', [ConciergeChatController::class, 'message'])->middleware('throttle:concierge-message')->name('message');
        Route::post('feedback', [ConciergeChatController::class, 'feedback'])->middleware('throttle:concierge-feedback')->name('feedback');
        Route::get('history', [ConciergeChatController::class, 'history'])->middleware('throttle:concierge-history')->name('history');
    });
});
