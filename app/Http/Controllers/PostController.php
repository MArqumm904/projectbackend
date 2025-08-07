<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Media;
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
        // Get or initialize the array of already fetched post IDs from session
        $fetchedPostIds = Session::get('fetched_post_ids', []);

        // Get all posts that haven't been fetched yet
        $query = Post::with(['user', 'media'])
            ->whereNotIn('id', $fetchedPostIds);

        // Execute the query and shuffle the results
        $posts = $query->get()->shuffle();

        // Take only 3 posts
        $postsToReturn = $posts->take(3);

        // Update the session with newly fetched post IDs
        $newFetchedIds = $postsToReturn->pluck('id')->toArray();
        Session::put('fetched_post_ids', array_merge($fetchedPostIds, $newFetchedIds));

        // Categorize posts by type
        $response = [
            'text_posts' => [],
            'image_posts' => [],
            'video_posts' => []
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

            if ($post->media->isEmpty()) {
                // Text post
                $response['text_posts'][] = $postData;
            } else {
                foreach ($post->media as $media) {
                    $mediaPost = $postData;
                    $mediaPost['media'] = [
                        'id' => $media->id,
                        'type' => $media->type,
                        'file' => $media->file
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
