<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentOrganisation = $user?->getCurrentOrganisation();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'role' => $user?->getRole(),
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
                'accessibleModules' => $user
                    ? collect(array_keys(config('modules.list')))
                        ->filter(fn (string $module) => $user->hasModuleAccess($module))
                        ->values()
                    : [],
                'devise' => $currentOrganisation?->devise ?? 'XOF',
            ],
            'moduleDefs' => config('modules.list'),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'organisationSwitcher' => ($user?->isSuperAdmin())
                ? [
                    'current' => $currentOrganisation?->only(['id', 'name', 'code']),
                    'options' => Organisation::orderBy('name')->get(['id', 'name', 'code', 'is_active']),
                ]
                : null,
            'notifications' => $user ? [
                'unreadCount' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->latest()->limit(15)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at,
                ])->values(),
            ] : null,
        ];
    }
}
