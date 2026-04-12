<?php

use App\Http\Controllers\SnippetController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::resource('snippets', SnippetController::class);
Route::get('/snippets/{snippet}/fork', [SnippetController::class, 'create'])->name('snippets.fork');

// 学生管理页面
Route::view('/students', 'students')->name('students.page');

// 学生管理API
Route::get('/students/data', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/data/{xgh}', [StudentController::class, 'show'])->name('students.show');
Route::put('/students/data/{xgh}', [StudentController::class, 'update'])->name('students.update');

// 首页路由，返回 welcome 页面
Route::get('/', function () {
    return view('welcome');
});
