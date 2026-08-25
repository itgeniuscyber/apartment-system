<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'notificationCenter' => fn (): array => $this->notificationCenter($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{unread: int, items: list<array<string, mixed>>} */
    private function notificationCenter(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['unread' => 0, 'items' => []];
        }

        $notifiable = $user->isManagement()
            ? $user
            : $user->tenantProfile()->first();

        if ($notifiable === null) {
            return ['unread' => 0, 'items' => []];
        }

        return [
            'unread' => $notifiable->unreadNotifications()->count(),
            'items' => array_values($notifiable->notifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'severity' => $notification->data['severity'] ?? 'info',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'reference' => $notification->data['reference'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->diffForHumans(),
                ])->all()),
        ];
    }
}
