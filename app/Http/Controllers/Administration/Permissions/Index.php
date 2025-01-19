<?php

namespace App\Http\Controllers\Administration\Permissions;

use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class Index extends Controller
{
    public function __invoke()
    {
        request()->validate([
            'direction' => ['in:asc,desc'],
            'field' => ['in:name,group'],
            'perPage' => [ Rule::in([10, 15, 25])]
        ]);

        $perPage = (request('perPage') ? request('perPage') : 15);

        $query = Permission::query()->selectRaw('permissions.id, permissions.name, permissions.group, permissions.parent, permissions.guard_name');

        if (request('search')) {
            $search  = explode(' ', request('search'));
            foreach ($search as $value) {
                $query->where(function ($query) use ($value) {
                    $query->orWhere('permissions.name', 'LIKE', "%".$value.'%');
                    $query->orWhere('permissions.group', 'LIKE','%'.$value.'%');
                    $query->orWhere('permissions.parent','LIKE','%'.$value.'%');
                });
            }
        }

        if (request()->has(['field', 'direction'])) {
            $query->orderBy(request('field'), request('direction'));
        } else {
            $query->orderBy('id', 'Desc');
        }

        return Inertia::render('Administration/Permissions/Index', [
            'can' => [
                'viewRoles' => Auth::user()->can('view roles'),
                'create' => Auth::user()->can('create role'),
                'edit' => Auth::user()->can('edit role'),
            ],
            'permissions' => $query->paginate($perPage)->withQueryString(),
            'filters' => request()->all(['search', 'field', 'direction']),
            'perPage' => intval($perPage),
            'totalPermissions' => intval(Permission::count())
        ]);
    }
}

