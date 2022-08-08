<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//
use App\Http\Controllers\UsersController;
use App\Http\Controllers\CookieAuthenticationController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
// Route::group(['middleware' => ['auth:sanctum']], function () {
//     Route::get('/me', MeController::class);
// });

// Route::group(['middleware' => ['auth:sanctum']], function () {
//     Route::post('/test', [CookieAuthenticationController::class, 'test']);
// });

