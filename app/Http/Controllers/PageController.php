<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $pageType): View
    {
        $page = Page::query()
            ->active()
            ->where('page_type', $pageType)
            ->firstOrFail();

        return view('pages.show', compact('page'));
    }
}
