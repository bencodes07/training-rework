import ActiveSoloEndorsements from '@/components/endorsements/active-solo-endorsements';
import SoloEndorsementsTable from '@/components/endorsements/solo-endorsements-table';
import Tier1EndorsementsTable from '@/components/endorsements/tier-1-endorsements-table';
import Tier2EndorsementsTable from '@/components/endorsements/tier-2-endorsements-table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { endorsements } from '@/routes';
import { Endorsement, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Radio, Shield, TowerControl } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Endorsements',
        href: endorsements().url,
    },
];

const tier1Endorsements: Endorsement[] = [
    {
        position: 'EDGG_KTG_CTR',
        fullName: 'Sektor Kitzingen',
        activity: 1,
        status: 'removal',
        lastActivity: '2024-10-15',
        type: 'CTR',
    },
    {
        position: 'EDDF_APP',
        fullName: 'Frankfurt Approach',
        activity: 1.5,
        status: 'warning',
        lastActivity: '2024-11-28',
        type: 'APP',
    },
    {
        position: 'EDDF_TWR',
        fullName: 'Frankfurt Tower',
        activity: 32,
        status: 'active',
        lastActivity: '2024-12-10',
        type: 'TWR',
    },
    {
        position: 'EDDF_GNDDEL',
        fullName: 'Frankfurt Ground/Delivery',
        activity: 45,
        status: 'active',
        lastActivity: '2024-12-15',
        type: 'GNDDEL',
    },
    {
        position: 'EDDL_APP',
        fullName: 'Düsseldorf Approach',
        activity: 142,
        status: 'active',
        lastActivity: '2025-09-20',
        type: 'APP',
    },
    {
        position: 'EDDL_TWR',
        fullName: 'Düsseldorf Tower',
        activity: 145,
        status: 'active',
        lastActivity: '2025-09-20',
        type: 'TWR',
    },
    {
        position: 'EDDL_GNDDEL',
        fullName: 'Düsseldorf Ground/Delivery',
        activity: 154,
        status: 'active',
        lastActivity: '2025-09-20',
        type: 'GNDDEL',
    },
];

const tier2Endorsements: Endorsement[] = [
    {
        position: 'EDXX_AFIS',
        fullName: 'AFIS Tower',
        status: 'active',
        lastActivity: '2024-12-12',
        type: 'TWR',
    },
];

const soloEndorsements: Endorsement[] = [
    {
        position: 'EDDH_TWR',
        fullName: 'Hamburg Tower',
        mentor: 'John Doe',
        status: 'active',
        lastActivity: '',
        type: 'TWR',
        expiresAt: '2025-12-01',
    },
];

export function getStatusBadge(status: string) {
    switch (status) {
        case 'active':
            return (
                <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700">
                    Active
                </Badge>
            );
        case 'warning':
            return (
                <Badge variant="outline" className="border-yellow-200 bg-yellow-50 text-yellow-700">
                    Low Activity
                </Badge>
            );
        case 'removal':
            return (
                <Badge variant="outline" className="border-red-200 bg-red-50 text-red-700">
                    In Removal
                </Badge>
            );
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export function getPositionIcon(type: string) {
    switch (type) {
        case 'GNDDEL':
            return <Radio className="h-4 w-4" />;
        case 'TWR':
            return <TowerControl className="h-4 w-4" />;
        case 'APP':
            return <Shield className="h-4 w-4" />;
        case 'CTR':
            return <Shield className="h-4 w-4" />;
        default:
            return <Radio className="h-4 w-4" />;
    }
}

export default function EndorsementsDashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Endorsements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <ActiveSoloEndorsements endorsements={soloEndorsements} />

                <Tabs defaultValue="tier1" className="w-full">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="tier1" className="flex items-center gap-2">
                            <Shield className="h-4 w-4" />
                            Tier 1 ({tier1Endorsements.length})
                        </TabsTrigger>
                        <TabsTrigger value="tier2" className="flex items-center gap-2">
                            <Shield className="h-4 w-4" />
                            Tier 2 ({tier2Endorsements.length})
                        </TabsTrigger>
                        <TabsTrigger value="solo" className="flex items-center gap-2">
                            <CheckCircle className="h-4 w-4" />
                            Solo ({soloEndorsements.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="tier1" className="mt-1 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Tier 1 Endorsements</CardTitle>
                                <CardDescription>
                                    Position specific endorsements requiring regular activity to maintain. Minimum activity thresholds must be met to
                                    avoid removal.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Tier1EndorsementsTable endorsements={tier1Endorsements} />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="tier2" className="mt-1 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Tier 2 Endorsements</CardTitle>
                                <CardDescription>Position independent endorsements</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Tier2EndorsementsTable endorsements={tier2Endorsements} />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="solo" className="mt-1 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Solo Endorsements</CardTitle>
                                <CardDescription>
                                    Temporary endorsements issued by mentors for specific positions. These have expiration dates and are used for
                                    training purposes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SoloEndorsementsTable endorsements={soloEndorsements} />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <Card className="border-primary/20 bg-primary/10 dark:border-primary/40 dark:bg-primary/20">
                    <CardContent>
                        <div className="flex items-start gap-4">
                            <div className="rounded-full bg-primary/10 p-2 dark:bg-primary/20">
                                <AlertCircle className="h-5 w-5 text-primary dark:text-primary" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-blue-900 dark:text-blue-100">Activity Requirements</h3>
                                <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                    Maintain minimum activity hours to keep your endorsements active. Low activity endorsements may be subject to
                                    removal if requirements aren't met.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
