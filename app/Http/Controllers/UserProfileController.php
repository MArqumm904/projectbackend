<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;

class UserProfileController extends Controller
{
    public function getProfile($user_id)
    {
        $user = User::with('profile')->find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'user' => $user,
            'profile' => $user->profile
        ]);
    }
    public function updateProfile(Request $request, $user_id)
    {

        if (!$request->hasFile('profile_photo')) {
            return response()->json([
                'message' => 'No file uploaded',
                'errors' => ['profile_photo' => ['The profile photo field is required.']],
                'debug' => [
                    'has_file' => $request->hasFile('profile_photo'),
                    'all_files' => array_keys($request->allFiles()),
                    'content_type' => $request->header('Content-Type'),
                    'request_method' => $request->method(),
                    'all_headers' => $request->headers->all(),
                ]
            ], 422);
        }

        $file = $request->file('profile_photo');

        if (!$file->isValid()) {
            return response()->json([
                'message' => 'Invalid file upload',
                'errors' => ['profile_photo' => ['The uploaded file is invalid.']]
            ], 422);
        }

        $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $photoPath = $file->store('profiles/profile_photos', 'public');

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
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }
}
