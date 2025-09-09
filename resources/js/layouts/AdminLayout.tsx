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
