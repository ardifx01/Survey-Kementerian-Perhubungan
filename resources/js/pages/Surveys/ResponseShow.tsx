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
