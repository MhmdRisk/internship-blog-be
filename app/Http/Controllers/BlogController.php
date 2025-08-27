<?php

namespace App\Http\Controllers;

use App\Mail\BlogSubmitted;
use App\Models\Blog;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Str;
use Illuminate\Support\Facades\Mail;

class BlogController extends Controller
{
    // just testing the routes
    function hello() {
        return "Hello there";
    }

    function createBlog(Request $request) { // CREATE (POST)
        /*
        // handshake: compare frontend-provided hash to backend key's hash
        $request->validate([
            'hashed_key' => 'required|string'
        ]);

        $backendKey = env('API_HANDSHAKE_KEY');
        $backendHashed = hash('sha256', $backendKey);
        if (!hash_equals($backendHashed, $request->hashed_key)) {
            return response()->json([
                'message' => 'keys are not the same'
            ], 403);
        }
        */
        
        // Log request data for debugging
        Log::info('Blog creation request data:', [
            'has_file' => $request->hasFile('image'),
            'file_valid' => $request->file('image') ? $request->file('image')->isValid() : false,
            'all_data' => $request->all()
        ]);

        $validated = $request->validate([
            'title' => 'required|max:100|string',
            'author' => 'required|max:100|string',
            'content' => 'required|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg',
            // 'imageURL' => 'nullable|string'
        ]);

        $blog = new Blog();
        $blog->title = $validated['title'];
        $blog->author = $validated['author'];
        $blog->content = $validated['content'];

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            try {
                // Configure Cloudinary with direct environment variable access
                $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
                $apiKey = getenv('CLOUDINARY_API_KEY');
                $apiSecret = getenv('CLOUDINARY_API_SECRET');
                
                // Debug: Log the Cloudinary credentials (remove this in production)
                Log::info('Cloudinary Config', [
                    'cloud_name' => $cloudName ? 'set' : 'not set',
                    'api_key' => $apiKey ? 'set' : 'not set',
                    'api_secret' => $apiSecret ? 'set' : 'not set'
                ]);
                
                if (!$cloudName || !$apiKey || !$apiSecret) {
                    throw new \Exception('Cloudinary credentials are not properly configured.');
                }
                
                Configuration::instance([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key' => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                    'url' => [
                        'secure' => true
                    ]
                ]);
                
                // Log before upload
                $file = $request->file('image');
                Log::info('Starting Cloudinary upload', [
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'original_name' => $file->getClientOriginalName()
                ]);
                
                // Upload to Cloudinary
                $uploadResult = (new UploadApi())->upload($request->file('image')->getRealPath(), [
                    'folder' => 'blog_images',
                    'resource_type' => 'auto',
                    'use_filename' => true,
                    'unique_filename' => true,
                    'overwrite' => false
                ]);
                
                // Log successful upload
                Log::info('Cloudinary upload successful', ['result' => $uploadResult]);
                
                // Store the secure URL from Cloudinary
                if (isset($uploadResult['secure_url'])) {
                    // Store only the Cloudinary URL without any prefix
                    $blog->image = $uploadResult['secure_url'];
                    Log::info('Image URL stored:', ['url' => $blog->image]);
                } else {
                    Log::error('No secure_url in Cloudinary response', ['response' => $uploadResult]);
                    throw new \Exception('Failed to get image URL from Cloudinary');
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Log::error('Cloudinary upload failed', [
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->all()
                ]);
                
                // Check for common Cloudinary errors
                if (str_contains($errorMessage, '401 Unauthorized')) {
                    $errorMessage = 'Invalid Cloudinary credentials. Please check your configuration.';
                } elseif (str_contains($errorMessage, 'File is empty')) {
                    $errorMessage = 'The uploaded file is empty or corrupted.';
                }
                
                return response()->json([
                    'error' => 'Failed to upload image',
                    'message' => $errorMessage,
                    'hint' => 'Please check your Cloudinary configuration and try again.'
                ], 500);
            }
        }
        /*
        if ($request->hasFile('imageURL')) {
            $blog->imageURL = $validated['imageURL'];
        }
        */

        $blog->save();
        
        // Mail::to($blog->author->email)->send(new BlogSubmitted($blog));

        return response()->json([
            'message' => 'Blog created and email sent successfully.',
            'blog' => $blog,
            'keys_verification' => $request->attributes->get('keys_message')
        ], 201);
    }


    function updateBlog(Request $request, $id) { // PATCH
        $user = auth()->user();

        \Log::info('Data received', ['data' => $request->all()]);

        // $id = $request->id;
        $blog = Blog::findOrFail($id);

        // authors can only edit their own blogs, admins can edit any blog
        if (!$user->hasRole('admin') && $blog->author !== $user->email) {
            return response()->json(['error' => 'You can only edit your own blogs'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|max:100|string',
            'author' => 'sometimes|required|max:100|string',
            'content' => 'sometimes|required|string',
            'image' => 'sometimes|file|mimes:jpeg,jpg,png'
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('blog_images', 'public');
            $validated['image'] = $imagePath;
        }
        
        $blog->update($validated);

        return response()->json([
            'message' => 'Blog updated successfully.',
            'blog' => $blog->fresh(),
        ]);
    }
    

    function readBlog($id) { /// READ (GET)
        $blog = Blog::find($id);

        if (!$blog) {
            return response()->json([
                'error' => 'This blog does not exist.'
            ], 404);
        }

        // Only transform local storage paths to full URLs, leave Cloudinary URLs as is
        if ($blog->image && !str_starts_with($blog->image, 'http')) {
            $blog->image = asset('storage/' . $blog->image);
        }
            
        return response()->json($blog);
    }


    function readAllBlogs() {
        // pagination (5 blogs per page)
        $blogs = Blog::paginate(6);

        // return description
        $blogs->getCollection()->transform(function ($blog) {
            return [
                'id' => $blog->id,
                'title' => $blog->title,
                'author' => $blog->author,
                'image' => $blog->image && !str_starts_with($blog->image, 'http') ? asset('storage/' . $blog->image) : $blog->image,
                'description' => substr($blog->content, 0, 100), // shorten content
            ];
        });

        return response()->json([
            //'data' => $blogs
            //'total' => $blogs->currentPage(),
            'data' => $blogs->items(),
            'page' => $blogs->currentPage(),
            'total_pages' => $blogs->lastPage(),
            'total_blogs' => $blogs->total(),
            'per_page' => $blogs->perPage(),
        ]);
    }


    function deleteBlog($id) {
        $user = auth()->user();

        //$blog = Blog::find($request->id);
        $blog = Blog::find($id);

        if (!$blog) {
            return response()->json([
                'error' => 'This blog does not exist.'
            ], 404);
        }

        $blog->delete();
        return response()->json(
            ['message' => 'Deleted']
        );
    }
}
