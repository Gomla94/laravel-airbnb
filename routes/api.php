<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImagesController;
use App\Http\Controllers\TagController;
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

Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);
Route::post('/offices/{office}/images', [OfficeImagesController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}/images/{image}', [OfficeImagesController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);