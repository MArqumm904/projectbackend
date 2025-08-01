<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Illuminate\Support\Facades\Storage;


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
        // Validate inputs
        $request->validate([
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'location' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        if (
            !$request->hasFile('profile_photo') &&
            !$request->hasFile('cover_photo') &&
            !$request->filled('location') &&
            !$request->filled('headline') &&
            !$request->filled('name')
        ) {
            return response()->json([
                'message' => 'No data provided to update',
                'errors' => [
                    'profile_photo' => ['Nothing to update.'],
                    'cover_photo' => ['Nothing to update.'],
                    'location' => ['Nothing to update.'],
                    'headline' => ['Nothing to update.'],
                    'name' => ['Nothing to update.'],
                ],
            ], 422);
        }

        try {
            // Get the profile
            $profile = Profile::where('user_id', $user_id)->first();
            if (!$profile) {
                return response()->json(['message' => 'Profile not found'], 404);
            }

            $updateData = [];

            // Handle profile photo
            if ($request->hasFile('profile_photo')) {
                $profileFile = $request->file('profile_photo');
                if (!$profileFile->isValid()) {
                    return response()->json([
                        'message' => 'Invalid profile photo upload',
                        'errors' => ['profile_photo' => ['The uploaded profile photo is invalid.']]
                    ], 422);
                }
                $profilePath = $profileFile->store('profiles/profile_photos', 'public');
                $updateData['profile_photo'] = $profilePath;
            }

            // Handle cover photo
            if ($request->hasFile('cover_photo')) {
                $coverFile = $request->file('cover_photo');
                if (!$coverFile->isValid()) {
                    return response()->json([
                        'message' => 'Invalid cover photo upload',
                        'errors' => ['cover_photo' => ['The uploaded cover photo is invalid.']]
                    ], 422);
                }
                $coverPath = $coverFile->store('profiles/cover_photos', 'public');
                $updateData['cover_photo'] = $coverPath;
            }

            // Add location and headline to updateData if present
            if ($request->filled('location')) {
                $updateData['location'] = $request->input('location');
            }

            if ($request->filled('headline')) {
                $updateData['headline'] = $request->input('headline');
            }

            // Update profile table
            $profile->update($updateData);

            // Update name in users table
            if ($request->filled('name')) {
                User::with('id', $user_id)->update([
                    'name' => $request->input('name')
                ]);
            }

            return response()->json([
                'message' => 'Profile updated successfully',
                'updated_profile_fields' => $updateData,
                'name_updated' => $request->filled('name') ? $request->input('name') : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating profile: ' . $e->getMessage()
            ], 500);
        }
    }


    public function deleteProfileFields(Request $request, $user_id)
    {
        // Validate that at least one field is provided
        if (
            !$request->filled('profile_photo') &&
            !$request->filled('cover_photo') &&
            !$request->filled('location') &&
            !$request->filled('headline') &&
            !$request->filled('name')
        ) {
            return response()->json([
                'message' => 'No field provided for deletion',
            ], 422);
        }

        try {
            // Get the profile
            $profile = Profile::where('user_id', $user_id)->first();
            if (!$profile) {
                return response()->json(['message' => 'Profile not found'], 404);
            }

            $deletedFields = [];

            // Delete profile photo
            if ($request->filled('profile_photo') && $request->input('profile_photo') === 'delete') {
                if ($profile->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->profile_photo)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->profile_photo);
                }
                $profile->profile_photo = null;
                $deletedFields[] = 'profile_photo';
            }

            // Delete cover photo
            if ($request->filled('cover_photo') && $request->input('cover_photo') === 'delete') {
                if ($profile->cover_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->cover_photo)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->cover_photo);
                }
                $profile->cover_photo = null;
                $deletedFields[] = 'cover_photo';
            }

            // Clear location
            if ($request->filled('location') && $request->input('location') === 'delete') {
                $profile->location = null;
                $deletedFields[] = 'location';
            }

            // Clear headline
            if ($request->filled('headline') && $request->input('headline') === 'delete') {
                $profile->headline = null;
                $deletedFields[] = 'headline';
            }

            // Save profile updates
            $profile->save();

            // Clear name in User table
            if ($request->filled('name') && $request->input('name') === 'delete') {
                $user = User::find($user_id);
                if ($user) {
                    $user->name = null;
                    $user->save();
                    $deletedFields[] = 'name';
                }
            }

            return response()->json([
                'message' => 'Selected fields deleted successfully',
                'deleted_fields' => $deletedFields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting profile fields: ' . $e->getMessage()
            ], 500);
        }
    }
}
