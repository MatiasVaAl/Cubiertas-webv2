<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WorkshopSetting;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', ['contact' => WorkshopSetting::current()->contact ?? [], 'featured' => Product::with('images')->where('available', true)->when(app()->isProduction(), fn ($q) => $q->where('demonstration', false))->orderBy('name')->limit(3)->get()]);
    }
}
