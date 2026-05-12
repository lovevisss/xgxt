<?php

use App\Http\Controllers\SnippetController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFamilyController;
use Illuminate\Support\Facades\Route;

Route::resource('snippets', SnippetController::class);
Route::get('/snippets/{snippet}/fork', [SnippetController::class, 'create'])->name('snippets.fork');

// 学生管理页面
Route::view('/students', 'students')->name('students.page');

// 学生管理API
Route::get('/students/data', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/filters', [StudentController::class, 'filters'])->name('students.filters');
Route::get('/students/profile/{xgh}', [StudentController::class, 'profile'])->name('students.profile');
Route::get('/students/data/{xgh}', [StudentController::class, 'show'])->name('students.show');
Route::put('/students/data/{xgh}', [StudentController::class, 'update'])->name('students.update');

// 学生家庭信息页面
Route::view('/student-families', 'student-families')->name('student-families.page');

// 学生家庭信息API
Route::get('/student-families/data', [StudentFamilyController::class, 'index'])->name('student-families.index');
Route::get('/student-families/data/{id}', [StudentFamilyController::class, 'show'])->name('student-families.show');
Route::put('/student-families/data/{id}', [StudentFamilyController::class, 'update'])->name('student-families.update');

// 首页路由，返回 welcome 页面
Route::get('/', function () {
    return view('welcome');
});
