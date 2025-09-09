import { Link, Head } from "@inertiajs/react";
import AdminLayout from "@/layouts/AdminLayout";

declare function route(name: string, params?: unknown): string;

export default function Dashboard() {
  return (
    <AdminLayout>
      <Head title="Dashboard" />
      <div className="mb-4 flex justify-between">
        <h1 className="text-xl font-semibold">Dashboard</h1>
        <Link href={route('surveys.index')} className="px-3 py-2 rounded bg-blue-600 text-white">Manage Surveys</Link>
      </div>
      <div className="bg-white rounded shadow p-4">
        <p>Welcome to the Survey Dashboard. Use the button above to manage surveys and view responses.</p>
      </div>
    </AdminLayout>
  );
}
