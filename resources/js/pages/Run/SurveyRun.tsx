import { Head, router, usePage } from "@inertiajs/react";
import { Model } from "survey-core";
import { Survey } from "survey-react-ui";

declare function route(name: string, params?: unknown): string;

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
