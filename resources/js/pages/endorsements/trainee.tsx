import ActiveSoloEndorsements from '@/components/endorsements/active-solo-endorsements';
import SoloEndorsementsTable from '@/components/endorsements/solo-endorsements-table';
import Tier1EndorsementsTable from '@/components/endorsements/tier-1-endorsements-table';
import Tier2EndorsementsTable from '@/components/endorsements/tier-2-endorsements-table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Endorsement, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Radio, Shield, TowerControl } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Endorsements',
        href: route('endorsements'),
    },
];

interface EndorsementsPageProps {
    tier1Endorsements: Endorsement[];
    tier2Endorsements: Endorsement[];
    soloEndorsements: Endorsement[];
    isVatsimUser: boolean;
}

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
        case 'available':
            return (
                <Badge variant="outline" className="border-blue-200 bg-blue-50 text-blue-700">
                    Available
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
    const { tier1Endorsements, tier2Endorsements, soloEndorsements, isVatsimUser } = usePage<EndorsementsPageProps>().props;

    // Filter active solo endorsements
    const activeSolos = soloEndorsements.filter((e) => e.status === 'active');

    if (!isVatsimUser) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Endorsements" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <CardContent className="flex items-center gap-4 pt-6">
                            <AlertCircle className="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                            <div>
                                <h3 className="font-semibold text-yellow-800 dark:text-yellow-200">VATSIM Account Required</h3>
                                <p className="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                    Endorsements are only available to users with VATSIM accounts. Please log in with your VATSIM credentials to view
                                    your endorsements.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Endorsements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Active Solo Endorsements */}
                {activeSolos.length > 0 && <ActiveSoloEndorsements endorsements={activeSolos} />}

                {/* Main Endorsement Tabs */}
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
                                {tier1Endorsements.length > 0 ? (
                                    <Tier1EndorsementsTable endorsements={tier1Endorsements} />
                                ) : (
                                    <div className="py-8 text-center">
                                        <Shield className="mx-auto h-12 w-12 text-muted-foreground" />
                                        <h3 className="mt-2 text-sm font-medium text-foreground">No Tier 1 Endorsements</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            You don't have any Tier 1 endorsements yet. Complete your training to receive endorsements.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="tier2" className="mt-1 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Tier 2 Endorsements</CardTitle>
                                <CardDescription>Position independent endorsements that require Moodle course completion.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {tier2Endorsements.length > 0 ? (
                                    <Tier2EndorsementsTable endorsements={tier2Endorsements} />
                                ) : (
                                    <div className="py-8 text-center">
                                        <Shield className="mx-auto h-12 w-12 text-muted-foreground" />
                                        <h3 className="mt-2 text-sm font-medium text-foreground">No Tier 2 Endorsements Available</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            No Tier 2 endorsements are currently available or you have already obtained all available endorsements.
                                        </p>
                                    </div>
                                )}
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
                                {soloEndorsements.length > 0 ? (
                                    <SoloEndorsementsTable endorsements={soloEndorsements} />
                                ) : (
                                    <div className="py-8 text-center">
                                        <CheckCircle className="mx-auto h-12 w-12 text-muted-foreground" />
                                        <h3 className="mt-2 text-sm font-medium text-foreground">No Solo Endorsements</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            You don't have any active solo endorsements. Your mentor will issue solo endorsements during training.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Activity Requirements Info */}
                <Card className="border-primary/20 bg-primary/10 dark:border-primary/40 dark:bg-primary/20">
                    <CardContent>
                        <div className="flex items-start gap-4">
                            <div className="rounded-full bg-primary/10 p-2 dark:bg-primary/20">
                                <AlertCircle className="h-5 w-5 text-primary dark:text-primary" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-blue-900 dark:text-blue-100">Activity Requirements</h3>
                                <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                    Maintain minimum activity hours to keep your Tier 1 endorsements active. Low activity endorsements may be subject
                                    to removal if requirements aren't met within the grace period.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}