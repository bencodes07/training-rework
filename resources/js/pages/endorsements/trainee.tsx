import EndorsementTable from '@/components/endorsements/endorsement-table';
import AppLayout from '@/layouts/app-layout';
import { endorsements } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Endorsements',
        href: endorsements().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Endorsements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <EndorsementTable data={[
                {
                    "id": 1,
                    "position": "EDGG_KTG_CTR",
                    activity: 16,
                    status: false
                },
                {
                    "id": 2,
                    "position": "EDDF_APP",
                    activity: 20,
                    status: true
                },
                {
                    "id": 3,
                    "position": "EDDF_TWR",
                    activity: 20,
                    status: true
                },
                {
                    "id": 4,
                    "position": "EDDF_GNDDEL",
                    activity: 20,
                    status: false
                },
            ]} />
            </div>
        </AppLayout>
    );
}
