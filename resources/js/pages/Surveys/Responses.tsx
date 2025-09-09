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
