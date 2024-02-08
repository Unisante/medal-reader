<?php

use App\Http\Controllers\AlgorithmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Auth::routes(['register' => true, 'verify' => true]);

Route::get('/', [AlgorithmController::class, 'index'])->name('home.index');
Route::get('/justarandomurlnothingtosee', [AlgorithmController::class, 'hidden'])->name('home.hidden');
Route::post('/', [AlgorithmController::class, 'store'])->name('home.store');
Route::get('/algorithm/{id}/{patient_id?}', [AlgorithmController::class, 'process'])->name('home.process');
