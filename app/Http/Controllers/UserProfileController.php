<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;
class UserProfileController extends Controller
{
    public function getProfile($user_id)
    {
        // Eager load the 'profile' relationship
        $user = User::with('profile')->find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Return user data along with profile data
        return response()->json([
            'user' => $user,
            'profile' => $user->profile
        ]);
    }
    public function updateProfile(Request $request, $user_id)
    {
        $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
    
        if (!$request->hasFile('profile_photo')) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }
    
        $photoPath = $request->file('profile_photo')->store('profiles/profile_photos', 'public');
    
        $profile = Profile::where('user_id', $user_id)->first();
    
        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }
    
        $profile->update([
            'profile_photo' => $photoPath,
        ]);
    
        return response()->json([
            'message' => 'Profile photo updated successfully',
            'profile_photo' => $photoPath,
        ]);
    }
} 