<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = [
        'title','slug','schema_json','status','version','created_by','published_at'
    ];

    protected $casts = [
        'schema_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function responses() {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopePublished($q) {
        return $q->where('status','published');
    }
}
