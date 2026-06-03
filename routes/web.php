<?php

use App\Http\Controllers\CasAuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\SnippetController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDataImportController;
use App\Http\Controllers\StudentFamilyController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/login', [CasAuthController::class, 'login'])->name('cas.login');
Route::get('/sso/logout', [CasAuthController::class, 'logout'])->name('cas.logout');
Route::post('/sso/userOnlineDetect', [CasAuthController::class, 'userOnlineDetect'])->name('cas.userOnlineDetect');
Route::match(['GET', 'POST'], '/sso/slo', [CasAuthController::class, 'slo'])->name('cas.slo');

Route::middleware(['cas.auth', 'admin.auth'])->group(function (): void {
    Route::resource('snippets', SnippetController::class);
    Route::get('/snippets/{snippet}/fork', [SnippetController::class, 'create'])->name('snippets.fork');

    Route::view('/students', 'students')->name('students.page');
    Route::get('/students/data', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/filters', [StudentController::class, 'filters'])->name('students.filters');
    Route::get('/students/dormitories/{ssh}', [StudentController::class, 'dormitory'])->name('students.dormitory');
    Route::get('/students/profile/{xgh}', [StudentController::class, 'profile'])->name('students.profile');
    Route::get('/students/data/{xgh}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/students/data/{xgh}', [StudentController::class, 'update'])->name('students.update');

    Route::view('/student-families', 'student-families')->name('student-families.page');
    Route::get('/student-families/data', [StudentFamilyController::class, 'index'])->name('student-families.index');
    Route::get('/student-families/data/{id}', [StudentFamilyController::class, 'show'])->name('student-families.show');
    Route::put('/student-families/data/{id}', [StudentFamilyController::class, 'update'])->name('student-families.update');

    Route::get('/student-imports', [StudentDataImportController::class, 'page'])->name('student-imports.page');
    Route::get('/student-imports/status/{task}', [StudentDataImportController::class, 'status'])->name('student-imports.status');
    Route::get('/student-imports/template/{type}', [StudentDataImportController::class, 'template'])->name('student-imports.template');
    Route::post('/student-imports/{type}', [StudentDataImportController::class, 'import'])->name('student-imports.import');

    Route::get('/student-award-punishment-import', [StudentDataImportController::class, 'redirectPage'])->name('student-award-punishment-import.page');
    Route::get('/student-award-punishment-import/template', [StudentDataImportController::class, 'template'])->defaults('type', 'award_punishment')->name('student-award-punishment-import.template');
    Route::post('/student-award-punishment-import', [StudentDataImportController::class, 'import'])->defaults('type', 'award_punishment')->name('student-award-punishment-import.import');
    Route::get('/student-loans/import', [StudentDataImportController::class, 'redirectPage'])->name('student-loans.import.page');
    Route::get('/student-loans/import/template', [StudentDataImportController::class, 'template'])->defaults('type', 'loan')->name('student-loans.import.template');
    Route::post('/student-loans/import', [StudentDataImportController::class, 'import'])->defaults('type', 'loan')->name('student-loans.import');
    Route::get('/student-support/import', [StudentDataImportController::class, 'redirectPage'])->name('student-support.import.page');
    Route::get('/student-support/import/template', [StudentDataImportController::class, 'template'])->defaults('type', 'support')->name('student-support.import.template');
    Route::post('/student-support/import', [StudentDataImportController::class, 'import'])->defaults('type', 'support')->name('student-support.import');

    Route::middleware('super-admin.auth')->group(function (): void {
        Route::get('/admin/users', [AdminUserController::class, 'page'])->name('admin.users.page');
        Route::get('/admin/users/data', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::put('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.update-role');
    });
});

Route::get('/', function () {
    return view('welcome');
});
