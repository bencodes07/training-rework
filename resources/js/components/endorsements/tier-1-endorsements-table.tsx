import { getPositionIcon, getStatusBadge } from '@/pages/endorsements/trainee';
import { Endorsement } from '@/types';
import { Calendar } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';
import ActivityProgress from './activity-progress';

export default function Tier1EndorsementsTable({ endorsements }: { endorsements: Endorsement[] }) {
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
                    {endorsements.map((endorsement) => (
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
                                    {new Date(endorsement.lastActivity!).toLocaleDateString('de')}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
