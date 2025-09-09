<?php

namespace Database\Seeders;

use App\Models\Survey;
use Illuminate\Database\Seeder;

class SampleSurveySeeder extends Seeder
{
    public function run(): void
    {
        $schema = [
            "title" => "Survei Kepuasan",
            "pages" => [[
                "elements" => [
                    ["type"=>"text", "name"=>"nama", "title"=>"Nama lengkap", "isRequired"=>true],
                    ["type"=>"radiogroup", "name"=>"gender", "title"=>"Jenis kelamin", "choices"=>["Laki-laki","Perempuan"]],
                    ["type"=>"rating", "name"=>"kepuasan", "title"=>"Seberapa puas?", "rateMax"=>5, "minRateDescription"=>"Tidak puas","maxRateDescription"=>"Sangat puas"]
                ]
            ]]
        ];

        Survey::updateOrCreate(
            ['slug' => 'survei-kepuasan'],
            ['title'=>'Survei Kepuasan', 'schema_json'=>$schema, 'status'=>'published', 'version'=>1, 'published_at'=>now()]
        );
    }
}
