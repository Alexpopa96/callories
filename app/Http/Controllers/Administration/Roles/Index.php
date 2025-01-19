<?php

namespace App\Http\Controllers\Administration\Roles;

use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Index extends Controller
{
    public function __invoke()
    {
        request()->validate([
            'direction' => ['in:asc,desc'],
            'field' => ['in:name'],
            'perPage' => [ Rule::in([10, 15, 25])]
        ]);

        $perPage = (request('perPage') ? request('perPage') : 15);

        $query = Role::query()->selectRaw('roles.id, roles.name, roles.guard_name');

        if (request('search')) {
            $search  = explode(' ', request('search'));
            foreach ($search as $value) {
                $query->where(function ($query) use ($value) {
                    $query->orWhere('roles.name', 'LIKE', "%".$value.'%');
                });
            }
        }

        if (request()->has(['field', 'direction'])) {
            $query->orderBy(request('field'), request('direction'));
        } else {
            $query->orderBy('id', 'Desc');
        }

        return Inertia::render('Administration/Roles/Index', [
            'can' => [
                'viewRoles' => Auth::user()->can('view roles'),
                'create' => Auth::user()->can('create role'),
                'edit' => Auth::user()->can('edit role'),
            ],
            'roles' => $query->paginate($perPage)->withQueryString(),
            'filters' => request()->all(['search', 'field', 'direction']),
            'perPage' => intval($perPage),
            'totalRoles' => intVal(Role::count())
        ]);
    }
}
