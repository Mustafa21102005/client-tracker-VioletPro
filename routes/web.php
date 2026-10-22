<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('edits', [ChangeRequestController::class, 'index'])->name('edits.index');

    Route::get('projects/search', [ProjectController::class, 'search']);

    Route::get('attachments', [AttachmentController::class, 'index'])->name('attachments.index');

    Route::get('edits/search', [ChangeRequestController::class, 'search']);

    Route::get('clients/search', [ClientController::class, 'search']);

    Route::get('companies/search', [CompanyController::class, 'search']);

    Route::post('clients/{client}/follow-up', [ClientController::class, 'followUp']);

    Route::post('change-requests/{changeRequest}/paid', [ChangeRequestController::class, 'markPaid']);

    Route::post('change-requests/{changeRequest}/done', [ChangeRequestController::class, 'markDone']);

    Route::resource('clients', ClientController::class)->except(['create', 'edit']);

    Route::resource('companies.projects', ProjectController::class)->shallow()->except(['create', 'edit', 'index']);

    Route::resource('clients.companies', CompanyController::class)->shallow()->except(['create', 'edit', 'index']);

    Route::resource('projects.change-requests', ChangeRequestController::class)->shallow()
        ->only(['store', 'update', 'destroy']);

    Route::resource('change-requests.attachments', AttachmentController::class)->shallow()
        ->only(['store', 'update', 'destroy']);

    Route::get('attachments/{attachment}/file', [AttachmentController::class, 'file']);

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
