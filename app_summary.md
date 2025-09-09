
# app_summary.md — Survey App ala Google Forms (Laravel + Inertia + React + TypeScript + SurveyJS)

**Tujuan:** Memberikan instruksi _end‑to‑end_ untuk coding agent membangun aplikasi survei seperti Google Forms menggunakan **Laravel + Inertia + React (TypeScript) + Vite + SurveyJS (`survey-react-ui` + opsional `survey-creator-react`)**.  
Mencakup: struktur berkas, migrasi DB, model, controller, route, halaman React/TSX, upload file, ekspor respons, analitik sederhana, dan catatan keamanan/deploy.

> **Ringkasan arsitektur:**  
> - **Admin**: CRUD survei, editor drag‑and‑drop (Survey Creator), publish/unpublish, lihat & ekspor jawaban, analitik ringkas.  
> - **Publik**: Jalankan survei melalui `/s/{slug}`, simpan jawaban JSON.  
> - **Data utama**: `surveys` (schema JSON), `survey_responses` (answers JSON).  
> - **Stack**: Laravel 11+, Breeze (auth) + Inertia + React + TypeScript + Vite + Tailwind.


---

## 0) Fitur Minimum Viable Product (MVP)

1. **Manajemen Survei (Admin)**
   - Buat, Edit (drag‑and‑drop), Draft/Publish, Versi (auto increment saat publish).
   - Skema disimpan dalam kolom JSON (`schema_json`).  
2. **Runner Survei (Publik)**
   - Render skema dengan `survey-react-ui`, submit jawaban ke backend.  
3. **Manajemen Respons**
   - Daftar respons, detail per respons (JSON viewer), **Export CSV/JSON**.  
4. **Keamanan**
   - Auth untuk admin; survei publik bisa anon. Throttle & CSRF aktif default Laravel.
5. **File Upload (opsional)**
   - Dukungan pertanyaan tipe file via endpoint upload.  
6. **Analitik Sederhana (opsional)**
   - Ringkasan: jumlah respons, rata‑rata rating, distribusi pilihan.


---

## 1) Bootstrap Proyek

```bash
# Laravel 11 contoh
composer create-project laravel/laravel survey-app
cd survey-app

# Auth + Inertia + React (Breeze)
composer require laravel/breeze --dev
php artisan breeze:install react

# TypeScript & Tailwind sudah ikut dengan Breeze React
npm i -D typescript @types/node @types/react @types/react-dom
npx tsc --init

npm i
php artisan migrate
npm run dev
```

**Tambahkan SurveyJS:**
```bash
npm i survey-core survey-react-ui
# opsional editor drag-drop:
npm i survey-creator-core survey-creator-react
```

**Global CSS SurveyJS** (di `resources/js/app.tsx` _atau_ file entry React):
```ts
import "survey-core/defaultV2.min.css";
// builder (opsional):
// import "survey-creator-core/survey-creator-core.min.css";
```


---

## 2) Konfigurasi TypeScript

`tsconfig.json` (aman untuk Vite):
```json
{
  "compilerOptions": {
    "target": "ES2020",
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "jsx": "react-jsx",
    "strict": true,
    "skipLibCheck": true,
    "noEmit": true,
    "baseUrl": ".",
    "paths": { "@/*": ["resources/js/*"] }
  },
  "include": ["resources/**/*", "vite.config.ts"]
}
```

> **Catatan:** Jika memakai helper `route()` di TS, pasang Ziggy atau deklarasi global simple:
> Buat `resources/types/global.d.ts`:
> ```ts
> declare function route(name: string, params?: unknown): string;
> ```


---

## 3) Database — Migrasi

Buat tiga migrasi: `surveys`, `survey_responses`, dan tambahan kolom `is_admin` pada `users`.

**`database/migrations/2025_09_09_000000_create_surveys_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('surveys', function (Blueprint $t) {
      $t->id();
      $t->string('title');
      $t->string('slug')->unique();
      $t->json('schema_json'); // definisi SurveyJS
      $t->enum('status', ['draft','published'])->default('draft');
      $t->unsignedInteger('version')->default(1);
      $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamp('published_at')->nullable();
      $t->timestamps();
      $t->index(['status','published_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('surveys'); }
};
```

**`database/migrations/2025_09_09_000001_create_survey_responses_table.php`**
```php
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
      $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // optional
      $t->json('answers_json');
      $t->json('meta_json')->nullable(); // ip, ua, referer, timestamps
      $t->timestamp('submitted_at')->useCurrent();
      $t->timestamps();
      $t->index(['survey_id','submitted_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('survey_responses'); }
};
```

**`database/migrations/2025_09_09_000002_add_is_admin_to_users.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->boolean('is_admin')->default(false)->after('password');
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropColumn('is_admin');
    });
  }
};
```

Jalankan:
```bash
php artisan migrate
```


---

## 4) Model Eloquent

**`app/Models/Survey.php`**
```php
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
```

**`app/Models/SurveyResponse.php`**
```php
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
```


---

## 5) Policy Sederhana (Akses Admin)

**Gate via middleware**: gunakan `is_admin` untuk akses area admin.

**`app/Http/Middleware/EnsureUserIsAdmin.php`**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
  public function handle(Request $request, Closure $next) {
    if (!$request->user() || !$request->user()->is_admin) {
      abort(403);
    }
    return $next($request);
  }
}
```

**Registrasi middleware** (`app/Http/Kernel.php`):
```php
protected $routeMiddleware = [
  // ...
  'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
];
```


---

## 6) Routes

**`routes/web.php`**
```php
<?php

use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyRunController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('surveys.index'));

Route::middleware(['auth','verified','admin'])->group(function () {
  Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
  Route::get('/surveys/create', [SurveyController::class, 'create'])->name('surveys.create');
  Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
  Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
  Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
  Route::post('/surveys/{survey}/publish', [SurveyController::class, 'publish'])->name('surveys.publish');
  Route::get('/surveys/{survey}/responses', [SurveyController::class, 'responses'])->name('surveys.responses');
  Route::get('/surveys/{survey}/responses/{response}', [SurveyController::class, 'responseShow'])->name('surveys.responses.show');
  Route::get('/surveys/{survey}/export/csv', [SurveyController::class, 'exportCsv'])->name('surveys.export.csv');
  Route::get('/surveys/{survey}/export/json', [SurveyController::class, 'exportJson'])->name('surveys.export.json');

  // upload file dari pertanyaan type "file"
  Route::post('/upload', [UploadController::class,'store'])->name('upload.store');
});

// RUNNER publik
Route::get('/s/{slug}', [SurveyRunController::class, 'show'])->name('run.show');
Route::post('/s/{slug}', [SurveyRunController::class, 'submit'])->name('run.submit');

require __DIR__.'/auth.php';
```


---

## 7) Controller (Backend)

**`app/Http/Controllers/SurveyController.php`**
```php
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
    $data['created_by'] = $r->user()->id;
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
      'status'=>'published',
      'published_at'=>now(),
      'version'=>$survey->version + 1
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
      // header minimal
      fputcsv($out, ['response_uuid','submitted_at','answers_json']);
      foreach ($rows as $r) {
        fputcsv($out, [$r->response_uuid, $r->submitted_at, json_encode($r->answers_json)]);
      }
      fclose($out);
    }, 200, $headers);
  }
}
```

**`app/Http/Controllers/SurveyRunController.php`**
```php
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
    $survey = Survey::published()->where('slug',$slug)->firstOrFail();
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
    $survey = Survey::published()->where('slug',$slug)->firstOrFail();
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
```

**`app/Http/Controllers/UploadController.php` (opsional untuk pertanyaan file)**
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
  public function store(Request $r) {
    $r->validate(['files.*' => 'file|max:5120']); // 5MB/file contoh
    $urls = [];
    foreach ($r->file('files', []) as $file) {
      $path = $file->store('survey_uploads/'.date('Y/m/d'), 'public');
      $urls[] = Storage::disk('public')->url($path);
    }
    return response()->json(['urls' => $urls]);
  }
}
```


---

## 8) Halaman Frontend (Inertia + React + TSX)

### 8.1 Layout dasar admin
**`resources/js/Layouts/AdminLayout.tsx`**
```tsx
import { PropsWithChildren } from "react";
import { Link, usePage } from "@inertiajs/react";

export default function AdminLayout({ children }: PropsWithChildren) {
  const { auth } = usePage<any>().props;
  return (
    <div className="min-h-screen bg-gray-50">
      <header className="border-b bg-white">
        <div className="max-w-6xl mx-auto p-4 flex items-center gap-4">
          <Link href={route('surveys.index')} className="font-semibold">Survey Admin</Link>
          <nav className="ml-auto flex items-center gap-3">
            <span className="text-sm text-gray-600">{auth?.user?.name}</span>
          </nav>
        </div>
      </header>
      <main className="max-w-6xl mx-auto p-6">{children}</main>
    </div>
  );
}
```

### 8.2 Index survei (admin)
**`resources/js/Pages/Surveys/Index.tsx`**
```tsx
import { Link, Head } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";

type SurveyRow = { id:number; title:string; slug:string; status:"draft"|"published"; version:number; published_at?: string; created_at:string; };

export default function Index({ surveys }:{ surveys: SurveyRow[] }) {
  return (
    <AdminLayout>
      <Head title="Surveys" />
      <div className="mb-4 flex justify-between">
        <h1 className="text-xl font-semibold">Surveys</h1>
        <Link href={route('surveys.create')} className="px-3 py-2 rounded bg-blue-600 text-white">Buat Survei</Link>
      </div>
      <div className="bg-white rounded shadow">
        <table className="w-full text-sm">
          <thead className="bg-gray-100">
            <tr>
              <th className="p-2 text-left">Judul</th>
              <th className="p-2">Status</th>
              <th className="p-2">Version</th>
              <th className="p-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {surveys.map(s => (
              <tr key={s.id} className="border-t">
                <td className="p-2">{s.title}<div className="text-xs text-gray-500">/{s.slug}</div></td>
                <td className="p-2 text-center">{s.status}</td>
                <td className="p-2 text-center">{s.version}</td>
                <td className="p-2 text-right space-x-2">
                  <Link href={route('surveys.edit', s.id)} className="text-blue-600">Edit</Link>
                  <Link href={route('surveys.responses', s.id)} className="text-green-600">Responses</Link>
                  {s.status === 'published' && (
                    <a href={route('run.show', s.slug)} target="_blank" className="text-gray-700 underline">Open</a>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
```

### 8.3 Editor drag‑and‑drop (opsional builder)
**`resources/js/Pages/Surveys/Edit.tsx`**
```tsx
import { Head, router } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { useMemo } from "react";
import { SurveyCreatorComponent, SurveyCreator } from "survey-creator-react";

type SurveyDTO = { id:number; title:string; schema_json:any } | null;

export default function Edit({ survey }: { survey: SurveyDTO }) {
  const creator = useMemo<SurveyCreator>(() => {
    const c = new SurveyCreator({
      showLogicTab: true,
      isAutoSave: false,
      showThemeTab: true
    });
    if (survey?.schema_json) c.JSON = survey.schema_json;
    return c;
  }, [survey?.id]);

  const onSave = () => {
    const payload = {
      title: (creator.JSON?.title as string) || survey?.title || "Untitled",
      schema_json: creator.JSON
    };
    if (survey) {
      router.put(route('surveys.update', survey.id), payload);
    } else {
      router.post(route('surveys.store'), { ...payload, slug: slugify(payload.title) });
    }
  };

  const onPublish = () => {
    if (!survey) return;
    router.post(route('surveys.publish', survey.id));
  };

  return (
    <AdminLayout>
      <Head title={survey ? `Edit: ${survey.title}` : "Buat Survei"} />
      <div className="mb-4 flex gap-2">
        <button onClick={onSave} className="px-4 py-2 rounded bg-blue-600 text-white">Simpan</button>
        {survey && <button onClick={onPublish} className="px-4 py-2 rounded bg-green-600 text-white">Publish</button>}
      </div>
      <SurveyCreatorComponent creator={creator} />
    </AdminLayout>
  );
}

function slugify(s: string) {
  return s.toLowerCase().trim().replace(/[^\w\- ]+/g, "").replace(/\s+/g, "-").slice(0, 64);
}
```

### 8.4 Runner publik
**`resources/js/Pages/Run/SurveyRun.tsx`**
```tsx
import { Head, router, usePage } from "@inertiajs/react";
import { Model } from "survey-core";
import { Survey } from "survey-react-ui";

type PageProps = {
  survey: { id:number; title:string; slug:string; schema:any };
  flash?: { ok?: string };
};

export default function SurveyRun() {
  const { survey, flash } = usePage<PageProps>().props as any;

  const model = new Model({
    title: survey.title,
    ...survey.schema
  });
  model.locale = "id";

  model.onUploadFiles.add(async (_sender, opt) => {
    const form = new FormData();
    for (const file of opt.files) form.append("files[]", file);
    const res = await fetch(route('upload.store'), { method: "POST", body: form, headers: { "X-Requested-With":"XMLHttpRequest" } });
    const data: { urls: string[] } = await res.json();
    opt.callback("success", data.urls.map(url => ({ file: { name: url, content: url } })));
  });

  const onComplete = (s: Model) => {
    router.post(route("run.submit", survey.slug), {
      answers: s.data,
      meta: { finishedAt: new Date().toISOString() }
    });
  };

  return (
    <div className="max-w-3xl mx-auto p-6">
      <Head title={survey.title} />
      {flash?.ok && <div className="mb-4 rounded bg-green-100 p-3">{flash.ok}</div>}
      <Survey model={model} onComplete={onComplete} />
    </div>
  );
}
```

### 8.5 Daftar & Detail Responses
**`resources/js/Pages/Surveys/Responses.tsx`**
```tsx
import AdminLayout from "@/Layouts/AdminLayout";
import { Head, Link } from "@inertiajs/react";

export default function Responses({ survey, responses }: any) {
  return (
    <AdminLayout>
      <Head title={`Responses — ${survey.title}`} />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Responses — {survey.title}</h1>
        <div className="space-x-3">
          <a href={route('surveys.export.csv', survey.id)} className="px-3 py-2 rounded bg-gray-800 text-white">Export CSV</a>
          <a href={route('surveys.export.json', survey.id)} className="px-3 py-2 rounded bg-gray-700 text-white">Export JSON</a>
        </div>
      </div>
      <div className="bg-white rounded shadow">
        <table className="w-full text-sm">
          <thead className="bg-gray-100">
            <tr>
              <th className="p-2 text-left">UUID</th>
              <th className="p-2">Submitted At</th>
              <th className="p-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {responses.data.map((r:any) => (
              <tr key={r.id} className="border-t">
                <td className="p-2">{r.response_uuid}</td>
                <td className="p-2 text-center">{r.submitted_at}</td>
                <td className="p-2 text-right">
                  <Link href={route('surveys.responses.show',[survey.id, r.id])} className="text-blue-600">Detail</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
```

**`resources/js/Pages/Surveys/ResponseShow.tsx`**
```tsx
import AdminLayout from "@/Layouts/AdminLayout";
import { Head } from "@inertiajs/react";

export default function ResponseShow({ survey, response }: any) {
  return (
    <AdminLayout>
      <Head title={`Response — ${survey.title}`} />
      <h1 className="text-xl font-semibold mb-4">Response Detail</h1>
      <div className="bg-white rounded shadow p-4">
        <div className="text-sm text-gray-600 mb-2">UUID: {response.response_uuid}</div>
        <div className="text-sm text-gray-600 mb-4">Submitted: {response.submitted_at}</div>
        <pre className="text-xs bg-gray-50 p-3 rounded overflow-auto">
{JSON.stringify(response.answers_json, null, 2)}
        </pre>
      </div>
    </AdminLayout>
  );
}
```


---

## 9) Komponen Styling

- Gunakan **Tailwind** bawaan Breeze.  
- Untuk SurveyJS, pakai tema `defaultV2.min.css`. Jika ingin mengubah warna, gunakan **Theme Editor** SurveyJS atau override CSS spesifik.


---

## 10) Seeder Admin & Sample Survey

**`database/seeders/AdminUserSeeder.php`**
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
  public function run(): void
  {
    User::updateOrCreate(
      ['email' => 'admin@example.com'],
      ['name' => 'Admin', 'password' => Hash::make('password'), 'is_admin' => true]
    );
  }
}
```

**`database/seeders/SampleSurveySeeder.php`**
```php
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
```

Jalankan:
```bash
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=SampleSurveySeeder
```


---

## 11) Keamanan & Reliabilitas

- **Auth & Admin**: Area admin diproteksi middleware `auth, verified, admin`.
- **Publik**: Submit respons tanpa login diizinkan. Pertimbangkan **rate limiting**: tambahkan `->middleware('throttle:20,1')` pada route `run.submit` jika perlu.
- **CSRF**: Laravel aktif default (Inertia/POST aman).
- **reCAPTCHA (opsional)**: Tambahkan di halaman publik untuk mencegah spam.
- **Privacy**: Simpan IP/UA di `meta_json` jika kebijakan mengizinkan; atau matikan jika tidak diperlukan.
- **File upload**: Validasi tipe/ukuran; gunakan storage `public` atau S3.  
- **Versi skema**: Saat publish menaikkan `version`; link publik selalu merender skema terkini yang berstatus `published`.

Contoh throttle pada route submit:
```php
Route::post('/s/{slug}', [SurveyRunController::class, 'submit'])
  ->name('run.submit')
  ->middleware('throttle:20,1');
```


---

## 12) Analitik Sederhana (Opsional)

Buat method/statistik sederhana di controller atau service untuk agregasi jawaban, mis. rata‑rata `rating` dan distribusi `radiogroup`.  
Contoh _server-side_ ringan (pseudo, sesuaikan key):  
- Ambil semua `answers_json`, mapping ke kolom agregat.  
- Simpan hasil di cache (Redis) untuk mempercepat dashboard.


---

## 13) Pengujian (Pest/Feature)

Buat beberapa uji:
1. **Publik render & submit**  
   - GET `/s/{slug}` (200).  
   - POST `/s/{slug}` dengan payload minimal → 302 redirect & row `survey_responses` bertambah.  
2. **Admin CRUD**  
   - Hanya admin bisa akses `/surveys/*`.  
   - Store/update valid, publish menaikkan `version`.  
3. **Export**  
   - Admin dapat mengunduh CSV/JSON.


---

## 14) Build & Deploy

```bash
# production build
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link  # pastikan public storage untuk upload file
```

**Nginx (ringkas):**
- Root ke `public/`
- Arahkan seluruh request ke `index.php` kecuali `/storage` (static).

**ENV Penting:**
```
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://domainmu
SESSION_DRIVER=cookie
FILESYSTEM_DISK=public
```

**Queue** (opsional jika kirim email notifikasi): pilih `database`/Redis, jalankan `php artisan queue:work`.


---

## 15) Backlog (Fitur Lanjutan)

- Link publik dengan token pendek non‑tebak (mis. `slug + nanoid`), _per-survey access control_.
- Logic lanjut di SurveyJS (kondisional, kalkulasi skor).
- **Themes** builder untuk brand perusahaan.
- **Import/Export skema** antar environment.
- **Webhooks** saat respons baru masuk.
- **Report PDF** per respons.
- **Per‑question stats** (chart di admin).


---

## 16) Checklist Implementasi (urut eksekusi)

1. Bootstrap Laravel + Breeze React + TS + Vite.  
2. Tambah paket SurveyJS (runner + opsional builder).  
3. Tambah migrasi `surveys`, `survey_responses`, `is_admin`. Migrasi DB.  
4. Tambah Model `Survey`, `SurveyResponse`.  
5. Tambah middleware `EnsureUserIsAdmin`, registrasikan.  
6. Definisikan **routes** admin & publik; tambahkan throttle untuk submit.  
7. Implement **controllers**: `SurveyController`, `SurveyRunController`, `UploadController`.  
8. Buat **Layouts & Pages** TSX: `AdminLayout`, `Surveys/Index`, `Surveys/Edit`, `Surveys/Responses`, `Surveys/ResponseShow`, `Run/SurveyRun`.  
9. Import CSS SurveyJS global; set `locale = "id"`.  
10. Tambah **onUploadFiles** handler (jika pakai pertanyaan file).  
11. Seed admin + contoh survei.  
12. Uji alur end‑to‑end (buat → publish → buka `/s/{slug}` → submit → cek responses → export).  
13. Build & deploy.


---

## 17) Contoh `schema_json` (disimpan di DB)

```json
{
  "title": "Survei Kepuasan",
  "pages": [
    {
      "elements": [
        { "type": "text", "name": "nama", "title": "Nama lengkap", "isRequired": true },
        { "type": "radiogroup", "name": "gender", "title": "Jenis kelamin", "choices": ["Laki-laki", "Perempuan"] },
        { "type": "rating", "name": "kepuasan", "title": "Seberapa puas?", "rateMax": 5, "minRateDescription": "Tidak puas", "maxRateDescription": "Sangat puas" }
      ]
    }
  ]
}
```

Selesai. Dokumen ini dapat langsung diikuti oleh coding agent untuk membangun aplikasi survei menyerupai Google Forms dengan dashboard admin pengelolaan survei.
