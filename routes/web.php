<?php

use App\Http\Controllers\CasAuthController;
use App\Http\Controllers\SnippetController;
use App\Http\Controllers\StudentAwardPunishmentImportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFamilyController;
use App\Http\Controllers\StudentLoanImportController;
use App\Http\Controllers\StudentSupportImportController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/login', [CasAuthController::class, 'login'])->name('cas.login');
Route::get('/sso/logout', [CasAuthController::class, 'logout'])->name('cas.logout');
Route::post('/sso/userOnlineDetect', [CasAuthController::class, 'userOnlineDetect'])->name('cas.userOnlineDetect');
Route::match(['GET', 'POST'], '/sso/slo', [CasAuthController::class, 'slo'])->name('cas.slo');

Route::middleware('cas.auth')->group(function (): void {
    Route::resource('snippets', SnippetController::class);
    Route::get('/snippets/{snippet}/fork', [SnippetController::class, 'create'])->name('snippets.fork');

    Route::view('/students', 'students')->name('students.page');
    Route::get('/students/data', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/filters', [StudentController::class, 'filters'])->name('students.filters');
    Route::get('/students/profile/{xgh}', [StudentController::class, 'profile'])->name('students.profile');
    Route::get('/students/data/{xgh}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/students/data/{xgh}', [StudentController::class, 'update'])->name('students.update');

    Route::view('/student-families', 'student-families')->name('student-families.page');
    Route::get('/student-families/data', [StudentFamilyController::class, 'index'])->name('student-families.index');
    Route::get('/student-families/data/{id}', [StudentFamilyController::class, 'show'])->name('student-families.show');
    Route::put('/student-families/data/{id}', [StudentFamilyController::class, 'update'])->name('student-families.update');

    Route::get('/student-award-punishment-import', [StudentAwardPunishmentImportController::class, 'page'])->name('student-award-punishment-import.page');
    Route::get('/student-award-punishment-import/template', [StudentAwardPunishmentImportController::class, 'template'])->name('student-award-punishment-import.template');
    Route::post('/student-award-punishment-import', [StudentAwardPunishmentImportController::class, 'import'])->name('student-award-punishment-import.import');

    Route::get('/student-loans/import', [StudentLoanImportController::class, 'page'])->name('student-loans.import.page');
    Route::get('/student-loans/import/template', [StudentLoanImportController::class, 'template'])->name('student-loans.import.template');
    Route::post('/student-loans/import', [StudentLoanImportController::class, 'import'])->name('student-loans.import');

    Route::get('/student-support/import', [StudentSupportImportController::class, 'page'])->name('student-support.import.page');
    Route::get('/student-support/import/template', [StudentSupportImportController::class, 'template'])->name('student-support.import.template');
    Route::post('/student-support/import', [StudentSupportImportController::class, 'import'])->name('student-support.import');
});

Route::get('/', function () {
    return view('welcome');
});
