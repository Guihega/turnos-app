<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpCenterController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Help/Index', [
            'userRole' => $request->user()->role ?? 'operator',
        ]);
    }
}
