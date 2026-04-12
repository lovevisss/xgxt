<?php

namespace App\Http\Controllers;

use App\Models\Snippet;
use Illuminate\Http\Request;

class SnippetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $snippets = Snippet::latest()->get();
        return view('snippets.index', compact('snippets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Snippet $snippet)
    {

        return view('snippets.create', compact('snippet'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
//        $this->validate($request, [
//            'title' => 'required|string|max:255',
//            'body' => 'required|string',
//        ]);
        Snippet::create([
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'forked_id' => $request->input('forked_id'),
        ]);

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Snippet $snippet)
    {
        return view('snippets.show', compact('snippet'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function fork(Snippet $snippet)
    {

    }
}
