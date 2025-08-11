<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Like;

class PostController extends Controller
{

    public function storetext(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        $post->load('user');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]
        ], 201);
    }
    public function storeimage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'image' => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the post
        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $request->content ?? '',
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        if ($request->type === 'image' && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('posts/images', 'public');

            // Create media record
            Media::create([
                'post_id' => $post->id,
                'file' => $imagePath,
                'type' => 'image',
            ]);
        }


        $post->load('user', 'media');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]
        ], 201);
    }
    public function storevideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:1000',
            'type' => 'required|string|in:text,image,video,poll',
            'visibility' => 'required|string|in:public,private,friends',
            'video' => 'required_if:type,video|file|mimes:mp4,mov,avi,wmv,flv,webm|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the post
        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $request->content ?? '',
            'type' => $request->type,
            'visibility' => $request->visibility,
        ]);

        if ($request->type === 'video' && $request->hasFile('video')) {
            $videoPath = $request->file('video')->store('posts/videos', 'public');

            // Create media record
            Media::create([
                'post_id' => $post->id,
                'file' => $videoPath,
                'type' => 'video',
            ]);
        }

        $post->load('user', 'media');

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Video post created successfully',
            'data' => [
                'post' => $post,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]
        ], 201);
    }

    public function getAllPosts(Request $request)
    {
        $currentUserId = auth()->id();
        $debugMessages = [];

        // Get already fetched IDs from request
        $fetchedPostIds = $request->input('already_fetched_ids', []);
        $debugMessages[] = 'Fetched from request: ' . json_encode($fetchedPostIds);

        // 🆕 NEW: Get authenticated user's recent posts (within last 5 minutes)
        $recentUserPosts = Post::with(['user', 'media', 'poll'])
            ->where('user_id', $currentUserId)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->whereNotIn('id', $fetchedPostIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Original query for other users' posts
        $postsToReturn = Post::with(['user', 'media', 'poll'])
            ->where('user_id', '!=', $currentUserId)
            ->whereNotIn('id', $fetchedPostIds)
            ->inRandomOrder()
            ->take(3)
            ->get();

        // 🆕 NEW: Combine posts - recent user posts first, then others
        $allPosts = $recentUserPosts->concat($postsToReturn);

        $debugMessages[] = 'Recent user posts: ' . $recentUserPosts->count();
        $debugMessages[] = 'Other posts returned: ' . $postsToReturn->count();

        // Prepare response
        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => [],
            'poll_posts' => [],
            'fetched_ids' => [],
            'debug' => $debugMessages
        ];

        // 🆕 Optimized: Get all reactions in ONE query
        $postIds = $allPosts->pluck('id');
        $allReactions = Like::whereIn('post_id', $postIds)
            ->selectRaw('post_id, reaction_type, COUNT(*) as count')
            ->groupBy('post_id', 'reaction_type')
            ->get()
            ->groupBy('post_id');

        $userReactions = Like::whereIn('post_id', $postIds)
            ->where('user_id', $currentUserId)
            ->pluck('reaction_type', 'post_id');

        // Loop through all posts
        foreach ($allPosts as $post) {
            // 🆕 Get reaction counts for this post
            $reactionData = isset($allReactions[$post->id]) ? $allReactions[$post->id] : collect();
            $reactionsCount = $reactionData->pluck('count', 'reaction_type');
            $totalReactions = $reactionsCount->sum();
            $userReaction = $userReactions[$post->id] ?? null;

            $postData = [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'page_id' => $post->page_id,
                'group_id' => $post->group_id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'user' => $post->user,
                'is_current_user' => $post->user_id === $currentUserId,

                // 🆕 Added Reactions Info
                'reactions_count' => $reactionsCount,
                'total_reactions' => $totalReactions,
                'current_user_reaction' => $userReaction
            ];

            $response['fetched_ids'][] = $post->id;

            // Handle poll posts
            if ($post->type === 'poll' && $post->poll) {
                $pollData = $postData;
                $pollData['poll'] = [
                    'id' => $post->poll->id,
                    'question' => $post->poll->question,
                    'options' => json_decode($post->poll->options, true),
                    'created_at' => $post->poll->created_at,
                    'updated_at' => $post->poll->updated_at,
                ];
                $response['poll_posts'][] = $pollData;
            }
            // Handle media posts (image/video)
            elseif (!$post->media->isEmpty()) {
                foreach ($post->media as $media) {
                    $mediaPost = $postData;
                    $mediaPost['media'] = [
                        'id' => $media->id,
                        'type' => $media->type,
                        'file' => $media->file,
                    ];
                    if ($media->type === 'image') {
                        $response['image_posts'][] = $mediaPost;
                    } elseif ($media->type === 'video') {
                        $response['video_posts'][] = $mediaPost;
                    }
                }
            }
            // Handle text posts
            else {
                $response['text_posts'][] = $postData;
            }
        }

        return response()->json($response);
    }

    public function storereaction(Request $request)
    {
        // ✅ Validate request
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'reaction_type' => 'required|in:like,love,haha,wow,sad,angry'
        ]);

        $userId = Auth::id();

        // ✅ Save or update reaction
        $reaction = Like::updateOrCreate(
            [
                'user_id' => $userId,
                'post_id' => $request->post_id
            ],
            [
                'reaction_type' => $request->reaction_type,
                'created_at' => now()
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Reaction saved successfully',
            'data' => $reaction
        ]);
    }

    public function getauthenticatedPosts(Request $request)
    {
        $currentUserId = auth()->id();
        $debugMessages = [];

        // Get already fetched IDs from request
        $fetchedPostIds = $request->input('already_fetched_ids', []);
        $debugMessages[] = 'Fetched from request: ' . json_encode($fetchedPostIds);

        // Fetch only current user's posts
        $postsToReturn = Post::with(['user', 'media'])
            ->where('user_id', $currentUserId) // Only current user's posts
            ->whereNotIn('id', $fetchedPostIds)
            ->inRandomOrder()
            ->take(3)
            ->get();

        $debugMessages[] = 'Posts returned: ' . $postsToReturn->count();

        // Prepare response
        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => [],
            'fetched_ids' => [],
            'debug' => $debugMessages
        ];

        foreach ($postsToReturn as $post) {
            $postData = [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'page_id' => $post->page_id,
                'group_id' => $post->group_id,
                'content' => $post->content,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'user' => $post->user,
            ];

            $response['fetched_ids'][] = $post->id;

            if ($post->media->isEmpty()) {
                $response['text_posts'][] = $postData;
            } else {
                foreach ($post->media as $media) {
                    $mediaPost = $postData;
                    $mediaPost['media'] = [
                        'id' => $media->id,
                        'type' => $media->type,
                        'file' => $media->file,
                    ];

                    if ($media->type === 'image') {
                        $response['image_posts'][] = $mediaPost;
                    } elseif ($media->type === 'video') {
                        $response['video_posts'][] = $mediaPost;
                    }
                }
            }
        }

        return response()->json($response);
    }
}
