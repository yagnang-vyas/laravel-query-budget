<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Tests\Fixtures\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/posts-lazy', [PostController::class, 'lazy']);
Route::get('/posts-eager', [PostController::class, 'eager']);
Route::get('/posts-duplicates', [PostController::class, 'duplicates']);
Route::get('/quiet', [PostController::class, 'quiet']);

Route::get('/budgeted/lazy', [PostController::class, 'lazy'])->middleware('query.budget:3');
Route::get('/budgeted/eager', [PostController::class, 'eager'])->middleware('query.budget:3');
Route::get('/budgeted/duplicates', [PostController::class, 'duplicates'])->middleware('query.budget:10');
Route::get('/budgeted/quiet', [PostController::class, 'quiet'])->middleware('query.budget:0');
