import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { router } from '@inertiajs/react';
import { AlertCircle, Clock, MessageSquare, Play, Users } from 'lucide-react';
import { useState } from 'react';

interface WaitingListEntry {
    id: number;
    name: string;
    vatsim_id: number;
    activity: number;
    waiting_time: string;
    waiting_days: number;
    remarks?: string;
    date_added: string;
}

interface Course {
    id: number;
    name: string;
    type: string;
    type_display: string;
    position: string;
    position_display: string;
    waiting_count: number;
    waiting_list: WaitingListEntry[];
}

interface PageProps {
    courses: Course[];
    statistics: {
        total_waiting: number;
        rtg_waiting: number;
        edmt_waiting: number;
        fam_waiting: number;
        gst_waiting: number;
    };
    config: {
        min_activity: number;
        display_activity: number;
    };
}

export default function MentorWaitingLists({ courses, statistics, config }: PageProps) {
    const [selectedEntry, setSelectedEntry] = useState<WaitingListEntry | null>(null);
    const [remarks, setRemarks] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const handleStartTraining = async (entryId: number) => {
        if (isLoading) return;
        
        setIsLoading(true);
        try {
            await router.post(`/waiting-lists/${entryId}/start-training`, {}, {
                preserveState: true,
                onSuccess: () => {
                    // Refresh the page data
                },
                onError: (errors) => {
                    console.error('Error starting training:', errors);
                },
                onFinish: () => setIsLoading(false),
            });
        } catch (error) {
            console.error('Error:', error);
            setIsLoading(false);
        }
    };

    const handleUpdateRemarks = async () => {
        if (!selectedEntry || isLoading) return;
        
        setIsLoading(true);
        try {
            await router.post('/waiting-lists/update-remarks', {
                entry_id: selectedEntry.id,
                remarks: remarks,
            }, {
                preserveState: true,
                onSuccess: () => {
                    setSelectedEntry(null);
                    setRemarks('');
                },
                onError: (errors) => {
                    console.error('Error updating remarks:', errors);
                },
                onFinish: () => setIsLoading(false),
            });
        } catch (error) {
            console.error('Error:', error);
            setIsLoading(false);
        }
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'RTG': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
            case 'EDMT': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
            case 'FAM': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
            case 'GST': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
            case 'RST': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
            default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
        }
    };

    const groupedCourses = courses.reduce((acc, course) => {
        if (!acc[course.type]) acc[course.type] = [];
        acc[course.type].push(course);
        return acc;
    }, {} as Record<string, Course[]>);

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold mb-2">Waiting List Management</h1>
                    <p className="text-muted-foreground">
                        Manage students waiting to start training across your courses.
                    </p>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Waiting</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.total_waiting}</div>
                            <p className="text-xs text-muted-foreground">Across all courses</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Rating Courses</CardTitle>
                            <Badge className="h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.rtg_waiting}</div>
                            <p className="text-xs text-muted-foreground">Students ready for training</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Endorsements</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.edmt_waiting}</div>
                            <p className="text-xs text-muted-foreground">Endorsement training</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Familiarisations</CardTitle>
                            <AlertCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.fam_waiting}</div>
                            <p className="text-xs text-muted-foreground">Familiarisation requests</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Course Tabs */}
                <Tabs defaultValue="RTG" className="mb-6">
                    <TabsList>
                        <TabsTrigger value="RTG">Rating Courses</TabsTrigger>
                        <TabsTrigger value="EDMT">Endorsements</TabsTrigger>
                        <TabsTrigger value="FAM">Familiarisations</TabsTrigger>
                        <TabsTrigger value="GST">Visitor Courses</TabsTrigger>
                        <TabsTrigger value="RST">Roster Courses</TabsTrigger>
                    </TabsList>

                    {Object.entries(groupedCourses).map(([type, typeCourses]) => (
                        <TabsContent key={type} value={type} className="space-y-6">
                            {typeCourses.map((course) => (
                                <Card key={course.id}>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle className="flex items-center gap-2">
                                                    {course.name}
                                                    <Badge className={getTypeColor(course.type)}>
                                                        {course.type_display}
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {course.position_display}
                                                    </Badge>
                                                </CardTitle>
                                                <CardDescription>
                                                    {course.waiting_count} student{course.waiting_count !== 1 ? 's' : ''} waiting
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    
                                    {course.waiting_list.length > 0 && (
                                        <CardContent>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Student</TableHead>
                                                        <TableHead>VATSIM ID</TableHead>
                                                        <TableHead>Activity (hrs)</TableHead>
                                                        <TableHead>Waiting Time</TableHead>
                                                        <TableHead>Remarks</TableHead>
                                                        <TableHead>Actions</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {course.waiting_list.map((entry) => (
                                                        <TableRow key={entry.id}>
                                                            <TableCell className="font-medium">
                                                                {entry.name}
                                                            </TableCell>
                                                            <TableCell>{entry.vatsim_id}</TableCell>
                                                            <TableCell>
                                                                <span className={
                                                                    entry.activity >= config.min_activity 
                                                                        ? 'text-green-600' 
                                                                        : entry.activity >= config.display_activity 
                                                                            ? 'text-yellow-600' 
                                                                            : 'text-red-600'
                                                                }>
                                                                    {entry.activity}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell>{entry.waiting_time}</TableCell>
                                                            <TableCell>
                                                                {entry.remarks ? (
                                                                    <span className="text-sm text-muted-foreground">
                                                                        {entry.remarks.substring(0, 50)}
                                                                        {entry.remarks.length > 50 && '...'}
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-muted-foreground">No remarks</span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <div className="flex gap-2">
                                                                    <Button
                                                                        size="sm"
                                                                        onClick={() => handleStartTraining(entry.id)}
                                                                        disabled={isLoading || (course.type === 'RTG' && entry.activity < config.display_activity)}
                                                                    >
                                                                        <Play className="h-4 w-4 mr-1" />
                                                                        Start
                                                                    </Button>
                                                                    
                                                                    <Dialog>
                                                                        <DialogTrigger asChild>
                                                                            <Button
                                                                                size="sm"
                                                                                variant="outline"
                                                                                onClick={() => {
                                                                                    setSelectedEntry(entry);
                                                                                    setRemarks(entry.remarks || '');
                                                                                }}
                                                                            >
                                                                                <MessageSquare className="h-4 w-4 mr-1" />
                                                                                Remarks
                                                                            </Button>
                                                                        </DialogTrigger>
                                                                        <DialogContent>
                                                                            <DialogHeader>
                                                                                <DialogTitle>
                                                                                    Update Remarks - {entry.name}
                                                                                </DialogTitle>
                                                                            </DialogHeader>
                                                                            <div className="space-y-4">
                                                                                <Textarea
                                                                                    placeholder="Enter remarks about this student..."
                                                                                    value={remarks}
                                                                                    onChange={(e) => setRemarks(e.target.value)}
                                                                                    rows={4}
                                                                                />
                                                                                <div className="flex justify-end gap-2">
                                                                                    <Button
                                                                                        variant="outline"
                                                                                        onClick={() => {
                                                                                            setSelectedEntry(null);
                                                                                            setRemarks('');
                                                                                        }}
                                                                                    >
                                                                                        Cancel
                                                                                    </Button>
                                                                                    <Button
                                                                                        onClick={handleUpdateRemarks}
                                                                                        disabled={isLoading}
                                                                                    >
                                                                                        {isLoading ? 'Saving...' : 'Save'}
                                                                                    </Button>
                                                                                </div>
                                                                            </div>
                                                                        </DialogContent>
                                                                    </Dialog>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    )}
                                </Card>
                            ))}
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </AppLayout>
    );
}