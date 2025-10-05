import ActivityProgress from '@/components/endorsements/activity-progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, Clock, Eye, Search, X } from 'lucide-react';
import { useMemo, useState, useCallback } from 'react';
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

interface EndorsementGroupData {
    position: string;
    position_name: string;
    airport_icao: string;
    position_type: string;
    endorsements: EndorsementData[];
}

interface PageProps {
    endorsementGroups: EndorsementGroupData[];
}

const formatRemovalDate = (removalDate: string | null, removalDays: number) => {
    if (!removalDate) return null;

    const date = new Date(removalDate);
    const now = new Date();
    const isPast = date < now;
    const daysAbs = Math.abs(Math.round(removalDays));

    return {
        date: date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }),
        isPast,
        daysText: isPast ? `${daysAbs} day${daysAbs !== 1 ? 's' : ''} overdue` : `${daysAbs} day${daysAbs !== 1 ? 's' : ''} remaining`,
    };
};

const getEndorsementState = (endorsement: EndorsementData): 'active' | 'low-activity' | 'in-removal' => {
    if (endorsement.removalDate) {
        return 'in-removal';
    }
    if (endorsement.status === 'warning' || endorsement.status === 'removal') {
        return 'low-activity';
    }
    return 'active';
};

export default function ManageEndorsements({ endorsementGroups: initialGroups }: PageProps) {
    const [endorsementGroups, setEndorsementGroups] = useState(initialGroups);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [selectedGroup, setSelectedGroup] = useState<EndorsementGroupData | null>(null);
    const [selectedEndorsement, setSelectedEndorsement] = useState<EndorsementData | null>(null);
    const [isGroupDialogOpen, setIsGroupDialogOpen] = useState(false);
    const [isRemovalDialogOpen, setIsRemovalDialogOpen] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    // Update endorsement in state
    const updateEndorsementInState = useCallback((endorsementId: number, updates: Partial<EndorsementData>) => {
        setEndorsementGroups((prevGroups) =>
            prevGroups.map((group) => ({
                ...group,
                endorsements: group.endorsements.map((endorsement) =>
                    endorsement.endorsementId === endorsementId ? { ...endorsement, ...updates } : endorsement,
                ),
            })),
        );

        // Also update selectedGroup if it's open
        setSelectedGroup((prevGroup) => {
            if (!prevGroup) return null;
            return {
                ...prevGroup,
                endorsements: prevGroup.endorsements.map((endorsement) =>
                    endorsement.endorsementId === endorsementId ? { ...endorsement, ...updates } : endorsement,
                ),
            };
        });
    }, []);

    const getGroupStats = (endorsements: EndorsementData[]) => {
        const total = endorsements.length;
        const lowActivity = endorsements.filter((e) => getEndorsementState(e) === 'low-activity').length;
        const inRemoval = endorsements.filter((e) => getEndorsementState(e) === 'in-removal').length;

        return { total, lowActivity, inRemoval };
    };

    const filteredGroups = useMemo(() => {
        return endorsementGroups.filter((group) => {
            const matchesSearch =
                !searchTerm ||
                group.position.toLowerCase().includes(searchTerm.toLowerCase()) ||
                group.position_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                group.airport_icao.toLowerCase().includes(searchTerm.toLowerCase());

            const stats = getGroupStats(group.endorsements);
            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'low-activity' && stats.lowActivity > 0) ||
                (statusFilter === 'in-removal' && stats.inRemoval > 0);

            return matchesSearch && matchesStatus;
        });
    }, [endorsementGroups, searchTerm, statusFilter]);

    const openGroupDialog = (group: EndorsementGroupData) => {
        setSelectedGroup(group);
        setIsGroupDialogOpen(true);
    };

    const handleRemoveEndorsement = async () => {
        if (!selectedEndorsement || isProcessing) return;

        setIsProcessing(true);

        // Calculate optimistic removal date (31 days from now)
        const removalWarningDays = 31;
        const optimisticRemovalDate = new Date();
        optimisticRemovalDate.setDate(optimisticRemovalDate.getDate() + removalWarningDays);
        const formattedRemovalDate = optimisticRemovalDate.toISOString().split('T')[0];

        // Optimistic update - immediately mark as in removal
        const optimisticUpdates: Partial<EndorsementData> = {
            removalDate: formattedRemovalDate,
            removalDays: removalWarningDays,
            status: 'removal',
        };

        updateEndorsementInState(selectedEndorsement.endorsementId, optimisticUpdates);

        // Close dialog immediately for better UX
        setIsRemovalDialogOpen(false);
        const savedEndorsement = selectedEndorsement;
        setSelectedEndorsement(null);

        try {
            await new Promise<void>((resolve, reject) => {
                router.delete(`/endorsements/tier1/${savedEndorsement.endorsementId}/remove`, {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Endorsement marked for removal', {
                            description: `${savedEndorsement.position} for ${savedEndorsement.userName} will be removed in ${removalWarningDays} days`,
                        });
                        resolve();
                    },
                    onError: (errors) => {
                        // Revert optimistic update on error
                        updateEndorsementInState(savedEndorsement.endorsementId, {
                            removalDate: null,
                            removalDays: 0,
                            status: savedEndorsement.status,
                        });

                        const errorMessage = Object.values(errors).flat()[0] || 'Failed to mark for removal';
                        toast.error(typeof errorMessage === 'string' ? errorMessage : 'Failed to mark for removal');
                        reject(new Error('Failed'));
                    },
                });
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

    const selectedGroupEndorsements = selectedGroup ? selectedGroup.endorsements : [];

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
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="low-activity">Low Activity</TabsTrigger>
                            <TabsTrigger value="in-removal">In Removal</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                {/* Endorsement Group Cards Grid */}
                {filteredGroups.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        {filteredGroups.map((group) => {
                            const stats = getGroupStats(group.endorsements);

                            return (
                                <Card key={group.position} className="transition-all">
                                    <CardHeader>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="mb-2 text-xl leading-tight font-bold">{group.position_name}</CardTitle>
                                            </div>
                                            <div className="flex-shrink-0">
                                                <Badge variant="secondary">{group.position_type}</Badge>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="-mt-4 flex h-full flex-col justify-end space-y-3">
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

                                        {stats.inRemoval > 0 && (
                                            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/20">
                                                <AlertCircle className="h-4 w-4 flex-shrink-0 text-red-600" />
                                                <div className="flex-1 text-sm text-red-800 dark:text-red-200">{stats.inRemoval} in removal</div>
                                            </div>
                                        )}

                                        <Button variant="outline" className="w-full" size="sm" onClick={() => openGroupDialog(group)}>
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
            </div>

            {/* Position Details Modal */}
            <Dialog open={isGroupDialogOpen} onOpenChange={setIsGroupDialogOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-[90vw] lg:max-w-[1000px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">{selectedGroup && selectedGroup.position_name}</DialogTitle>
                        <DialogDescription>
                            {selectedGroup && (
                                <div className="flex items-center gap-2">
                                    <span className="font-mono">{selectedGroup.position}</span>
                                    <Badge variant="outline" className="text-xs">
                                        {selectedGroup.position_type}
                                    </Badge>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedGroupEndorsements.length > 0 ? (
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
                                    {selectedGroupEndorsements.map((endorsement) => {
                                        const removalInfo = formatRemovalDate(endorsement.removalDate, endorsement.removalDays);
                                        const state = getEndorsementState(endorsement);

                                        return (
                                            <TableRow key={endorsement.id} className={cn(state === 'in-removal' && 'bg-red-50 dark:bg-red-950/20')}>
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
                                                        {state === 'in-removal' ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-red-200 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900 dark:text-red-300"
                                                            >
                                                                <AlertTriangle className="mr-1 h-3 w-3" />
                                                                In Removal
                                                            </Badge>
                                                        ) : state === 'low-activity' ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
                                                            >
                                                                Low Activity
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-green-200 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900 dark:text-green-300"
                                                            >
                                                                Active
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
                                                                        disabled={state === 'in-removal' || state === 'active' || isProcessing}
                                                                    >
                                                                        <AlertTriangle className="mr-1 h-4 w-4" />
                                                                        Mark for Removal
                                                                    </Button>
                                                                </div>
                                                            </TooltipTrigger>
                                                            {state === 'in-removal' && (
                                                                <TooltipContent>
                                                                    <p>Already marked for removal</p>
                                                                    {removalInfo && <p className="text-xs">{removalInfo.date}</p>}
                                                                </TooltipContent>
                                                            )}
                                                            {state === 'active' && (
                                                                <TooltipContent>
                                                                    <p>Endorsement is active - cannot mark for removal</p>
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