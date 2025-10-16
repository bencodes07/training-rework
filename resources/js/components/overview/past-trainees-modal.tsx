import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { MentorCourse } from '@/types/mentor';
import { router } from '@inertiajs/react';
import { Calendar, CheckCircle2, Loader2, Search, UserPlus, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PastTrainee {
    id: number;
    name: string;
    vatsimId: number;
    initials: string;
    completedAt: string;
    completedBy: string;
    outcome: 'passed' | 'failed' | 'withdrawn';
    totalSessions: number;
    remarks?: string;
}

interface PastTraineesModalProps {
    course: MentorCourse | null;
    isOpen: boolean;
    onClose: () => void;
}

export function PastTraineesModal({ course, isOpen, onClose }: PastTraineesModalProps) {
    const [trainees, setTrainees] = useState<PastTrainee[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [outcomeFilter, setOutcomeFilter] = useState<string>('all');

    // Mock data for now - replace with actual API call
    useEffect(() => {
        if (isOpen && course) {
            setIsLoading(true);
            // Simulate API call
            setTimeout(() => {
                const mockData: PastTrainee[] = [
                    {
                        id: 1,
                        name: 'Max Mustermann',
                        vatsimId: 1234567,
                        initials: 'MM',
                        completedAt: '2025-01-15',
                        completedBy: 'John Mentor',
                        outcome: 'passed',
                        totalSessions: 12,
                        remarks: 'Excellent progress throughout training',
                    },
                    {
                        id: 2,
                        name: 'Anna Schmidt',
                        vatsimId: 2345678,
                        initials: 'AS',
                        completedAt: '2024-12-20',
                        completedBy: 'Jane Instructor',
                        outcome: 'passed',
                        totalSessions: 15,
                    },
                    {
                        id: 3,
                        name: 'Thomas Weber',
                        vatsimId: 3456789,
                        initials: 'TW',
                        completedAt: '2024-11-10',
                        completedBy: 'John Mentor',
                        outcome: 'withdrawn',
                        totalSessions: 5,
                        remarks: 'Trainee requested to pause training',
                    },
                ];
                setTrainees(mockData);
                setIsLoading(false);
            }, 500);
        }
    }, [isOpen, course]);

    const filteredTrainees = useMemo(() => {
        return trainees.filter((trainee) => {
            const matchesSearch =
                !searchTerm ||
                trainee.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                trainee.vatsimId.toString().includes(searchTerm);

            const matchesOutcome = outcomeFilter === 'all' || trainee.outcome === outcomeFilter;

            return matchesSearch && matchesOutcome;
        });
    }, [trainees, searchTerm, outcomeFilter]);

    const getOutcomeBadge = (outcome: PastTrainee['outcome']) => {
        switch (outcome) {
            case 'passed':
                return (
                    <Badge className="border-green-200 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900 dark:text-green-300">
                        <CheckCircle2 className="mr-1 h-3 w-3" />
                        Passed
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge className="border-red-200 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900 dark:text-red-300">
                        <X className="mr-1 h-3 w-3" />
                        Failed
                    </Badge>
                );
            case 'withdrawn':
                return (
                    <Badge variant="outline" className="text-muted-foreground">
                        <X className="mr-1 h-3 w-3" />
                        Withdrawn
                    </Badge>
                );
        }
    };

    const handleReactivate = (traineeId: number) => {
        if (!course) return;

        router.post(route('overview.reactivate-trainee'), {
            trainee_id: traineeId,
            course_id: course.id,
        });
    };

    const handleClose = () => {
        setSearchTerm('');
        setOutcomeFilter('all');
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Past Trainees - {course?.name}</DialogTitle>
                    <DialogDescription>
                        View and manage trainees who have completed or left this course
                    </DialogDescription>
                </DialogHeader>

                {/* Filters */}
                <div className="flex flex-wrap gap-3 py-4">
                    <div className="relative flex-1 min-w-[250px]">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or VATSIM ID..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-10"
                        />
                        {searchTerm && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="absolute right-1 top-1/2 h-7 w-7 -translate-y-1/2 p-0"
                                onClick={() => setSearchTerm('')}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    <Select value={outcomeFilter} onValueChange={setOutcomeFilter}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Filter by outcome" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Outcomes</SelectItem>
                            <SelectItem value="passed">Passed</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                            <SelectItem value="withdrawn">Withdrawn</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Trainees List */}
                <div>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                    ) : filteredTrainees.length > 0 ? (
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Trainee</TableHead>
                                        <TableHead>Completed</TableHead>
                                        <TableHead>Completed By</TableHead>
                                        <TableHead>Sessions</TableHead>
                                        <TableHead>Outcome</TableHead>
                                        <TableHead>Remarks</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredTrainees.map((trainee) => (
                                        <TableRow key={trainee.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 font-medium text-primary">
                                                        {trainee.initials}
                                                    </div>
                                                    <div>
                                                        <div className="font-medium">{trainee.name}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {trainee.vatsimId}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Calendar className="h-4 w-4" />
                                                    {new Date(trainee.completedAt).toLocaleDateString()}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {trainee.completedBy}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{trainee.totalSessions}</Badge>
                                            </TableCell>
                                            <TableCell>{getOutcomeBadge(trainee.outcome)}</TableCell>
                                            <TableCell className="max-w-xs">
                                                {trainee.remarks ? (
                                                    <span className="text-sm text-muted-foreground line-clamp-2">
                                                        {trainee.remarks}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleReactivate(trainee.id)}
                                                >
                                                    <UserPlus className="mr-2 h-4 w-4" />
                                                    Reactivate
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed py-12 text-center">
                            <Search className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">No past trainees found</h3>
                            <p className="text-sm text-muted-foreground">
                                {searchTerm || outcomeFilter !== 'all'
                                    ? 'Try adjusting your filters'
                                    : 'No trainees have completed or left this course yet'}
                            </p>
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between border-t pt-4">
                    <div className="text-sm text-muted-foreground">
                        Showing {filteredTrainees.length} of {trainees.length} past trainees
                    </div>
                    <Button variant="outline" onClick={handleClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}