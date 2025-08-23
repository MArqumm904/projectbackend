<?php

namespace App\Http\Controllers;

use App\Models\UserMembership;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\MembershipDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserMembershipController extends Controller
{
    public function getCompanies(Request $request)
    {
        $perPage = $request->input('per_page', 2);
        $page = $request->input('page', 1);

        $userId = auth()->id();

        // Get page IDs where current user has sent membership requests (only pending ones)
        $requestedPageIds = UserMembership::where('user_id', $userId)
            ->where('status', 'pending') // Only exclude pending requests
            ->pluck('page_id')
            ->toArray();

        // Get pages excluding the ones user has already sent requests to
        $pages = Page::whereNotIn('id', $requestedPageIds)
            ->paginate($perPage);

        return response()->json($pages);
    }

    public function requestMembership(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'companyId' => 'required|integer|exists:pages,id',
                'companyName' => 'required|string|max:255',
                'jobTitle' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'startDate' => 'required|string|max:255',
                'endDate' => 'nullable|string|max:255',
                'currentlyWorking' => 'boolean',
                'responsibilities' => 'required|string',
            ]);

            $userId = auth()->id();
            $companyId = $request->companyId;

            // Check if user already has a pending or approved request for this company
            $existingRequest = UserMembership::where('user_id', $userId)
                ->where('page_id', $companyId)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => "You already have a {$existingRequest->status} request for this company"
                ], 409);
            }

            // Create membership request
            $membership = UserMembership::create([
                'user_id' => $userId,
                'page_id' => $companyId,
                'company_name' => $request->companyName,
                'job_title' => $request->jobTitle,
                'location' => $request->location,
                'start_date' => $request->startDate,
                'end_date' => $request->currentlyWorking ? null : $request->endDate,
                'currently_working' => $request->currentlyWorking ? 1 : 0,
                'responsibilities' => $request->responsibilities,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Membership request submitted successfully',
                'data' => [
                    'membership_id' => $membership->id,
                    'status' => 'pending',
                    'company_name' => $request->companyName,
                    'page_id' => $companyId
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Additional helper method to get user's membership requests
    public function getUserMemberships(Request $request)
    {
        try {
            $userId = auth()->id();

            // Step 1: Find all pages owned by current user
            $ownedPageIds = Page::where('owner_id', $userId)->pluck('id');

            if ($ownedPageIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No memberships found because user does not own any pages',
                    'data' => []
                ], 200);
            }

            // Step 2: Get memberships with status = pending + user + profile
            $memberships = UserMembership::with([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo,cover_photo,location,dob'
            ])
                ->select([
                    'id',
                    'user_id',
                    'page_id',
                    'company_name',
                    'job_title',
                    'location',
                    'start_date',
                    'end_date',
                    'currently_working',
                    'responsibilities',
                    'status',
                    'created_at',
                    'updated_at'
                ])
                ->whereIn('page_id', $ownedPageIds)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending user memberships retrieved successfully',
                'data' => $memberships
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storecompaniesresponses(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'membership_id' => 'required|exists:user_membership,id',
                'confirmation_letter' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'proof_document' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Get current authenticated user
            $currentUserId = Auth::id();

            if (!$currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Find the page owned by current user
            $userPage = Page::where('owner_id', $currentUserId)->first();

            if (!$userPage) {
                return response()->json([
                    'success' => false,
                    'message' => 'No page found for current user'
                ], 404);
            }

            // Check membership_id
            $userMembership = UserMembership::find($request->membership_id);

            if (!$userMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid membership ID'
                ], 404);
            }

            // Upload confirmation letter
            $confirmationLetterPath = null;
            if ($request->hasFile('confirmation_letter')) {
                $confirmationLetter = $request->file('confirmation_letter');
                $confirmationLetterName = time() . '_confirmation_' . $confirmationLetter->getClientOriginalName();
                $confirmationLetterPath = $confirmationLetter->storeAs('membership_documents', $confirmationLetterName, 'public');
            }

            // Upload proof document
            $proofDocumentPath = null;
            if ($request->hasFile('proof_document')) {
                $proofDocument = $request->file('proof_document');
                $proofDocumentName = time() . '_proof_' . $proofDocument->getClientOriginalName();
                $proofDocumentPath = $proofDocument->storeAs('membership_documents', $proofDocumentName, 'public');
            }

            // Insert record into membership_documents (status field removed)
            $membershipDocument = MembershipDocument::create([
                'membership_id' => $request->membership_id,
                'confirmation_letter' => $confirmationLetterPath,
                'proof_document' => $proofDocumentPath,
                'uploaded_by_company' => $userPage->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update user_membership status to "company_approved"
            $userMembership->status = 'company_approved';
            $userMembership->save();

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully and membership updated to company_approved',
                'data' => $membershipDocument
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method to cancel/remove a pending membership request                  I DONT USE IT
    public function cancelMembershipRequest(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'membership_id' => 'required|integer|exists:user_memberships,id'
            ]);

            $userId = auth()->id();
            $membershipId = $request->membership_id;

            // Check if the membership request belongs to the authenticated user and is pending
            $membership = UserMembership::where('id', $membershipId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membership request not found or cannot be cancelled'
                ], 404);
            }

            // Delete the membership request
            $membership->delete();

            return response()->json([
                'success' => true,
                'message' => 'Membership request cancelled successfully',
                'data' => [
                    'membership_id' => $membershipId,
                    'company_name' => $membership->company_name
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
