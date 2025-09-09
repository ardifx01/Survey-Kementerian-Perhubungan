import { Link, Head } from "@inertiajs/react";
import AdminLayout from "@/layouts/AdminLayout";

declare function route(name: string, params?: unknown): string;

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
