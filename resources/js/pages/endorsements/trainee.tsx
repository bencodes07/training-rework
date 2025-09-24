import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { endorsements } from '@/routes';
import { Endorsement, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertCircle, Calendar, CheckCircle, Clock, Radio, Shield, TowerControl } from 'lucide-react';

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

function getStatusBadge(status: string) {
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

function getPositionIcon(type: string) {
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

function ActivityProgress({ current, status }: { current: number; status: string }) {
    const percentage = Math.min((current / 3) * 100, 100);

    let progressColor = 'bg-green-500';
    if (status === 'warning') progressColor = 'bg-yellow-500';
    if (status === 'removal') progressColor = 'bg-red-500';

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <div className="max-w-40">
                        <div className="mb-1 flex justify-between text-xs">
                            <span>{current}h</span>
                            <span>of</span>
                            <span>3h</span>
                        </div>
                        <Progress value={percentage} className={`h-2`} colorClass={progressColor} />
                    </div>
                </TooltipTrigger>
                <TooltipContent>
                    <p>
                        {current} of 3 hours in the last 180 days ({percentage.toFixed(1)}%)
                    </p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

function ActiveSoloEndorsements() {
    const activeSolos = soloEndorsements.filter((e) => e.status === 'active');

    if (activeSolos.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <div className="rounded-full bg-primary/10 p-2 dark:bg-primary/20">
                        <CheckCircle className="h-5 w-5 text-primary dark:text-primary" />
                    </div>
                    Active Solo Endorsements
                </CardTitle>
                <CardDescription>
                    You have {activeSolos.length} active solo endorsement{activeSolos.length > 1 ? 's' : ''} from your mentor
                    {activeSolos.length > 1 ? 's' : ''}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid gap-3">
                    {activeSolos.map((endorsement) => (
                        <div key={endorsement.position} className="flex items-center justify-between rounded-lg border p-3">
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-blue-900 dark:text-blue-400">
                                    {getPositionIcon(endorsement.type)}
                                </div>
                                <div>
                                    <div className="font-medium">{endorsement.position}</div>
                                    <div className="text-sm text-muted-foreground">{endorsement.fullName}</div>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-sm font-medium">{endorsement.mentor}</div>
                                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock className="h-3 w-3" />
                                    Expires {new Date(endorsement.expiresAt!).toLocaleDateString()}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function Tier1EndorsementsTable() {
    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Position</TableHead>
                        <TableHead>Activity Progress</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Last Activity</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {tier1Endorsements.map((endorsement) => (
                        <TableRow key={endorsement.position} className="h-18">
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        {getPositionIcon(endorsement.type)}
                                    </div>
                                    <div>
                                        <div className="font-medium">{endorsement.position}</div>
                                        <div className="text-sm text-muted-foreground">{endorsement.fullName}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <ActivityProgress current={endorsement.activity!} status={endorsement.status} />
                            </TableCell>
                            <TableCell>{getStatusBadge(endorsement.status)}</TableCell>
                            <TableCell>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Calendar className="h-4 w-4" />
                                    {new Date(endorsement.lastActivity!).toLocaleDateString()}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function Tier2EndorsementsTable() {
    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Position</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {tier2Endorsements.map((endorsement) => (
                        <TableRow key={endorsement.position}>
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-purple-600">
                                        {getPositionIcon(endorsement.type)}
                                    </div>
                                    <div>
                                        <div className="font-medium">{endorsement.position}</div>
                                        <div className="text-sm text-muted-foreground">{endorsement.fullName}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>{getStatusBadge(endorsement.status)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function SoloEndorsementsTable() {
    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Position</TableHead>
                        <TableHead>Mentor</TableHead>
                        <TableHead>Expires</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {soloEndorsements.map((endorsement) => (
                        <TableRow key={endorsement.position}>
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                                        {getPositionIcon(endorsement.type)}
                                    </div>
                                    <div>
                                        <div className="font-medium">{endorsement.position}</div>
                                        <div className="text-sm text-muted-foreground">{endorsement.fullName}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div>
                                    <div className="font-medium">{endorsement.mentor}</div>
                                    <div className="text-sm text-muted-foreground">ID: 1234567</div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock className="h-4 w-4" />
                                    {new Date(endorsement.expiresAt!).toLocaleDateString()}
                                </div>
                            </TableCell>
                            <TableCell>{getStatusBadge(endorsement.status)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

export default function EndorsementsDashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Endorsements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <ActiveSoloEndorsements />

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
                                <Tier1EndorsementsTable />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="tier2" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Tier 2 Endorsements</CardTitle>
                                <CardDescription>
                                    Advanced position endorsements with higher activity requirements. These positions typically require additional
                                    training and experience.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Tier2EndorsementsTable />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="solo" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Solo Endorsements</CardTitle>
                                <CardDescription>
                                    Temporary endorsements issued by mentors for specific positions. These have expiration dates and are used for
                                    training purposes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SoloEndorsementsTable />
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
