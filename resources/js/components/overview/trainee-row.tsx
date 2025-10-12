import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { TableCell, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Trainee } from '@/types/mentor';
import { router } from '@inertiajs/react';
import { Calendar, CheckCircle2, Clock, Eye, FileText, MoreVertical, Plus, UserCheck, UserMinus, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';

interface TraineeRowProps {
    trainee: Trainee;
    courseId: number;
    onRemarkClick: (trainee: Trainee) => void;
    onClaimClick: (trainee: Trainee) => void;
    onAssignClick: (trainee: Trainee) => void;
}

export function TraineeRow({ trainee, courseId, onRemarkClick, onClaimClick, onAssignClick }: TraineeRowProps) {
    const [isRemoving, setIsRemoving] = useState(false);
    const [isUnclaiming, setIsUnclaiming] = useState(false);

    const handleRemoveTrainee = () => {
        if (!confirm(`Are you sure you want to remove ${trainee.name} from this course?`)) {
            return;
        }

        setIsRemoving(true);
        router.post(
            route('overview.remove-trainee'),
            {
                trainee_id: trainee.id,
                course_id: courseId,
            },
            {
                onFinish: () => setIsRemoving(false),
            },
        );
    };

    const handleUnclaimTrainee = () => {
        setIsUnclaiming(true);
        router.post(
            route('overview.unclaim-trainee'),
            {
                trainee_id: trainee.id,
                course_id: courseId,
            },
            {
                onFinish: () => setIsUnclaiming(false),
            },
        );
    };

    const formatRemarkDate = (dateString: string | null) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diffInDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

        if (diffInDays === 0) return 'Today';
        if (diffInDays === 1) return 'Yesterday';
        if (diffInDays < 7) return `${diffInDays} days ago`;
        if (diffInDays < 30) return `${Math.floor(diffInDays / 7)} weeks ago`;
        if (diffInDays < 365) return `${Math.floor(diffInDays / 30)} months ago`;
        return date.toLocaleDateString();
    };

    return (
        <TableRow key={trainee.id}>
            <TableCell>
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 font-medium text-primary">
                        {trainee.initials}
                    </div>
                    <div>
                        <div className="font-medium">{trainee.name}</div>
                        <div className="text-sm text-muted-foreground">{trainee.vatsimId}</div>
                    </div>
                </div>
            </TableCell>

            <TableCell>
                <div className="space-y-1">
                    {trainee.progress.length > 0 ? (
                        <div className="flex items-center gap-1">
                            {trainee.progress.map((passed, idx) => (
                                <div
                                    key={idx}
                                    className={`h-2 w-2 rounded-full ${passed ? 'bg-green-500' : 'bg-red-500'}`}
                                    title={`Session ${idx + 1}: ${passed ? 'Passed' : 'Failed'}`}
                                />
                            ))}
                            <Button variant="ghost" size="sm" className="ml-1 h-6 px-2">
                                <Eye className="mr-1 h-3 w-3" />
                                Details
                            </Button>
                            <Button variant="ghost" size="sm" className="h-6 bg-green-50 px-2 text-green-700 hover:bg-green-100">
                                <Plus className="h-3 w-3" />
                            </Button>
                        </div>
                    ) : (
                        <div className="flex items-center gap-1">
                            <span className="text-sm text-muted-foreground">No sessions yet</span>
                            <Button variant="ghost" size="sm" className="h-6 bg-green-50 px-2 text-green-700 hover:bg-green-100">
                                <Plus className="h-3 w-3" />
                            </Button>
                        </div>
                    )}
                    {trainee.lastSession && (
                        <div className="text-xs text-muted-foreground">Last: {new Date(trainee.lastSession).toLocaleDateString()}</div>
                    )}
                </div>
            </TableCell>

            <TableCell>
                {trainee.soloStatus ? (
                    <Badge
                        variant="outline"
                        className={
                            trainee.soloStatus.remaining < 10
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : trainee.soloStatus.remaining < 20
                                  ? 'border-yellow-200 bg-yellow-50 text-yellow-700'
                                  : 'border-green-200 bg-green-50 text-green-700'
                        }
                    >
                        <Clock className="mr-1 h-3 w-3" />
                        {trainee.soloStatus.remaining} days
                    </Badge>
                ) : (
                    <Button variant="ghost" size="sm" className="h-7 text-xs">
                        <Plus className="mr-1 h-3 w-3" />
                        Add Solo
                    </Button>
                )}
            </TableCell>

            <TableCell className="max-w-xs">
                <div className="truncate text-sm">{trainee.nextStep || '—'}</div>
            </TableCell>

            <TableCell className="max-w-xs">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                onClick={() => onRemarkClick(trainee)}
                                className="w-full rounded p-1 text-left transition-colors hover:bg-muted/50"
                            >
                                {trainee.remark && trainee.remark.text ? (
                                    <div>
                                        <div className="line-clamp-2 text-sm">{trainee.remark.text}</div>
                                        <div className="mt-1 text-xs text-muted-foreground">Click to edit</div>
                                    </div>
                                ) : (
                                    <div className="text-sm text-muted-foreground">Click to add remark</div>
                                )}
                            </button>
                        </TooltipTrigger>
                        {trainee.remark && trainee.remark.text && trainee.remark.updated_at && (
                            <TooltipContent side="top" className="max-w-xs">
                                <div className="space-y-1">
                                    <div className="font-medium">Last updated</div>
                                    <div className="text-sm">
                                        {formatRemarkDate(trainee.remark.updated_at)}
                                        {trainee.remark.author_name && (
                                            <>
                                                {' by '}
                                                <span className="font-medium">{trainee.remark.author_name}</span>
                                                {trainee.remark.author_initials && (
                                                    <span className="ml-1 text-muted-foreground">({trainee.remark.author_initials})</span>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </TooltipContent>
                        )}
                    </Tooltip>
                </TooltipProvider>
            </TableCell>

            <TableCell>
                {trainee.claimedBy ? (
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className={
                                trainee.claimedBy === 'You' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 bg-gray-50 text-gray-700'
                            }
                        >
                            {trainee.claimedBy === 'You' ? (
                                <>
                                    <UserCheck className="mr-1 h-3 w-3" />
                                    Claimed by you
                                </>
                            ) : (
                                <>
                                    <Users className="mr-1 h-3 w-3" />
                                    Claimed by {trainee.claimedBy}
                                </>
                            )}
                        </Badge>
                        {trainee.claimedBy === 'You' && (
                            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={handleUnclaimTrainee} disabled={isUnclaiming}>
                                <UserMinus className="mr-1 h-3 w-3" />
                                {isUnclaiming ? 'Unclaiming...' : 'Unclaim'}
                            </Button>
                        )}
                    </div>
                ) : (
                    <Button variant="outline" size="sm" onClick={() => onClaimClick(trainee)}>
                        <UserPlus className="mr-1 h-3 w-3" />
                        Claim
                    </Button>
                )}
            </TableCell>

            <TableCell className="text-right">
                <div className="flex items-center justify-end gap-2">
                    <Button size="sm" variant="outline" className="border-green-200 bg-green-50 text-green-700 hover:bg-green-100">
                        <CheckCircle2 className="mr-1 h-3 w-3" />
                        Finish
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                                <FileText className="mr-2 h-4 w-4" />
                                View Profile
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                                <Calendar className="mr-2 h-4 w-4" />
                                Schedule Session
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            {trainee.claimedBy !== 'You' && (
                                <DropdownMenuItem onClick={() => onClaimClick(trainee)}>
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Claim Trainee
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={() => onAssignClick(trainee)}>
                                <Users className="mr-2 h-4 w-4" />
                                Assign to Mentor
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-destructive" onClick={handleRemoveTrainee} disabled={isRemoving}>
                                <UserMinus className="mr-2 h-4 w-4" />
                                {isRemoving ? 'Removing...' : 'Remove from Course'}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </TableCell>
        </TableRow>
    );
}