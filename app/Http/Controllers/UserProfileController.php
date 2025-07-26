<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

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
} 