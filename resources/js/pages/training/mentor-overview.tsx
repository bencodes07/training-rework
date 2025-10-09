import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    Archive,
    Calendar,
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    ListTodo,
    MoreVertical,
    Plane,
    Plus,
    Radio,
    Settings,
    Shield,
    TowerControl,
    TrendingUp,
    UserCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Overview',
        href: route('courses.index'),
    },
];

// Mock data
const mockCourses = [
    {
        id: 1,
        name: 'Düsseldorf Tower',
        position: 'TWR',
        type: 'RTG',
        activeTrainees: 5,
        trainees: [
            {
                id: 1,
                name: 'John Doe',
                vatsimId: '1234567',
                initials: 'JD',
                progress: [true, true, false, false, false],
                lastSession: '2024-12-15',
                nextStep: 'Pattern work with traffic',
                claimedBy: 'You',
                soloStatus: { remaining: 25, used: 5 },
                remark: 'Good progress, needs more traffic management practice',
            },
            {
                id: 2,
                name: 'Jane Smith',
                vatsimId: '7654321',
                initials: 'JS',
                progress: [true, true, true, false, false],
                lastSession: '2024-12-10',
                nextStep: 'Complex traffic scenarios',
                claimedBy: null,
                soloStatus: null,
                remark: '',
            },
            {
                id: 3,
                name: 'Bob Wilson',
                vatsimId: '9876543',
                initials: 'BW',
                progress: [true, false, false, false, false],
                lastSession: '2024-12-18',
                nextStep: 'Basic procedures review',
                claimedBy: 'Sarah Miller',
                soloStatus: { remaining: 15, used: 15 },
                remark: 'Struggling with radio phraseology',
            },
        ],
    },
    {
        id: 2,
        name: 'Frankfurt Approach',
        position: 'APP',
        type: 'RTG',
        activeTrainees: 3,
        trainees: [
            {
                id: 4,
                name: 'Alice Brown',
                vatsimId: '1357924',
                initials: 'AB',
                progress: [true, true, true, true, false],
                lastSession: '2024-12-12',
                nextStep: 'IFR sequencing',
                claimedBy: 'You',
                soloStatus: { remaining: 45, used: 0 },
                remark: 'Excellent performance',
            },
        ],
    },
    {
        id: 3,
        name: 'Paderborn-High',
        position: 'CTR',
        type: 'RTG',
        activeTrainees: 2,
        trainees: [],
    },
    {
        id: 4,
        name: 'Berlin TMA Familiarisation',
        position: 'APP',
        type: 'FAM',
        activeTrainees: 1,
        trainees: [],
    },
];

const statistics = {
    activeTrainees: 21,
    claimedTrainees: 3,
    trainingSessions: 4,
    waitingList: 8,
};

const getPositionIcon = (position: string) => {
    switch (position) {
        case 'GND':
            return <Radio className="h-4 w-4" />;
        case 'TWR':
            return <TowerControl className="h-4 w-4" />;
        case 'APP':
            return <Shield className="h-4 w-4" />;
        case 'CTR':
            return <Plane className="h-4 w-4" />;
        default:
            return <Radio className="h-4 w-4" />;
    }
};

const getPositionColor = (position: string) => {
    switch (position) {
        case 'GND':
            return 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300';
        case 'TWR':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'APP':
            return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'CTR':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getTypeColor = (type: string) => {
    switch (type) {
        case 'RTG':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:border-blue-800';
        case 'EDMT':
            return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950 dark:text-purple-300 dark:border-purple-800';
        case 'FAM':
            return 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-950 dark:text-yellow-300 dark:border-yellow-800';
        case 'GST':
            return 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950 dark:text-green-300 dark:border-green-800';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:border-gray-800';
    }
};

export default function MentorOverview() {
    const [selectedCourse, setSelectedCourse] = useState(mockCourses[0]);
    const [activeCategory, setActiveCategory] = useState('RTG');
    const [selectedTrainee, setSelectedTrainee] = useState(null);
    const [isRemarkDialogOpen, setIsRemarkDialogOpen] = useState(false);
    const [remarkText, setRemarkText] = useState('');

    const filteredCourses = mockCourses.filter((course) => {
        if (activeCategory === 'EDMT_FAM') {
            return course.type === 'EDMT' || course.type === 'FAM';
        }
        return course.type === activeCategory;
    });

    const getCategoryCount = (category: string) => {
        if (category === 'EDMT_FAM') {
            return mockCourses.filter((c) => c.type === 'EDMT' || c.type === 'FAM').length;
        }
        return mockCourses.filter((c) => c.type === category).length;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Courses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Active Trainees</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">21</CardTitle>
                        </CardHeader>
                        <CardFooter className="text-sm">
                            <div className="text-muted-foreground">Active trainees across all of your courses</div>
                        </CardFooter>
                    </Card>
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Claimed Trainees</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">3</CardTitle>
                        </CardHeader>
                        <CardFooter className="flex-col items-start gap-1.5 text-sm">
                            <div className="text-muted-foreground">Amount of total sessions you had together with a mentor</div>
                        </CardFooter>
                    </Card>
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Training Sessions</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">4</CardTitle>
                            <CardAction>
                                <Badge>
                                    <TrendingUp />
                                    +12.5%
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardFooter className="text-sm text-muted-foreground">Training sessions held the last 30 days</CardFooter>
                    </Card>
                </div>

                {/* Course Filter */}
                <Card className="gap-4 py-0 pb-4">
                    <CardHeader className="!gap-0 border-b !p-0">
                        <Tabs value={activeCategory} onValueChange={setActiveCategory}>
                            <TabsList className="w-full justify-start rounded-bl-none">
                                {getCategoryCount('RTG') > 0 && (
                                    <TabsTrigger className="max-w-68" value="RTG">
                                        Ratings ({getCategoryCount('RTG')})
                                    </TabsTrigger>
                                )}
                                {getCategoryCount('EDMT_FAM') > 0 && (
                                    <TabsTrigger className="max-w-68" value="EDMT_FAM">
                                        Endorsements & Familiarisation ({getCategoryCount('EDMT_FAM')})
                                    </TabsTrigger>
                                )}
                                {getCategoryCount('GST') > 0 && (
                                    <TabsTrigger className="max-w-68" value="GST">
                                        Visitor ({getCategoryCount('GST')})
                                    </TabsTrigger>
                                )}
                            </TabsList>
                        </Tabs>
                    </CardHeader>

                    <CardContent className="px-4">
                        <div className="flex flex-wrap gap-2">
                            {filteredCourses.map((course) => (
                                <button
                                    key={course.id}
                                    onClick={() => setSelectedCourse(course)}
                                    className={`inline-flex items-center gap-2 rounded-full border px-2 py-1 text-sm font-medium transition-colors ${
                                        selectedCourse.id === course.id
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background hover:bg-muted'
                                    }`}
                                >
                                    <div className={`rounded-full p-1 ${getPositionColor(course.position)}`}>{getPositionIcon(course.position)}</div>
                                    {course.name}
                                    <Badge variant="secondary" className="ml-1">
                                        {course.activeTrainees}
                                    </Badge>
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Course Content */}
                {selectedCourse && (
                    <Card>
                        <CardHeader className="border-b">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-xl">{selectedCourse.name}</CardTitle>
                                    <CardDescription className="mt-1 flex items-center gap-2">
                                        <Badge variant="outline" className={getPositionColor(selectedCourse.position)}>
                                            {selectedCourse.position}
                                        </Badge>
                                        <Badge variant="outline" className={getTypeColor(selectedCourse.type)}>
                                            {selectedCourse.type === 'RTG'
                                                ? 'Rating'
                                                : selectedCourse.type === 'FAM'
                                                  ? 'Familiarisation'
                                                  : selectedCourse.type}
                                        </Badge>
                                    </CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm">
                                        <Archive className="mr-2 h-4 w-4" />
                                        Past Trainees
                                    </Button>
                                    <Button variant="outline" size="sm">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Manage Mentors
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            {selectedCourse.trainees.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Trainee</TableHead>
                                                <TableHead>Progress</TableHead>
                                                <TableHead>Solo</TableHead>
                                                <TableHead>Next Step</TableHead>
                                                <TableHead>Remark</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right">Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selectedCourse.trainees.map((trainee) => (
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
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-6 bg-green-50 px-2 text-green-700 hover:bg-green-100"
                                                                >
                                                                    <Plus className="h-3 w-3" />
                                                                </Button>
                                                            </div>
                                                            {trainee.lastSession && (
                                                                <div className="text-xs text-muted-foreground">
                                                                    Last: {new Date(trainee.lastSession).toLocaleDateString()}
                                                                </div>
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
                                                        <button
                                                            onClick={() => {
                                                                setSelectedTrainee(trainee);
                                                                setRemarkText(trainee.remark);
                                                                setIsRemarkDialogOpen(true);
                                                            }}
                                                            className="rounded p-1 text-left transition-colors hover:bg-muted/50"
                                                        >
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
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="border-green-200 bg-green-50 text-green-700 hover:bg-green-100"
                                                            >
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
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <Users className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-medium">No trainees yet</h3>
                                    <p className="mb-4 text-sm text-muted-foreground">Add a trainee to this course to get started</p>
                                    <div className="flex items-center gap-2">
                                        <Input placeholder="VATSIM ID" className="w-40" />
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Trainee
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>

                        {selectedCourse.trainees.length > 0 && (
                            <CardFooter className="border-t bg-muted/50">
                                <div className="flex w-full items-center gap-2">
                                    <Input placeholder="VATSIM ID" className="w-40" />
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Trainee
                                    </Button>
                                </div>
                            </CardFooter>
                        )}
                    </Card>
                )}

                {/* Remark Dialog */}
                <Dialog open={isRemarkDialogOpen} onOpenChange={setIsRemarkDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Update Remark - {selectedTrainee?.name}</DialogTitle>
                            <DialogDescription>
                                Add notes about this trainee's availability, performance, or other relevant information.
                            </DialogDescription>
                        </DialogHeader>
                        <Textarea
                            placeholder="Enter remarks about this trainee..."
                            value={remarkText}
                            onChange={(e) => setRemarkText(e.target.value)}
                            rows={4}
                        />
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsRemarkDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button
                                onClick={() => {
                                    // Handle save
                                    setIsRemarkDialogOpen(false);
                                }}
                            >
                                Save
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
