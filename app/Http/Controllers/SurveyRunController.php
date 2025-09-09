<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SurveyRunController extends Controller
{
    public function show($slug) {
        $survey = Survey::where('status','published')->where('slug',$slug)->firstOrFail();
        return Inertia::render('Run/SurveyRun', [
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'slug' => $survey->slug,
                'schema' => $survey->schema_json,
            ]
        ]);
    }

    public function submit(Request $r, $slug) {
        $survey = Survey::where('status','published')->where('slug',$slug)->firstOrFail();
        $data = $r->validate(['answers'=>'required|array','meta'=>'nullable|array']);
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'response_uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'answers_json' => $data['answers'],
            'meta_json' => $data['meta'] ?? [
                'ip'=>$r->ip(),
                'ua'=>$r->userAgent(),
                'referer'=>$r->headers->get('referer'),
            ],
            'submitted_at' => now(),
        ]);
        return redirect()->route('run.show', $survey->slug)->with('ok','Terima kasih! Respon terekam.');
    }
}
