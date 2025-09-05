<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->when($search, fn($q) =>
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users'   => $users,
            'filters' => ['search' => $search],
        ]);
    }
}
