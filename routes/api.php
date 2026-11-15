<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassGroupController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupProgramController;
use App\Http\Controllers\Api\V1\GroupUserController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ProvinceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserGroupController;
use App\Http\Controllers\Api\V1\WorshipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/setup/admin', [AuthController::class, 'setupAdmin'])
        ->middleware('throttle:5,1')
        ->name('setup.admin');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('/auth/me', [AuthController::class, 'updateMe'])->name('auth.update-me');
        Route::patch('/auth/password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:5,1')
            ->name('auth.change-password');

        Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show']);
        Route::apiResource('locations', LocationController::class)->only(['index', 'show', 'store']);
        Route::apiResource('class-groups', ClassGroupController::class)->only(['index', 'show']);
        Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);
        Route::apiResource('worships', WorshipController::class);
        Route::patch('worships/{worship}/finish', [WorshipController::class, 'finish'])->name('worships.finish');

        Route::prefix('worships/{worship}/attendances')->name('worships.attendances.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            Route::delete('{person}', [AttendanceController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('exports')->name('exports.')->middleware('throttle:20,1')->group(function () {
            Route::get('worships', [ExportController::class, 'worships'])->name('worships');
            Route::get('worships/{worship}/attendances', [ExportController::class, 'attendances'])->name('attendances');
            Route::get('people', [ExportController::class, 'people'])->name('people');
        });

        Route::get('people/lookup', [PersonController::class, 'lookup'])->name('people.lookup');
        Route::apiResource('people', PersonController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['people' => 'person']);

        Route::middleware('role:admin')->group(function () {
            Route::prefix('dashboard')->name('dashboard.')->group(function () {
                Route::get('overview', [DashboardController::class, 'overview'])->name('overview');
                Route::get('programs/{program}', [DashboardController::class, 'program'])->name('program');
                Route::get('worships/{worship}', [DashboardController::class, 'worship'])->name('worship');
            });

            Route::apiResource('provinces', ProvinceController::class)->except(['index', 'show']);
            Route::apiResource('locations', LocationController::class)->only(['update', 'destroy']);
            Route::apiResource('class-groups', ClassGroupController::class)->except(['index', 'show']);
            Route::delete('people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');
            Route::apiResource('programs', ProgramController::class)->except(['index', 'show']);
            Route::apiResource('groups', GroupController::class);

            Route::prefix('groups/{group}')->name('groups.')->group(function () {
                Route::put('users', [GroupUserController::class, 'sync'])->name('users.sync');
                Route::post('users', [GroupUserController::class, 'attach'])->name('users.attach');
                Route::delete('users/{user}', [GroupUserController::class, 'detach'])->name('users.detach');

                Route::put('programs', [GroupProgramController::class, 'sync'])->name('programs.sync');
                Route::post('programs', [GroupProgramController::class, 'attach'])->name('programs.attach');
                Route::delete('programs/{program}', [GroupProgramController::class, 'detach'])->name('programs.detach');
            });

            Route::apiResource('users', UserController::class);
            Route::put('users/{user}/groups', [UserGroupController::class, 'sync'])->name('users.groups.sync');
            Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                ->name('users.reset-password');
            Route::patch('users/{user}/test-password', [UserController::class, 'generateTestPassword'])
                ->middleware('throttle:10,1')
                ->name('users.test-password');
        });
    });
});
