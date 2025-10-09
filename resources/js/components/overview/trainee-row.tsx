import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { TableCell, TableRow } from '@/components/ui/table';
import { Trainee } from '@/types/mentor';
import { AlertCircle, Calendar, CheckCircle2, Clock, Eye, FileText, MoreVertical, Plus, UserCheck } from 'lucide-react';

interface TraineeRowProps {
    trainee: Trainee;
    onRemarkClick: (trainee: Trainee) => void;
}

export function TraineeRow({ trainee, onRemarkClick }: TraineeRowProps) {
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
                <button onClick={() => onRemarkClick(trainee)} className="rounded p-1 text-left transition-colors hover:bg-muted/50">
                    {trainee.remark ? (
                        <div>
                            <div className="line-clamp-2 text-sm">{trainee.remark}</div>
                            <div className="mt-1 text-xs text-muted-foreground">Click to edit</div>
                        </div>
                    ) : (
                        <div className="text-sm text-muted-foreground">Click to add remark</div>
                    )}
                </button>
            </TableCell>

            <TableCell>
                {trainee.claimedBy ? (
                    <Badge
                        variant="outline"
                        className={
                            trainee.claimedBy === 'You'
                                ? 'border-blue-200 bg-blue-50 text-blue-700'
                                : 'border-gray-200 bg-gray-50 text-gray-700'
                        }
                    >
                        {trainee.claimedBy === 'You' ? (
                            <>
                                <UserCheck className="mr-1 h-3 w-3" />
                                Claimed by you
                            </>
                        ) : (
                            <>Claimed by {trainee.claimedBy}</>
                        )}
                    </Badge>
                ) : (
                    <Button variant="outline" size="sm">
                        <Eye className="mr-1 h-3 w-3" />
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
                            <DropdownMenuItem className="text-destructive">
                                <AlertCircle className="mr-2 h-4 w-4" />
                                Remove from Course
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </TableCell>
        </TableRow>
    );
}