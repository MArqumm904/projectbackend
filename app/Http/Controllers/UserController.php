<?php


namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required_without:phone|nullable|email|max:255',
            'phone' => 'required_without:email|nullable|string|max:20',
            'password' => 'required|string|min:6',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'dob' => 'required|date',
            'location' => 'required|string|max:255',
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            return response()->json(['error' => 'Either email or phone is required.'], 422);
        }

        $userData = [
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
        ];
        if (!empty($validated['email'])) {
            $userData['email'] = $validated['email'];
        }
        if (!empty($validated['phone'])) {
            $userData['phone'] = $validated['phone'];
        }
        $user = User::create($userData);

        // Create profile
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->dob = $validated['dob'];
        $profile->location = $validated['location'];
        $profile->save();

        // Create skills if provided
        if (!empty($validated['skills'])) {
            foreach ($validated['skills'] as $skill) {
                UserSkill::create([
                    'user_id' => $user->id,
                    'skill' => $skill,
                    'proficiency' => 'none',
                ]);
            }
        }

        return response()->json(['message' => 'User registered successfully.'], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required_without:phone|nullable|email',
            'phone' => 'required_without:email|nullable|string',
            'password' => 'required|string',
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            return response()->json(['error' => 'Either email or phone is required.'], 422);
        }

        $userQuery = User::query();
        if (!empty($validated['email'])) {
            $userQuery->where('email', $validated['email']);
        } else {
            $userQuery->where('phone', $validated['phone']);
        }
        $user = $userQuery->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user,
        ]);
    }
}
