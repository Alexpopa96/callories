<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'fit';

    /**
     * The admin panel keeps the Argon template; everything else uses the mobile-first shell.
     */
    public function rootView(Request $request): string
    {
        return $request->is('dashboard', 'administration/*', 'user/*') ? 'app' : $this->rootView;
    }

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
        $user = auth()->user();

        return [
            ...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->permissionList() : [],
                'can' => [
                    'viewDashboard' => Auth::user() ? Auth::user()->can('view dashboard') : false,
                    'viewAdministration' => Auth::user() ? Auth::user()->can('view administration') : false,
                    'viewUsers' => Auth::user() ? Auth::user()->can('view users') : false,
                    'viewRoles' => Auth::user() ? Auth::user()->can('view roles') : false,
                    'viewPermissions' => Auth::user() ? Auth::user()->can('view permissions') : false,
                ],
            ],
            'toast' => function () {
                return [
                    'error' => Session::get('error'),
                    'success' => Session::get('success'),
                ];
            },
            'impersonate' => Session::get('impersonate'),
            'role_id' => Auth::user() ? Auth::user()->roles()->first()?->id : null
        ];
    }
}
