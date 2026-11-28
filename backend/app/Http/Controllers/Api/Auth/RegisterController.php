<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegistrationService $registration, AuthService $auth): JsonResponse
    {
        $data = $request->safe()->except(['documents', 'accept_terms']);
        $user = $registration->register($data, $request->documents());
        $token = $auth->issueToken($user, (string) $request->userAgent() ?: 'spa');

        $user->load(['company', 'roles']);

        return response()->json([
            'message' => __('messages.kyc.submitted'),
            'token' => $token['token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at'],
            'user' => (new UserResource($user))->withPermissions(),
        ], 201);
    }
}
