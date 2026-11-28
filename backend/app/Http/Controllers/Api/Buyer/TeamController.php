<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\InviteUserRequest;
use App\Http\Requests\Buyer\UpdateMemberRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\Company\CompanyUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Company sub-user management (Buyer Admin only). */
class TeamController extends Controller
{
    public function __construct(private readonly CompanyUserService $users) {}

    public function members(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('company.manage_users'), 403);

        $members = User::query()
            ->where('company_id', $request->user()->company_id)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        return UserResource::collection($members);
    }

    public function updateMember(UpdateMemberRequest $request, User $member): UserResource
    {
        Gate::authorize('manageMember', $member);

        return new UserResource($this->users->updateMember($request->user(), $member, $request->validated())->load('roles:id,name'));
    }

    public function invitations(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('company.manage_users'), 403);

        $invitations = CompanyInvitation::query()
            ->where('company_id', $request->user()->company_id)
            ->with('inviter:id,name')
            ->latest()
            ->limit(100)
            ->get();

        return InvitationResource::collection($invitations);
    }

    public function invite(InviteUserRequest $request): JsonResponse
    {
        $invitation = $this->users->invite($request->user()->company, $request->user(), $request->validated());

        return (new InvitationResource($invitation))
            ->additional(['message' => __('messages.team.invitation_sent', ['email' => $invitation->email])])
            ->response()
            ->setStatusCode(201);
    }

    public function revoke(CompanyInvitation $invitation): JsonResponse
    {
        Gate::authorize('delete', $invitation);

        $this->users->revokeInvitation($invitation);

        return response()->json(['message' => __('messages.team.invitation_revoked')]);
    }
}
