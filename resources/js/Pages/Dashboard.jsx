import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header="Dashboard"
            breadcrumbs={[
                { label: 'Dashboard', href: route('dashboard') }
            ]}
        >
            <Head title="Dashboard" />

            <div className="bg-white shadow-sm rounded-lg p-6">
                <h2 className="text-xl font-semibold text-gray-800 mb-4">
                    Welcome to your Dashboard
                </h2>
                <p className="text-gray-600">
                    You're logged in! This is your main dashboard where you can manage your application.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
