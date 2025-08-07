<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'page_name' => 'required|string|max:255',
            'page_description' => 'nullable|string',
            'page_category' => 'nullable|string|max:255',
            'page_type' => 'nullable|string|max:255',
            'page_profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'page_banner_image' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $user = $request->user();

        if ($request->hasFile('page_profile_photo')) {
            $validated['page_profile_photo'] = $request->file('page_profile_photo')->store('pages/profile_photos', 'public');
        }

        if ($request->hasFile('page_banner_image')) {
            $validated['page_cover_photo'] = $request->file('page_banner_image')->store('pages/banner_images', 'public');
        }

        $validated['owner_id'] = $user->id;

        $page = Page::create($validated);

        return response()->json([
            'message' => 'Page created successfully',
            'data' => $page
        ], 201);
    }
}
