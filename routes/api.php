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
    Route::post('/about/info', [App\Http\Controllers\AboutController::class, 'createUserInfo']);
    Route::post('/about/overview', [App\Http\Controllers\AboutController::class, 'createUserOverview']);
    Route::post('/about/skills', [App\Http\Controllers\AboutController::class, 'createUserSkill']);

    // ============================ ABOUT GET API'S ================================
    Route::get('/about/overview/{id}', [App\Http\Controllers\AboutController::class, 'getUserOverview']);
    Route::get('/about/education/{id}', [App\Http\Controllers\AboutController::class, 'getUserEducation']);
    Route::get('/about/certification/{id}', [App\Http\Controllers\AboutController::class, 'getUserCertification']);
    Route::get('/about/skills/{id}', [App\Http\Controllers\AboutController::class, 'getUserSkill']);
    Route::get('/about/info/{id}', [App\Http\Controllers\AboutController::class, 'getUserInfo']);
    // ============================ ABOUT UPDATE API'S ================================
    Route::put('/about/overview/{id}', [App\Http\Controllers\AboutController::class, 'updateUserOverview']);
    Route::put('/about/education/{educationId}', [App\Http\Controllers\AboutController::class, 'updateUserEducation']);
    Route::put('/about/certification/{certificationId}', [App\Http\Controllers\AboutController::class, 'updateUserCertification']);
    Route::put('/about/skills/{skillId}', [App\Http\Controllers\AboutController::class, 'updateUserSkill']);
    Route::put('/about/info/{infoId}', [App\Http\Controllers\AboutController::class, 'updateUserInfo']);
    // ============================ ABOUT DELETE API'S ================================
    Route::delete('/about/overview/{id}', [App\Http\Controllers\AboutController::class, 'deleteUserOverview']);
    Route::delete('/about/education/{educationId}', [App\Http\Controllers\AboutController::class, 'deleteUserEducation']);
    Route::delete('/about/certification/{certificationId}', [App\Http\Controllers\AboutController::class, 'deleteUserCertification']);
    Route::delete('/about/skills/{skillId}', [App\Http\Controllers\AboutController::class, 'deleteUserSkill']);
    Route::delete('/about/info/{infoId}', [App\Http\Controllers\AboutController::class, 'deleteUserInfo']);
});


// ============================ CHECK AUTH THAT USER IS LOGGED IN API ================================
Route::middleware('auth:sanctum')->get('/check-auth', [App\Http\Controllers\UserController::class, 'checkAuth']);

// ============================ GET USER PROFILE DATA API ================================
Route::get('/user/profile/{user_id}', [App\Http\Controllers\UserProfileController::class, 'getProfile']);
Route::post('/user/profile/{user_id}', [App\Http\Controllers\UserProfileController::class, 'updateProfile']);    

// ============================ LOGOUT API ================================
Route::middleware('auth:sanctum')->post('/logout', [App\Http\Controllers\UserController::class, 'logout']);

// ============================ GROUPS API ================================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/groups', [App\Http\Controllers\GroupController::class, 'store']);
    Route::post('/groups/{groupid}', [App\Http\Controllers\GroupController::class, 'update']);
    // =================================== GET ONE GROUP ===================================
    Route::get('/groups/{groupid}', [App\Http\Controllers\GroupController::class, 'show']);
    // =================================== GET ALL GROUP ===================================
    Route::get('/groups', [App\Http\Controllers\GroupController::class, 'showall']);
    Route::delete('/groups/{id}', [App\Http\Controllers\GroupController::class, 'destroy']);
});

