<?php

use App\Http\Controllers\MockInsurerController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests/{uuid}', [PolicyRequestController::class, 'show']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::post('/policy-requests/{uuid}/submit', [PolicyRequestController::class, 'submit']);

Route::post('/mock-insurer/callback', [MockInsurerController::class, 'callback']);
Route::post('/mock-insurer/policies', [MockInsurerController::class, 'issue']);
Route::get('/mock-insurer/policies/{key}', [MockInsurerController::class, 'status']);
