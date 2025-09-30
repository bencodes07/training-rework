import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

interface UserProfile {
    vatsim_id: number;
    first_name: string;
    last_name: string;
    email?: string;
    rating: number;
    subdivision?: string;
    last_rating_change?: string;
    is_staff: boolean;
    is_superuser: boolean;
}

interface Course {
    id: number;
    name: string;
    type: string;
    position: string;
}

interface Endorsement {
    position: string;
    activity_hours: number;
    status: string;
    last_activity_date?: string;
}

interface Familiarisation {
    id: number;
    sector_name: string;
    fir: string;
}

interface UserData {
    user: UserProfile;
    active_courses: Course[];
    completed_courses: Course[];
    endorsements: Endorsement[];
    moodle_courses: any[];
    familiarisations: Record<string, Familiarisation[]>;
}

export default function UserProfilePage({ userData }: { userData: UserData }) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Find User', href: '#' },
                { title: `${userData.user.first_name} ${userData.user.last_name}`, href: `/users/${userData.user.vatsim_id}` },
            ]}
        >
            <Head title={`${userData.user.first_name} ${userData.user.last_name} - User Profile`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <h1 className="text-3xl font-bold">Debug User Profile</h1>

                {/* User Data */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">User Information</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.user, null, 2)}
                    </pre>
                </div>

                {/* Active Courses */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">Active Courses ({userData.active_courses.length})</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.active_courses, null, 2)}
                    </pre>
                </div>

                {/* Completed Courses */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">Completed Courses ({userData.completed_courses.length})</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.completed_courses, null, 2)}
                    </pre>
                </div>

                {/* Endorsements */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">Endorsements ({userData.endorsements.length})</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.endorsements, null, 2)}
                    </pre>
                </div>

                {/* Familiarisations */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">Familiarisations</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.familiarisations, null, 2)}
                    </pre>
                </div>

                {/* Moodle Courses */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">Moodle Courses ({userData.moodle_courses.length})</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData.moodle_courses, null, 2)}
                    </pre>
                </div>

                {/* Raw Props Debug */}
                <div className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-2xl font-semibold">All Props (Debug)</h2>
                    <pre className="overflow-auto whitespace-pre-wrap bg-muted p-4 rounded">
                        {JSON.stringify(userData, null, 2)}
                    </pre>
                </div>
            </div>
        </AppLayout>
    );
}