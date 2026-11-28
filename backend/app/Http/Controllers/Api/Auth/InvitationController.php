<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Resources\UserResource;
use App\Models\CompanyInvitation;
use App\Services\Auth\AuthService;
use App\Services\Company\CompanyUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /** Public preview of an invitation (company name + email) so the accept screen can render. */
    public function show(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);

        $invitation = CompanyInvitation::findValidByToken($request->string('token'));

        if ($invitation === null) {
            throw BusinessRuleException::make('invitation_invalid', [], 410);
        }

        $invitation->load('company:id,name', 'inviter:id,name');

        return response()->json([
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $invitation->role,
            'company' => $invitation->company->name,
            'invited_by' => $invitation->inviter->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, CompanyUserService $users, AuthService $auth): JsonResponse
    {
        $user = $users->acceptInvitation($request->validated('token'), $request->safe()->except('token'));
        $token = $auth->issueToken($user, (string) $request->userAgent() ?: 'spa');

        $user->load(['company', 'roles']);

        return response()->json([
            'message' => __('messages.auth.invitation_accepted'),
            'token' => $token['token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at'],
            'user' => (new UserResource($user))->withPermissions(),
        ], 201);
    }
}
