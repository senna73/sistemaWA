<?php

use App\Http\Controllers\Admin\Finance\ProcessorController;
use App\Http\Controllers\finance\admin\LeaderCostCenterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/api/leader-center/stats', [LeaderCostCenterController::class, 'stats'])->middleware('auth');

