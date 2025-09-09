<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $r) {
        $r->validate(['files.*' => 'file|max:5120']); // 5MB per file
        $urls = [];
        foreach ($r->file('files', []) as $file) {
            $path = $file->store('survey_uploads/'.date('Y/m/d'), 'public');
            $urls[] = Storage::disk('public')->url($path);
        }
        return response()->json(['urls' => $urls]);
    }
}
