<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id','response_uuid','user_id','answers_json','meta_json','submitted_at'
    ];

    protected $casts = [
        'answers_json' => 'array',
        'meta_json' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function survey() {
        return $this->belongsTo(Survey::class);
    }
}
