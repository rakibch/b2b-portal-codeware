<?php

namespace App\Http\Controllers\Api\Shared;

use App\Enums\AnnouncementAudience;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller
{
    /** Published, unexpired announcements for the viewer's audience (localised, cached briefly). */
    public function __invoke(Request $request): JsonResponse
    {
        $audience = $request->user()->isStaff() ? AnnouncementAudience::Staff : AnnouncementAudience::Buyers;
        $locale = app()->getLocale();

        $data = Cache::remember("announcements.{$audience->value}.{$locale}", 300, fn () => Announcement::query()
            ->visibleTo($audience)
            ->limit(5)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'level' => $a->level,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->all());

        return response()->json(['data' => $data]);
    }
}
