<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Education;
use App\Models\UserCertification;
use App\Models\UserInfo;
use App\Models\UserOverview;
use App\Models\UserSkill;

class AboutController extends Controller
{
    // Create Education
    public function createEducation(Request $request)
    {
        $validated = $request->validate([
            'schooluniname' => 'required|string',
            'qualification' => 'required|string',
            'field_of_study' => 'required|string',
            'location' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $validated['user_id'] = Auth::id();
        $education = Education::create($validated);
        return response()->json($education, 201);
    }

    // Create Certification (with image upload)
    public function createCertification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'organization' => 'required|string',
            'start_year' => 'required|integer',
            'end_year' => 'required|integer',
            'description' => 'required|string',
            'certificate_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $imagePath = $request->file('certificate_photo')->store('certificates', 'public');
        $certification = UserCertification::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'organization' => $validated['organization'],
            'start_year' => $validated['start_year'],
            'end_year' => $validated['end_year'],
            'description' => $validated['description'],
            'certificate_photo' => $imagePath,
        ]);
        return response()->json($certification, 201);
    }

    // Create UserInfo
    public function createUserInfo(Request $request)
    {
        $validated = $request->validate([
            'contact' => 'required|string',
            'email' => 'required|email',
            'languages_spoken' => 'required|string',
            'website' => 'required|string',
            'social_link' => 'required|string',
            'gender' => 'required|string',
            'date_of_birth' => 'required|date',
        ]);
        $validated['user_id'] = Auth::id();
        $userInfo = UserInfo::create($validated);
        return response()->json($userInfo, 201);
    }

    // Create UserOverview
    public function createUserOverview(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
        ]);
        $validated['user_id'] = Auth::id();
        $overview = UserOverview::create($validated);
        return response()->json($overview, 201);
    }

    // Create UserSkill
    public function createUserSkill(Request $request)
    {
        $validated = $request->validate([
            'skill' => 'required|string',
            'proficiency' => 'required|string',
            'description' => 'required|string',
        ]);
        $validated['user_id'] = Auth::id();
        $skill = UserSkill::create($validated);
        return response()->json($skill, 201);
    }
}
