<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Media;
use App\Models\Comment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

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

        $postsToReturn = Post::with(['user', 'media', 'poll'])
            ->where('user_id', '!=', $currentUserId)
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
            'poll_posts' => [],
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
                'type' => $post->type,
                'visibility' => $post->visibility,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'user' => $post->user,
            ];

            $response['fetched_ids'][] = $post->id;

            // Handle poll posts
            if ($post->type === 'poll' && $post->poll) {
                $pollData = $postData;
                $pollData['poll'] = [
                    'id' => $post->poll->id,
                    'question' => $post->poll->question,
                    'options' => json_decode($post->poll->options, true), // Decode JSON options
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
            // Handle text posts (no media, not poll)
            else {
                $response['text_posts'][] = $postData;
            }
        }

        return response()->json($response);
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

    public function getcomments(Request $request)
    {
        $postId = $request->input('post_id');

        // Root comments (parent_id = null)
        $comments = Comment::where('post_id', $postId)
            ->whereNull('parent_id')
            ->with(['user:id,name,email', 'replies.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function storecomment(Request $request)
    {
        // Get authenticated user ID
        $userId = auth()->id(); // or Auth::id()

        // Validation (no need to validate user_id anymore)
        $request->validate([
            'post_id' => 'required|integer',
            'content' => 'required|string',
            'parent_id' => 'nullable|integer'
        ]);

        // Create new comment
        $comment = Comment::create([
            'post_id'     => $request->post_id,
            'user_id'     => $userId,
            'parent_id'   => $request->parent_id, // null if root comment
            'content'     => $request->content,
            'likes_count' => 0
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data'    => $comment
        ]);
    }

    public function storereply(Request $request)
    {
        // Validation
        $request->validate([
            'post_id'   => 'required|integer',
            'user_id'   => 'required|integer',
            'parent_id' => 'required|integer',
            'content'   => 'required|string'
        ]);

        // Reply create
        $reply = Comment::create([
            'post_id'     => $request->post_id,
            'user_id'     => $request->user_id,
            'parent_id'   => $request->parent_id,
            'content'     => $request->content,
            'likes_count' => 0
        ]);

        // Reply ka user load karke bhej do
        $reply->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'data'    => $reply
        ]);
    }

    public function likeacomment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => 'required|integer|exists:comments,id'
            ]);

            $commentId = $request->comment_id;
            $userId = auth()->id();

            $comment = Comment::find($commentId);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            $likedByUsers = $comment->liked_by_users ? json_decode($comment->liked_by_users, true) : [];

            if (($key = array_search($userId, $likedByUsers)) !== false) {
                unset($likedByUsers[$key]);
                $likedByUsers = array_values($likedByUsers);
                $comment->decrement('likes_count');
                $action = 'unliked';
            } else {
                $likedByUsers[] = $userId;
                $comment->increment('likes_count');
                $action = 'liked';
            }

            $comment->update([
                'liked_by_users' => json_encode($likedByUsers)
            ]);

            return response()->json([
                'success' => true,
                'message' => "Comment {$action} successfully",
                'data' => [
                    'comment_id' => $commentId,
                    'new_likes_count' => $comment->likes_count,
                    'user_liked' => $action === 'liked',
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