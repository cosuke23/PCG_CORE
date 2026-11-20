<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Android app (1.0.3+): notifications without a session, by account id and app token
// (App\Support\MemberNotifications::appToken). 'api' has no session or cookies.
Route::get('/app/notifications', [\App\Http\Controllers\NotificationController::class, 'poll'])
    ->middleware('throttle:30,1')->name('api.app.notifications');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
