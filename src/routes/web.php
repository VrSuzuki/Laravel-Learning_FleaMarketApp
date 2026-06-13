<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentManageController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'index'])->name('home');
Route::get('/about', [MarketplaceController::class, 'about'])->name('about');
Route::get('/search', [MarketplaceController::class, 'search'])->name('search.advanced');
Route::get('/users/{user:handle}', [ProfileController::class, 'show'])->name('profiles.show');
Route::get('/users/{user:handle}/following', [ProfileController::class, 'following'])->name('profiles.following');
Route::get('/users/{user:handle}/followers', [ProfileController::class, 'followers'])->name('profiles.followers');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('guest')->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profiles.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profiles.update');

    Route::get('/contents/create', [ContentManageController::class, 'create'])->name('contents.create');
    Route::post('/contents', [ContentManageController::class, 'store'])->name('contents.store');
    Route::get('/contents/{content}/edit', [ContentManageController::class, 'edit'])->name('contents.edit');
    Route::put('/contents/{content}', [ContentManageController::class, 'update'])->name('contents.update');
    Route::post('/profile/content-order', [ContentManageController::class, 'reorder'])->name('contents.reorder');

    Route::post('/contents/{content}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('/contents/{content}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/users/{user:handle}/follow', [FollowController::class, 'toggle'])->name('follows.toggle');

    Route::get('/settings', [AccountController::class, 'settings'])->name('settings.index');
    Route::post('/settings', [AccountController::class, 'updateSettings'])->name('settings.update');
    Route::delete('/settings/account', [AccountController::class, 'destroy'])->name('settings.destroy');
    Route::get('/favorites', [AccountController::class, 'favorites'])->name('favorites.index');
    Route::get('/sales', [AccountController::class, 'sales'])->name('sales.index');
    Route::get('/purchases', [AccountController::class, 'purchases'])->name('purchases.index');
    Route::get('/purchases/{order}', [AccountController::class, 'purchaseDetail'])->name('purchases.show');
    Route::get('/library', [AccountController::class, 'library'])->name('library.index');
    Route::get('/following', [AccountController::class, 'following'])->name('following.index');
    Route::get('/followers', [AccountController::class, 'followers'])->name('followers.index');
    Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications.index');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{content}', [CartController::class, 'store'])->name('cart.store');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/checkout', [CartController::class, 'checkout'])->name('checkout.start');
    Route::get('/checkout/success', [CartController::class, 'success'])->name('checkout.success');
    Route::get('/downloads/{content}', [CartController::class, 'download'])->name('downloads.show');
});

Route::get('/contents/{content}', [MarketplaceController::class, 'show'])->name('contents.show');
