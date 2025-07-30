<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;

class GroupController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|max:255',
            'group_description' => 'nullable|string',
            'group_type' => 'required|in:public,private,secret',
            'group_industry' => 'nullable|string|max:255',
            'group_history' => 'nullable|string',
            'group_profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'group_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
             

        $validated['group_created_by'] = Auth::id();

        // Handle profile photo upload
        if ($request->hasFile('group_profile_photo')) {
            $photoPath = $request->file('group_profile_photo')->store('groups/profile_photos', 'public');
            $validated['group_profile_photo'] = $photoPath;
        }

        // Handle banner image upload
            if ($request->hasFile('group_banner_image')) {
            $bannerPath = $request->file('group_banner_image')->store('groups/banner_images', 'public');
            $validated['group_banner_image'] = $bannerPath;
        }

        $group = Group::create($validated);
        $group->load('creator');

        return response()->json($group, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $group = Group::with('creator')->find($id);
        
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        return response()->json($group);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $group = Group::find($id);
        
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Check if user is the creator of the group
        if ($group->created_by != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'group_type' => 'sometimes|required|in:public,private,secret',
            'industry' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'group_about' => 'nullable|string',
            'history' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($group->profile_photo) {
                Storage::disk('public')->delete($group->profile_photo);
            }
            $photoPath = $request->file('profile_photo')->store('groups/profile_photos', 'public');
            $validated['profile_photo'] = $photoPath;
        }

        // Handle banner image upload
        if ($request->hasFile('banner_image')) {
            // Delete old banner if exists
            if ($group->banner_image) {
                Storage::disk('public')->delete($group->banner_image);
            }
            $bannerPath = $request->file('banner_image')->store('groups/banner_images', 'public');
            $validated['banner_image'] = $bannerPath;
        }

        $group->update($validated);
        $group->load('creator');

        return response()->json($group);
    }

}
