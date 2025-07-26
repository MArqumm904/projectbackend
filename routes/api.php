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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// ============================ SIGNUP API ================================
Route::post('/signup', [App\Http\Controllers\UserController::class, 'signup']);


// ============================ LOGIN API ================================
Route::post('/login', [App\Http\Controllers\UserController::class, 'login']);


// ============================ TEST API ============================
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// ============================ ABOUT API ================================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/about/education', [App\Http\Controllers\AboutController::class, 'createEducation']);
    Route::post('/about/certification', [App\Http\Controllers\AboutController::class, 'createCertification']);
    Route::post('/about/userinfo', [App\Http\Controllers\AboutController::class, 'createUserInfo']);
    Route::post('/about/overview', [App\Http\Controllers\AboutController::class, 'createUserOverview']);
    Route::post('/about/skill', [App\Http\Controllers\AboutController::class, 'createUserSkill']);

    // ============================ ABOUT GET API'S ================================
    Route::get('/about/overview/{id}', [App\Http\Controllers\AboutController::class, 'getUserOverview']);
    Route::put('/about/overview/{id}', [App\Http\Controllers\AboutController::class, 'updateUserOverview']);
});


// ============================ CHECK AUTH THAT USER IS LOGGED IN API ================================
Route::middleware('auth:sanctum')->get('/check-auth', [App\Http\Controllers\UserController::class, 'checkAuth']);

// ============================ GET USER PROFILE DATA API ================================
Route::get('/user/profile/{user_id}', [App\Http\Controllers\UserProfileController::class, 'getProfile']);

// ============================ LOGOUT API ================================
Route::middleware('auth:sanctum')->post('/logout', [App\Http\Controllers\UserController::class, 'logout']);

