<?php

use App\Http\Controllers\SnippetController;
use Illuminate\Support\Facades\Route;

Route::resource('snippets', SnippetController::class);
Route::get('/snippets/{snippet}/fork', [SnippetController::class, 'create'])->name('snippets.fork');
