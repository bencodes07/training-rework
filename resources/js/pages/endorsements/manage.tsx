import ActivityProgress from '@/components/endorsements/activity-progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, Clock, Eye, Search, Shield, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Endorsements',
        href: route('endorsements'),
    },
    {
        title: 'Manage',
        href: route('endorsements.manage'),
    },
];

interface EndorsementData {
    id: number;
    endorsementId: number;
    position: string;
    vatsimId: number;
    userName: string;
    activity: number;
    activityHours: number;
    status: 'active' | 'warning' | 'removal';
    progress: number;
    removalDate: string | null;
    removalDays: number;
}

interface GroupedEndorsements {
    [position: string]: EndorsementData[];
}

interface PageProps {
    endorsementGroups: GroupedEndorsements;
}

const getPositionDisplayName = (position: string): string => {
    const names: Record<string, string> = {
        EDDF_TWR: 'Frankfurt Tower',
        EDDF_APP: 'Frankfurt Approach',
        EDDF_GNDDEL: 'Frankfurt Ground/Delivery',
        EDDL_TWR: 'Düsseldorf Tower',
        EDDL_APP: 'Düsseldorf Approach',
        EDDL_GNDDEL: 'Düsseldorf Ground/Delivery',
        EDDK_TWR: 'Köln Tower',
        EDDK_APP: 'Köln Approach',
        EDDH_TWR: 'Hamburg Tower',
        EDDH_APP: 'Hamburg Approach',
        EDDH_GNDDEL: 'Hamburg Ground/Delivery',
        EDDM_TWR: 'München Tower',
        EDDM_APP: 'München Approach',
        EDDM_GNDDEL: 'München Ground/Delivery',
        EDDB_APP: 'Berlin Approach',
        EDDB_TWR: 'Berlin Tower',
        EDDB_GNDDEL: 'Berlin Ground/Delivery',
        EDWW_CTR: 'Bremen Big',
        EDGG_KTG_CTR: 'Sektor Kitzingen',
    };
    return names[position] || position;
};

const getPositionType = (position: string): string => {
    if (position.endsWith('_CTR')) return 'CTR';
    if (position.endsWith('_APP')) return 'APP';
    if (position.endsWith('_TWR')) return 'TWR';
    if (position.endsWith('_GNDDEL')) return 'GND/DEL';
    return 'Other';
};

const getAirportCode = (position: string): string => {
    const parts = position.split('_');
    return parts[0];
};

const getTypeColor = (type: string) => {
    switch (type) {
        case 'CTR':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'APP':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'TWR':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 border-green-200 dark:border-green-800';
        case 'GND/DEL':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300 border-orange-200 dark:border-orange-800';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800';
    }
};

const formatRemovalDate = (removalDate: string | null, removalDays: number) => {
    if (!removalDate) return null;

    const date = new Date(removalDate);
    const now = new Date();
    const isPast = date < now;
    const daysAbs = Math.abs(removalDays);

    return {
        date: date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }),
        isPast,
        daysText: isPast ? `${daysAbs} day${daysAbs !== 1 ? 's' : ''} overdue` : `${daysAbs} day${daysAbs !== 1 ? 's' : ''} remaining`,
    };
};

export default function ManageEndorsements({ endorsementGroups }: PageProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [selectedPosition, setSelectedPosition] = useState<string | null>(null);
    const [selectedEndorsement, setSelectedEndorsement] = useState<EndorsementData | null>(null);
    const [isPositionDialogOpen, setIsPositionDialogOpen] = useState(false);
    const [isRemovalDialogOpen, setIsRemovalDialogOpen] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    // Get position statistics
    const getPositionStats = (endorsements: EndorsementData[]) => {
        const total = endorsements.length;
        const lowActivity = endorsements.filter((e) => e.status === 'warning' || e.status === 'removal' || e.removalDate !== null).length;

        return { total, lowActivity };
    };

    // Filter positions
    const filteredPositions = useMemo(() => {
        return Object.entries(endorsementGroups)
            .filter(([position, endorsements]) => {
                const matchesSearch =
                    !searchTerm ||
                    position.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    getPositionDisplayName(position).toLowerCase().includes(searchTerm.toLowerCase()) ||
                    getAirportCode(position).toLowerCase().includes(searchTerm.toLowerCase());

                const stats = getPositionStats(endorsements);
                const matchesStatus =
                    statusFilter === 'all' ||
                    (statusFilter === 'active' && stats.lowActivity === 0) ||
                    (statusFilter === 'warning' && stats.lowActivity > 0);

                return matchesSearch && matchesStatus;
            })
            .sort(([a], [b]) => a.localeCompare(b));
    }, [endorsementGroups, searchTerm, statusFilter]);

    const openPositionDialog = (position: string) => {
        setSelectedPosition(position);
        setIsPositionDialogOpen(true);
    };

    const handleRemoveEndorsement = async () => {
        if (!selectedEndorsement || isProcessing) return;

        setIsProcessing(true);

        try {
            await new Promise<void>((resolve, reject) => {
                router.post(
                    `/endorsements/tier1/${selectedEndorsement.endorsementId}/remove`,
                    {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success('Endorsement marked for removal', {
                                description: `${selectedEndorsement.position} for ${selectedEndorsement.userName}`,
                            });
                            setIsRemovalDialogOpen(false);
                            setSelectedEndorsement(null);
                            resolve();
                        },
                        onError: (errors) => {
                            const errorMessage = Object.values(errors).flat()[0] || 'Failed to mark for removal';
                            toast.error(typeof errorMessage === 'string' ? errorMessage : 'Failed to mark for removal');
                            reject(new Error('Failed'));
                        },
                    },
                );
            });
        } catch (error) {
            console.error('Error removing endorsement:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    const openRemovalDialog = (endorsement: EndorsementData) => {
        setSelectedEndorsement(endorsement);
        setIsRemovalDialogOpen(true);
    };

    const selectedPositionEndorsements = selectedPosition ? endorsementGroups[selectedPosition] : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Endorsements" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[300px] flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search positions or airports..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-10"
                        />
                        {searchTerm && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="absolute top-1/2 right-1 h-7 w-7 -translate-y-1/2 p-0"
                                onClick={() => setSearchTerm('')}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    <Tabs value={statusFilter} onValueChange={setStatusFilter}>
                        <TabsList>
                            <TabsTrigger value="all">All Positions</TabsTrigger>
                            <TabsTrigger value="active">No Issues</TabsTrigger>
                            <TabsTrigger value="warning">Has Issues</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                {/* Position Cards Grid */}
                {filteredPositions.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {filteredPositions.map(([position, endorsements]) => {
                            const stats = getPositionStats(endorsements);

                            return (
                                <Card
                                    key={position}
                                    className={'cursor-pointer transition-all hover:shadow-md'}
                                    onClick={() => openPositionDialog(position)}
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="mb-1 flex items-center gap-2 text-base">
                                                    <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                                        <Shield className="h-4 w-4 text-primary" />
                                                    </div>
                                                    <span className="truncate">{getPositionDisplayName(position)}</span>
                                                </CardTitle>
                                                <CardDescription className="mt-2 flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-xs">{position}</span>
                                                    <Badge variant="outline" className={cn('text-xs', getTypeColor(getPositionType(position)))}>
                                                        {getPositionType(position)}
                                                    </Badge>
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="-mt-4 space-y-3">
                                        {/* Quick Stats */}
                                        <div className="flex items-center justify-between rounded-lg border p-3">
                                            <div className="text-sm text-muted-foreground">Controllers</div>
                                            <div className="text-lg font-semibold">{stats.total}</div>
                                        </div>

                                        {stats.lowActivity > 0 && (
                                            <div className="flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-950/20">
                                                <AlertTriangle className="h-4 w-4 flex-shrink-0 text-yellow-600" />
                                                <div className="flex-1 text-sm text-yellow-800 dark:text-yellow-200">
                                                    {stats.lowActivity} low activity
                                                </div>
                                            </div>
                                        )}

                                        <Button variant="outline" className="w-full" size="sm">
                                            <Eye className="mr-2 h-4 w-4" />
                                            View Details
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Card className="py-12">
                        <CardContent className="text-center">
                            <Search className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">No positions found</h3>
                            <p className="text-muted-foreground">
                                {searchTerm ? 'Try adjusting your search criteria.' : 'No positions match the selected filter.'}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Info Card */}
                <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/20">
                    <CardContent className="flex items-start gap-4 pt-6">
                        <AlertCircle className="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                        <div>
                            <h3 className="font-semibold text-blue-900 dark:text-blue-100">Activity Requirements</h3>
                            <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                Tier 1 endorsements require minimum 180 minutes (3 hours) of activity in the last 180 days. Controllers below this
                                threshold can be marked for removal with a 31-day grace period.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Position Details Modal */}
            <Dialog open={isPositionDialogOpen} onOpenChange={setIsPositionDialogOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-[90vw] lg:max-w-[1000px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5 text-primary" />
                            {selectedPosition && getPositionDisplayName(selectedPosition)}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedPosition && (
                                <div className="flex items-center gap-2">
                                    <span className="font-mono">{selectedPosition}</span>
                                    <Badge variant="outline" className={cn('text-xs', getTypeColor(getPositionType(selectedPosition)))}>
                                        {getPositionType(selectedPosition)}
                                    </Badge>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedPositionEndorsements.length > 0 ? (
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Controller</TableHead>
                                        <TableHead>Activity</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selectedPositionEndorsements.map((endorsement) => {
                                        const removalInfo = formatRemovalDate(endorsement.removalDate, endorsement.removalDays);

                                        return (
                                            <TableRow key={endorsement.id} className={cn(endorsement.removalDate && 'bg-red-50 dark:bg-red-950/20')}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{endorsement.userName}</div>
                                                        <div className="text-sm text-muted-foreground">VATSIM {endorsement.vatsimId}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <ActivityProgress current={endorsement.activity} status={endorsement.status} />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        {endorsement.removalDate ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-red-200 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900 dark:text-red-300"
                                                            >
                                                                <AlertTriangle className="mr-1 h-3 w-3" />
                                                                Removal Pending
                                                            </Badge>
                                                        ) : endorsement.status === 'active' ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-green-200 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900 dark:text-green-300"
                                                            >
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
                                                            >
                                                                Low Activity
                                                            </Badge>
                                                        )}
                                                        {removalInfo && (
                                                            <div
                                                                className={cn(
                                                                    'flex items-center gap-1 text-xs',
                                                                    removalInfo.isPast ? 'text-red-600' : 'text-orange-600',
                                                                )}
                                                            >
                                                                <Clock className="h-3 w-3" />
                                                                <span>{removalInfo.daysText}</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div className="inline-block">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="destructive"
                                                                        onClick={() => openRemovalDialog(endorsement)}
                                                                        disabled={endorsement.removalDate !== null || isProcessing}
                                                                    >
                                                                        <AlertTriangle className="mr-1 h-4 w-4" />
                                                                        Mark for Removal
                                                                    </Button>
                                                                </div>
                                                            </TooltipTrigger>
                                                            {endorsement.removalDate !== null && (
                                                                <TooltipContent>
                                                                    <p>Already marked for removal</p>
                                                                    {removalInfo && <p className="text-xs">{removalInfo.date}</p>}
                                                                </TooltipContent>
                                                            )}
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="py-8 text-center text-muted-foreground">No controllers found for this position</div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Removal Confirmation Dialog */}
            <Dialog open={isRemovalDialogOpen} onOpenChange={setIsRemovalDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark Endorsement for Removal</DialogTitle>
                        <DialogDescription>Are you sure you want to mark this endorsement for removal?</DialogDescription>
                    </DialogHeader>

                    {selectedEndorsement && (
                        <div className="space-y-4 py-4">
                            <div className="rounded-lg border p-4">
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium">Controller:</span>
                                        <span className="text-sm">{selectedEndorsement.userName}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium">VATSIM ID:</span>
                                        <span className="text-sm">{selectedEndorsement.vatsimId}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium">Position:</span>
                                        <span className="text-sm">{selectedEndorsement.position}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium">Current Activity:</span>
                                        <span className="text-sm">
                                            {selectedEndorsement.activityHours}h ({selectedEndorsement.activity}m)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950/20">
                                <p className="text-sm text-yellow-800 dark:text-yellow-200">
                                    This will start the removal process. The controller will be notified and given 31 days to improve their activity
                                    before the endorsement is removed.
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setIsRemovalDialogOpen(false);
                                setSelectedEndorsement(null);
                            }}
                            disabled={isProcessing}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleRemoveEndorsement} disabled={isProcessing}>
                            {isProcessing ? 'Processing...' : 'Mark for Removal'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
