<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Directory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $favorites = $request->user()->favorites()->published()
            ->with(Directory::cardRelations())
            ->orderByPivot('created_at', 'desc')->paginate(12);

        return view('account.favorites', ['favorites' => $favorites]);
    }
}
