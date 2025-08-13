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

    /*
    alternate solution: create a function that does all the validation, 
    and call it inside all the other 3 functions when/if needed
    */

    function createBlog(Request $request) { // CREATE (POST)
        //
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
        // 
        //\Log::info("ID: ", $id);
        //\Log::info();
        \Log::info('Data received', ['data' => $request->all()]);

        //$id = $request->id;
        $blog = Blog::findOrFail($id);

        /*
        // to double check: validation
        if ($request->has('title')) $blog->title = $request->title;
        if ($request->has('author')) $blog->author = $request->author;
        if ($request->has('content')) $blog->content = $request->content;
        */

        // added the SOMETIMES attribute, as the PATCH method does not necesserily change the entire entry
        $validated = $request->validate([
            'title' => 'sometimes|required|max:100|string',
            'author' => 'sometimes|required|max:100|string',
            'content' => 'sometimes|required|string',
            'image' => 'sometimes|file|mimes:jpeg,jpg,png'
        ]);

        //\Log::info($validated['title']);

        // testing if input array is still empty on postman
        /*
        if (empty($validated)) {
            return response()->json([
                'message' => 'No valid fields provided for update.',
                'blog' => $blog
            ], 422);
        }
        */

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('blog_images', 'public');
            $validated['image'] = $imagePath;
        }
        
        //\Log::info($request->all());
        //\Log::info($id);
        //\Log::info($blog);
        //dd($validated);
        //\Log::info($validated['title']);

        $blog->update($validated);
        //$blog->save();

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
