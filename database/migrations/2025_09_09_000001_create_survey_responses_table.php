<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('survey_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $t->uuid('response_uuid')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->json('answers_json');
            $t->json('meta_json')->nullable();
            $t->timestamp('submitted_at')->useCurrent();
            $t->timestamps();
            $t->index(['survey_id','submitted_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('survey_responses'); }
};
