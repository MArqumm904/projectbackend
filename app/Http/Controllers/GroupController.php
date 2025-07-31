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
            'group_name' => 'required|string|max:255|unique:groups,group_name',
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
    public function show($groupid)
    {
        // Authenticated user ki ID
        $userId = auth()->id();

        // Specific group fetch karein jo current user ne banaya ho
        $group = Group::with('creator')
            ->where('id', $groupid)
            ->where('group_created_by', $userId)
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }
    public function showall()
    {
        // Current authenticated user ki ID get karenge
        $userId = auth()->id();

        // Groups retrieve karenge jo current user ne create kiye hain
        $groups = Group::with('creator')
            ->where('group_created_by', $userId)
            ->latest()
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['message' => 'No groups found for this user'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $groups,
            'total' => $groups->count()
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $groupid)
    {
        $group = Group::where('id', $groupid)
            ->where('group_created_by', Auth::id())
            ->first();

        if (!$group) {
            return response()->json(['message' => 'No group found to update'], 404);
        }

        $validated = $request->validate([
            'group_name' => 'sometimes|required|string|max:255',
            'group_description' => 'nullable|string',
            'group_category' => 'nullable|string|max:255',
            'group_industry' => 'nullable|string|max:255',
            'group_about' => 'nullable|string',
            'group_history' => 'nullable|string',
            'group_profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'group_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        // Handle profile photo
        if ($request->hasFile('group_profile_photo')) {
            if ($group->group_profile_photo) {
                Storage::disk('public')->delete($group->group_profile_photo);
            }
            $validated['group_profile_photo'] = $request->file('group_profile_photo')->store('groups/profile_photos', 'public');
        }

        // Handle banner image
        if ($request->hasFile('group_banner_image')) {
            if ($group->group_banner_image) {
                Storage::disk('public')->delete($group->group_banner_image);
            }
            $validated['group_banner_image'] = $request->file('group_banner_image')->store('groups/banner_images', 'public');
        }

        $group->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully.',
            'data' => $group,
        ]);
    }
}
