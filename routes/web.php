<?php

use App\Http\Controllers\Admin\CatalogManagementController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\TrackingController;
use App\Models\QuoteLine;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', HomeController::class)->name('home');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{product}', [CatalogController::class, 'show'])->name('catalog.show');
Route::view('/cotizar', 'configurator')->name('configurator');
Route::view('/guia-de-medidas', 'guide')->name('guide');
Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::view('/carrito/{line}/editar', 'configurator')->name('cart.edit');
Route::patch('/carrito/{line}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/carrito/{line}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::post('/carrito/{line}/separar', [CartController::class, 'split'])->name('cart.split');
Route::get('/solicitud', [QuoteController::class, 'create'])->name('quotes.create');
Route::post('/solicitud', [QuoteController::class, 'store'])->middleware('throttle:10,1')->block()->name('quotes.store');
Route::get('/solicitud/recibida', [QuoteController::class, 'received'])->name('quotes.received');
Route::view('/consultar-pedido', 'tracking.lookup')->name('tracking.lookup');
Route::post('/consultar-pedido', [TrackingController::class, 'authenticate'])->middleware('throttle:6,1')->name('tracking.authenticate');
Route::get('/seguimiento/{quote}', [TrackingController::class, 'show'])->name('tracking.show');
Route::post('/seguimiento/{quote}/aceptar', [QuoteController::class, 'accept'])->middleware('throttle:10,1')->name('quotes.accept');
Route::post('/seguimiento/{quote}/revision', [QuoteController::class, 'revision'])->middleware('throttle:10,1')->name('quotes.revision');
Route::middleware('guest')->group(function () {
    Route::get('/equipo/ingresar', [AuthController::class, 'create'])->name('login');
    Route::post('/equipo/ingresar', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});
Route::post('/equipo/salir', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/pedidos/{order}/comprobante', [PaymentController::class, 'store'])->middleware('throttle:6,1')->name('payments.store');
Route::get('/comprobantes/{payment}', [PaymentController::class, 'receipt'])->middleware('throttle:30,1')->name('payments.receipt');
Route::prefix('admin')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', [OperationsController::class, 'dashboard'])->name('admin.dashboard');
    foreach (['quotes' => 'cotizaciones', 'orders' => 'pedidos', 'payments' => 'pagos', 'deliveries' => 'entregas'] as $area => $path) {
        Route::get('/'.$path, [OperationsController::class, 'index'])->defaults('area', $area)->name('admin.'.$area);
    }
    Route::get('/cotizaciones/{quote}', [OperationsController::class, 'show'])->name('admin.quotes.show');
    Route::post('/cotizaciones/{quote}/lineas/{line}', [OperationsController::class, 'resolveLine'])->name('admin.lines.resolve');
    Route::post('/cotizaciones/{quote}/transporte', [OperationsController::class, 'transport'])->name('admin.transport');
    Route::post('/cotizaciones/{quote}/emitir', [OperationsController::class, 'issue'])->name('admin.quotes.issue');
    Route::post('/cotizaciones/{quote}/estado', [OperationsController::class, 'status'])->name('admin.quotes.status');
    Route::post('/cotizaciones/{quote}/renovar-acceso', [OperationsController::class, 'rotate'])->name('admin.tracking.rotate');
    Route::post('/pagos/{payment}/verificar', [PaymentController::class, 'verify'])->name('admin.payments.verify');
    Route::post('/pedidos/{order}/estado', [OperationsController::class, 'transition'])->name('admin.orders.transition');
    Route::get('/referencias/{line}', function (QuoteLine $line) {
        Gate::authorize('update', $line->quote);
        $path = $line->snapshot['configuration']['reference_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    })->name('admin.references');
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/catalogo', [CatalogManagementController::class, 'index'])->name('admin.catalog');
        Route::post('/catalogo/productos/{product?}', [CatalogManagementController::class, 'product'])->name('admin.products.save');
        Route::post('/catalogo/{product}/imagenes', [CatalogManagementController::class, 'image'])->name('admin.images.save');
        Route::delete('/catalogo/imagenes/{image}', [CatalogManagementController::class, 'removeImage'])->name('admin.images.remove');
        Route::post('/adicionales/{work?}', [CatalogManagementController::class, 'additional'])->name('admin.works.save');
        Route::get('/tarifas', [CatalogManagementController::class, 'rates'])->name('admin.rates');
        Route::post('/tarifas/{rate?}', [CatalogManagementController::class, 'rate'])->name('admin.rates.save');
        Route::get('/configuracion', [SettingsController::class, 'index'])->name('admin.settings');
        Route::post('/configuracion', [SettingsController::class, 'update'])->name('admin.settings.update');
        Route::post('/usuarios/{user?}', [SettingsController::class, 'user'])->name('admin.users.save');
    });
});
