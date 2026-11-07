<?php

use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\LabelsController;
use App\Http\Controllers\LocationLabelsController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\ScannerAppController;
use App\Http\Controllers\StockExportController;
use App\Livewire\Auth\Login;
use App\Livewire\Contractors;
use App\Livewire\Dashboard;
use App\Livewire\Documents;
use App\Livewire\Layout;
use App\Livewire\Products;
use App\Livewire\Roles;
use App\Livewire\Scanner;
use App\Livewire\Stock;
use App\Livewire\Users;
use App\Livewire\Warehouses;
use App\Livewire\Webhooks;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/logowanie', Login::class)->name('login');
});

Route::post('/wyloguj', LogoutController::class)->middleware('auth')->name('logout');

// Mobilny skaner (PWA). Strona publiczna – urządzenie uwierzytelnia się tokenem w API.
Route::controller(ScannerAppController::class)->prefix('skaner')->name('scanner.')->group(function () {
    Route::get('/', 'show')->name('app');
    Route::get('manifest.webmanifest', 'manifest')->name('manifest');
    Route::get('sw.js', 'serviceWorker')->name('sw');
});

// Każda trasa panelu chroniona uprawnieniem RBAC (App\Enums\Ability).
Route::middleware('auth')->group(function () {
    Route::livewire('/', Dashboard::class)->name('dashboard');

    Route::middleware('can:documents.view')->group(function () {
        Route::livewire('/dokumenty', Documents\Index::class)->name('documents.index');
        Route::livewire('/dokumenty/nowy/{type}', Documents\Create::class)->name('documents.create')->can('documents.create');
        Route::livewire('/dokumenty/{document}', Documents\Show::class)->name('documents.show');
        Route::get('/dokumenty/{document}/pdf', DocumentPdfController::class)->name('documents.pdf');
    });

    Route::middleware('can:stock.view')->group(function () {
        Route::livewire('/stany', Stock\Index::class)->name('stock.index');
        Route::get('/stany/eksport', StockExportController::class)->name('stock.export')->can('stock.export');

        Route::livewire('/towary', Products\Index::class)->name('products.index');
        Route::livewire('/towary/nowy', Products\Form::class)->name('products.create')->can('products.manage');
        Route::livewire('/towary/{product}', Products\Show::class)->name('products.show');
        Route::livewire('/towary/{product}/edycja', Products\Form::class)->name('products.edit')->can('products.manage');
        Route::get('/etykiety', LabelsController::class)->name('labels');
        Route::get('/etykiety/regal/{rack}', LocationLabelsController::class)->name('labels.locations');
        Route::livewire('/plan', Layout\Plan::class)->name('layout.plan');

        Route::livewire('/kontrahenci', Contractors\Index::class)->name('contractors.index');
        Route::livewire('/magazyny', Warehouses\Index::class)->name('warehouses.index');
    });

    Route::livewire('/skanery', Scanner\Devices::class)->name('scanner.devices')->can('scanner.use');

    Route::livewire('/webhooki', Webhooks\Index::class)->name('webhooks.index')->can('webhooks.manage');
    Route::livewire('/uzytkownicy', Users\Index::class)->name('users.index')->can('users.manage');
    Route::livewire('/role', Roles\Index::class)->name('roles.index')->can('roles.manage');
});
