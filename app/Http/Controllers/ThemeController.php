<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $theme = $request->user()->theme_preference === 'dark' ? 'light' : 'dark';

        $request->user()->update(['theme_preference' => $theme]);

        return back();
    }
}
