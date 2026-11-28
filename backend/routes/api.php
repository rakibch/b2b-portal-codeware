<?php

use App\Http\Controllers\Api\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Api\Admin\CompanyPriceController;
use App\Http\Controllers\Api\Admin\CustomerGroupController;
use App\Http\Controllers\Api\Admin\KycController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\RfqController as AdminRfqController;
use App\Http\Controllers\Api\Admin\UnitController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\InvitationController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Buyer\AddressController;
use App\Http\Controllers\Api\Buyer\CartController;
use App\Http\Controllers\Api\Buyer\CompanyController as BuyerCompanyController;
use App\Http\Controllers\Api\Buyer\QuickOrderController;
use App\Http\Controllers\Api\Buyer\RfqController as BuyerRfqController;
use App\Http\Controllers\Api\Buyer\TeamController;
use App\Http\Controllers\Api\Catalog\CatalogController;
use App\Http\Controllers\Api\Shared\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Auth: Sanctum bearer tokens. Every authenticated group runs `active` (deactivated users are
| cut off immediately). Portals are hard-separated with `user.type:buyer|staff`; buyer
| transactional routes additionally require `company.approved` (KYC gate). Fine-grained
| access uses spatie permissions + Policies (tenant isolation).
*/

Route::prefix('v1')->group(function (): void {

    // ---------------------------------------------------------------- Public auth
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('register', RegisterController::class)->middleware('throttle:register');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');
        Route::get('invitations', [InvitationController::class, 'show'])->middleware('throttle:password-reset');
        Route::post('invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:register');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {

        // ------------------------------------------------------------ Session / profile
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutEverywhere']);
            Route::patch('me/locale', [AuthController::class, 'updateLocale']);
            Route::put('me/password', [AuthController::class, 'changePassword']);
        });

        Route::get('documents/{document}/download', DocumentDownloadController::class)->whereNumber('document');

        // ------------------------------------------------------------ Catalog (prices gated inside)
        Route::prefix('catalog')->middleware('throttle:search')->group(function (): void {
            Route::get('categories', [CatalogController::class, 'categories']);
            Route::get('brands', [CatalogController::class, 'brands']);
            Route::get('products', [CatalogController::class, 'index']);
            Route::get('products/lookup', [CatalogController::class, 'lookup']);
            Route::get('products/{slug}', [CatalogController::class, 'show'])->where('slug', '[a-z0-9\-]+');
            Route::post('price-quote', [CatalogController::class, 'quote'])->middleware('company.approved');
        });

        // ============================================================ BUYER PORTAL
        Route::prefix('buyer')->middleware('user.type:buyer')->group(function (): void {

            // Available to pending/rejected buyers too (profile, KYC resubmission, addresses).
            Route::get('company', [BuyerCompanyController::class, 'show']);
            Route::patch('company', [BuyerCompanyController::class, 'update']);
            Route::post('company/documents', [BuyerCompanyController::class, 'submitDocuments'])->middleware('throttle:uploads');
            Route::apiResource('addresses', AddressController::class)->whereNumber('address');

            Route::middleware('company.approved')->group(function (): void {
                // Team
                Route::get('team/members', [TeamController::class, 'members']);
                Route::patch('team/members/{member}', [TeamController::class, 'updateMember'])->whereNumber('member');
                Route::get('team/invitations', [TeamController::class, 'invitations']);
                Route::post('team/invitations', [TeamController::class, 'invite']);
                Route::delete('team/invitations/{invitation}', [TeamController::class, 'revoke'])->whereNumber('invitation');

                // Cart
                Route::get('cart', [CartController::class, 'show']);
                Route::post('cart/items', [CartController::class, 'addItems']);
                Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])->whereNumber('item');
                Route::delete('cart/items/{item}', [CartController::class, 'removeItem'])->whereNumber('item');
                Route::delete('cart', [CartController::class, 'clear']);

                // Quick order pad & bulk import
                Route::post('quick-order/validate', [QuickOrderController::class, 'validateRows']);
                Route::post('quick-order/add-to-cart', [QuickOrderController::class, 'addToCart']);
                Route::post('quick-order/import', [QuickOrderController::class, 'import'])->middleware('throttle:uploads');
                Route::get('quick-order/template', [QuickOrderController::class, 'template']);

                // RFQ
                Route::get('rfqs', [BuyerRfqController::class, 'index']);
                Route::post('rfqs', [BuyerRfqController::class, 'store']);
                Route::get('rfqs/{rfq}', [BuyerRfqController::class, 'show']);
                Route::post('rfqs/{rfq}/accept', [BuyerRfqController::class, 'accept']);
                Route::post('rfqs/{rfq}/reject', [BuyerRfqController::class, 'reject']);
                Route::post('rfqs/{rfq}/cancel', [BuyerRfqController::class, 'cancel']);
            });
        });

        // ============================================================ ADMIN PORTAL
        Route::prefix('admin')->middleware('user.type:staff')->group(function (): void {

            // KYC queue
            Route::middleware('permission:kyc.review')->group(function (): void {
                Route::get('kyc', [KycController::class, 'index']);
                Route::get('kyc/{company}', [KycController::class, 'show']);
                Route::post('kyc/{company}/approve', [KycController::class, 'approve']);
                Route::post('kyc/{company}/reject', [KycController::class, 'reject']);
                Route::post('kyc/{company}/suspend', [KycController::class, 'suspend']);
                Route::patch('kyc/{company}/documents/{document}', [KycController::class, 'reviewDocument'])->whereNumber('document');
            });

            // Companies
            Route::middleware('permission:companies.view')->group(function (): void {
                Route::get('companies', [AdminCompanyController::class, 'index']);
                Route::get('companies/{company}', [AdminCompanyController::class, 'show']);
                Route::get('companies/{company}/users', [AdminCompanyController::class, 'users']);
            });
            Route::patch('companies/{company}/terms', [AdminCompanyController::class, 'updateTerms'])->middleware('permission:credit.manage');

            // Contract prices
            Route::middleware('permission:pricing.manage')->group(function (): void {
                Route::get('companies/{company}/prices', [CompanyPriceController::class, 'index']);
                Route::post('companies/{company}/prices', [CompanyPriceController::class, 'store']);
                Route::patch('companies/{company}/prices/{price}', [CompanyPriceController::class, 'update'])->whereNumber('price');
                Route::delete('companies/{company}/prices/{price}', [CompanyPriceController::class, 'destroy'])->whereNumber('price');
                Route::apiResource('customer-groups', CustomerGroupController::class)->whereNumber('customer_group');
                Route::put('products/{product}/tiers', [AdminProductController::class, 'syncTiers'])->whereNumber('product');
            });

            // Catalog read (staff with catalog.view: sales & warehouse too)
            Route::middleware('permission:catalog.view')->group(function (): void {
                Route::get('products', [AdminProductController::class, 'index']);
                Route::get('products/{product}', [AdminProductController::class, 'show'])->whereNumber('product');
                Route::get('categories', [AdminCategoryController::class, 'index']);
                Route::get('brands', [AdminBrandController::class, 'index']);
                Route::get('units', [UnitController::class, 'index']);
            });

            Route::post('products/{product}/stock-adjustments', [AdminProductController::class, 'adjustStock'])
                ->whereNumber('product')
                ->middleware('permission:inventory.adjust');

            // Catalog write
            Route::middleware('permission:catalog.manage')->group(function (): void {
                Route::post('products', [AdminProductController::class, 'store']);
                Route::match(['put', 'patch'], 'products/{product}', [AdminProductController::class, 'update'])->whereNumber('product');
                Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->whereNumber('product');
                Route::post('products/{id}/restore', [AdminProductController::class, 'restore'])->whereNumber('id');
                Route::apiResource('categories', AdminCategoryController::class)->except('index')->whereNumber('category');
                Route::apiResource('brands', AdminBrandController::class)->except('index')->whereNumber('brand');
                Route::apiResource('units', UnitController::class)->except('index')->whereNumber('unit');
            });

            // RFQs
            Route::middleware('permission:rfqs.view')->group(function (): void {
                Route::get('rfqs', [AdminRfqController::class, 'index']);
                Route::get('rfqs/{rfq}', [AdminRfqController::class, 'show']);
            });
            Route::middleware('permission:rfqs.quote')->group(function (): void {
                Route::post('rfqs/{rfq}/review', [AdminRfqController::class, 'startReview']);
                Route::post('rfqs/{rfq}/quote', [AdminRfqController::class, 'quote']);
                Route::post('rfqs/{rfq}/decline', [AdminRfqController::class, 'decline']);
            });
        });
    });
});
