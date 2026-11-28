<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateLocaleRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name') ?: (string) $request->userAgent() ?: 'spa',
            $request->ip(),
        );

        $result['user']->load(['company.customerGroup', 'roles']);

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => (new UserResource($result['user']))->withPermissions(),
        ]);
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user()->load(['company.customerGroup', 'company.accountManager', 'roles']);

        return (new UserResource($user))->withPermissions();
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => __('messages.auth.logged_out')]);
    }

    public function logoutEverywhere(Request $request): JsonResponse
    {
        $this->auth->logoutEverywhere($request->user());

        return response()->json(['message' => __('messages.auth.logged_out_everywhere')]);
    }

    public function updateLocale(UpdateLocaleRequest $request): JsonResponse
    {
        $request->user()->update(['locale' => $request->validated('locale')]);
        app()->setLocale($request->validated('locale'));

        return response()->json([
            'message' => __('messages.auth.locale_updated'),
            'locale' => $request->validated('locale'),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->validated('password'));

        return response()->json(['message' => __('messages.auth.password_changed')]);
    }
}
