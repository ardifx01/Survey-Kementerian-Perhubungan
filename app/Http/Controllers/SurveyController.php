<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyController extends Controller
{
    public function index() {
        $surveys = Survey::latest()->select('id','title','slug','status','version','published_at','created_at')->get();
        return Inertia::render('Surveys/Index', compact('surveys'));
    }

    public function create() {
        return Inertia::render('Surveys/Edit', ['survey' => null]);
    }

    public function store(Request $r) {
        $data = $r->validate([
            'title' => 'required|string|max:160',
            'slug' => 'required|string|alpha_dash|unique:surveys,slug|max:160',
            'schema_json' => 'required|array',
        ]);
        $data['created_by'] = $r->user()?->id;
        $survey = Survey::create($data);
        return redirect()->route('surveys.edit', $survey);
    }

    public function edit(Survey $survey) {
        return Inertia::render('Surveys/Edit', ['survey' => $survey]);
    }

    public function update(Request $r, Survey $survey) {
        $data = $r->validate([
            'title' => 'required|string|max:160',
            'schema_json' => 'required|array',
            'status' => 'in:draft,published'
        ]);
        $survey->update($data);
        return back()->with('ok','Saved');
    }

    public function publish(Survey $survey) {
        $survey->update([
            'status' => 'published',
            'published_at' => now(),
            'version' => $survey->version + 1
        ]);
        return back()->with('ok','Published');
    }

    public function responses(Survey $survey) {
        $responses = $survey->responses()->latest('submitted_at')->paginate(20);
        return Inertia::render('Surveys/Responses', [
            'survey' => $survey->only(['id','title','slug']),
            'responses' => $responses
        ]);
    }

    public function responseShow(Survey $survey, SurveyResponse $response) {
        abort_unless($response->survey_id === $survey->id, 404);
        return Inertia::render('Surveys/ResponseShow', [
            'survey' => $survey->only(['id','title']),
            'response' => $response
        ]);
    }

    public function exportJson(Survey $survey) {
        $json = $survey->responses()->latest()->get(['response_uuid','answers_json','submitted_at']);
        return response()->json($json);
    }

    public function exportCsv(Survey $survey): StreamedResponse {
        $filename = 'survey_'.$survey->id.'_responses.csv';
        $rows = $survey->responses()->orderBy('submitted_at')->get();
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];

        return response()->stream(function() use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['response_uuid','submitted_at','answers_json']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->response_uuid, $r->submitted_at, json_encode($r->answers_json)]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
