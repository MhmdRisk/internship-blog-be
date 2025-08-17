<?php

namespace App\Http\Controllers;

use App\Mail\BlogSubmitted;
use App\Models\Blog;
use Illuminate\Http\Request;
use Str;
use Illuminate\Support\Facades\Mail;

class BlogController extends Controller
{
    // just testing the routes
    function hello() {
        return "Hello there";
    }

    function createBlog(Request $request) { // CREATE (POST)
        // check if user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // check if user has author or admin role
        $user = auth()->user();
        if (!$user->hasRole('author') && !$user->hasRole('admin')) {
            return response()->json(['error' => 'Forbidden - Only authors and admins can create blogs'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|max:100|string',
            'author' => 'required|max:100|string',
            'content' => 'required|string',
            'image' => 'nullable|file|mimes:string',
            // 'image' => 'nullable|file|mimes:jpeg,png,jpg',
            // 'imageURL' => 'nullable|string'
        ]);

        $blog = new Blog();
        $blog->title = $validated['title'];
        $blog->author = $validated['author'];
        $blog->content = $validated['content'];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('images', 'public');
            $blog->image = $path;
        }
        /*
        if ($request->hasFile('imageURL')) {
            $blog->imageURL = $validated['imageURL'];
        }
        */

        $blog->save();
        
        Mail::to($blog->author->email)->send(new BlogSubmitted($blog));

        return response()->json([
            'message' => 'Blog created and email sent successfully.',
            'blog' => $blog,
        ], 201);
    }


    function updateBlog(Request $request, $id) { // PATCH
        // check if user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // check if user has edit permission
        $user = auth()->user();
        if (!$user->hasPermission('edit blogs')) {
            return response()->json(['error' => 'You do not have permission to edit blogs'], 403);
        }

        \Log::info('Data received', ['data' => $request->all()]);

        // $id = $request->id;
        $blog = Blog::findOrFail($id);

        // authors can only edit their own blogs, admins can edit any blog
        if (!$user->hasRole('admin') && $blog->author !== $user->email) {
            return response()->json(['error' => 'You can only edit your own blogs'], 403);
        }

        // added the SOMETIMES attribute, as the PATCH method does not necesserily change the entire entry
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

        // transform image path into full url
        $blog->image = $blog->image ? asset('storage/' . $blog->image) : null;
            
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
                'image' => $blog->image ? asset('storage/' . $blog->image) : null,
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
        // check if user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // check if user has delete permission
        $user = auth()->user();
        if (!$user->hasPermission('delete blogs')) {
            return response()->json(['error' => 'You do not have permission to delete blogs'], 403);
        }

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
