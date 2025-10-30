import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { User, BookOpen, Award, Map, GraduationCap, Shield, TowerControl, Clock, Calendar, AlertCircle, CheckCircle, Radio } from 'lucide-react';
import { cn } from '@/lib/utils';

interface UserProfile {
    vatsim_id: number;
    first_name: string;
    last_name: string;
    email?: string;
    rating: number;
    subdivision?: string;
    last_rating_change?: string;
    is_staff: boolean;
    is_superuser: boolean;
}

interface Course {
    id: number;
    name: string;
    type: string;
    position: string;
    is_mentor: boolean;
    logs: TrainingLog[];
}

interface TrainingLog {
    id: number;
    session_date: string;
    position: string;
    type: string;
    type_display: string;
    result: boolean;
    mentor_name: string;
    session_duration?: number;
}

interface Endorsement {
    position: string;
    activity_hours: number;
    status: string;
    last_activity_date?: string;
}

interface Familiarisation {
    id: number;
    sector_name: string;
    fir: string;
}

interface MoodleCourse {
    id: number;
    name: string;
    passed: boolean;
    link: string;
}

interface UserData {
    user: UserProfile;
    active_courses: Course[];
    completed_courses: Course[];
    endorsements: Endorsement[];
    moodle_courses: MoodleCourse[];
    familiarisations: Record<string, Familiarisation[]>;
}

const getRatingDisplay = (rating: number): string => {
    const ratings: Record<number, string> = {
        0: 'Suspended',
        1: 'Observer (OBS)',
        2: 'Student 1 (S1)',
        3: 'Student 2 (S2)',
        4: 'Student 3 (S3)',
        5: 'Controller 1 (C1)',
        7: 'Controller 3 (C3)',
        8: 'Instructor 1 (I1)',
        10: 'Instructor 3 (I3)',
        11: 'Supervisor (SUP)',
        12: 'Administrator (ADM)',
    };
    return ratings[rating] || 'Unknown';
};

const getTypeColor = (type: string) => {
    switch (type) {
        case 'RTG':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'EDMT':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'FAM':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'GST':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'RST':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getPositionIcon = (position: string) => {
    switch (position) {
        case 'GND':
            return <Radio className="h-4 w-4" />;
        case 'TWR':
            return <TowerControl className="h-4 w-4" />;
        case 'APP':
        case 'CTR':
            return <Shield className="h-4 w-4" />;
        default:
            return <Radio className="h-4 w-4" />;
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'active':
            return (
                <Badge
                    variant="outline"
                    className="border-green-200 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900 dark:text-green-300"
                >
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Active
                </Badge>
            );
        case 'warning':
            return (
                <Badge
                    variant="outline"
                    className="border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
                >
                    <AlertCircle className="mr-1 h-3 w-3" />
                    Low Activity
                </Badge>
            );
        case 'removal':
            return (
                <Badge variant="outline" className="border-red-200 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900 dark:text-red-300">
                    <AlertCircle className="mr-1 h-3 w-3" />
                    Removal Pending
                </Badge>
            );
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
};

export default function UserProfilePage({ userData }: { userData: UserData }) {
    const { user, active_courses, completed_courses, endorsements, moodle_courses, familiarisations } = userData;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Find User',
            href: '#',
        },
        {
            title: `${user.first_name} ${user.last_name}`,
            href: `/users/${user.vatsim_id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${user.first_name} ${user.last_name} - User Profile`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <User className="h-8 w-8 text-primary" />
                                </div>
                                <div>
                                    <CardTitle className="text-2xl">
                                        {user.first_name} {user.last_name}
                                    </CardTitle>
                                    <CardDescription className="mt-1 flex flex-wrap items-center gap-3">VATSIM ID: {user.vatsim_id}</CardDescription>
                                </div>
                            </div>
                            <div className="flex flex-col gap-2">
                                {user.is_superuser && <Badge variant="destructive">Superuser</Badge>}
                                {user.is_staff && <Badge variant="secondary">Staff</Badge>}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="flex items-center gap-3 rounded-lg border p-3">
                                <Award className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Rating</p>
                                    <p className="text-xs text-muted-foreground">{getRatingDisplay(user.rating)}</p>
                                </div>
                            </div>
                            {user.subdivision && (
                                <div className="flex items-center gap-3 rounded-lg border p-3">
                                    <Map className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">Subdivision</p>
                                        <p className="text-xs text-muted-foreground">{user.subdivision}</p>
                                    </div>
                                </div>
                            )}
                            {user.last_rating_change && (
                                <div className="flex items-center gap-3 rounded-lg border p-3">
                                    <Calendar className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">Last Rating Change</p>
                                        <p className="text-xs text-muted-foreground">{new Date(user.last_rating_change).toLocaleDateString('de')}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Main Content Tabs */}
                <Tabs defaultValue="active-courses" className="w-full">
                    <TabsList className="grid w-full grid-cols-5">
                        <TabsTrigger value="active-courses">
                            <BookOpen className="mr-2 h-4 w-4" />
                            Active ({active_courses.length})
                        </TabsTrigger>
                        <TabsTrigger value="completed-courses">
                            <GraduationCap className="mr-2 h-4 w-4" />
                            Completed ({completed_courses.length})
                        </TabsTrigger>
                        <TabsTrigger value="endorsements">
                            <Shield className="mr-2 h-4 w-4" />
                            Endorsements ({endorsements.length})
                        </TabsTrigger>
                        <TabsTrigger value="moodle">
                            <GraduationCap className="mr-2 h-4 w-4" />
                            Moodle ({moodle_courses.length})
                        </TabsTrigger>
                        <TabsTrigger value="familiarisations">
                            <Map className="mr-2 h-4 w-4" />
                            Familiarisations
                        </TabsTrigger>
                    </TabsList>

                    {/* Active Courses Tab */}
                    <TabsContent value="active-courses" className="mt-4 space-y-4">
                        {active_courses.length > 0 ? (
                            <div className="grid gap-4">
                                {active_courses.map((course) => {
                                    return (
                                        <Card key={course.id}>
                                            <CardHeader>
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-3">
                                                        {getPositionIcon(course.position)}
                                                        <div>
                                                            <CardTitle className="text-base">{course.name}</CardTitle>
                                                            <CardDescription className="mt-1 flex flex-wrap gap-2">
                                                                <Badge variant="outline">{course.position}</Badge>
                                                                <Badge className={getTypeColor(course.type)}>{course.type}</Badge>
                                                            </CardDescription>
                                                        </div>
                                                    </div>
                                                    {!course.is_mentor && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            View Only
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent>
                                                {course.is_mentor ? (
                                                    course.logs &&
                                                    course.logs.length > 0 && (
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <h4 className="text-sm font-semibold">Recent Training Logs</h4>
                                                                <a
                                                                    href={`/training-logs/view/${user.vatsim_id}/${course.id}`}
                                                                    className="text-sm text-primary hover:underline"
                                                                >
                                                                    View All →
                                                                </a>
                                                            </div>
                                                            {course.logs.map((log) => (
                                                                <div
                                                                    key={log.id}
                                                                    className={cn(
                                                                        'rounded-lg border p-4 transition-colors hover:bg-muted/50',
                                                                        log.result
                                                                            ? 'border-green-200 bg-green-50/50 dark:border-green-800 dark:bg-green-900/10'
                                                                            : 'border-red-200 bg-red-50/50 dark:border-red-800 dark:bg-red-900/10',
                                                                    )}
                                                                >
                                                                    <div className="flex items-start justify-between">
                                                                        <div className="flex-1">
                                                                            <div className="flex items-center gap-2">
                                                                                <span className="font-medium">{log.position}</span>
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className={cn(
                                                                                        'text-xs',
                                                                                        log.type === 'O' &&
                                                                                            'border-blue-200 bg-blue-50 text-blue-700',
                                                                                        log.type === 'S' &&
                                                                                            'border-purple-200 bg-purple-50 text-purple-700',
                                                                                        log.type === 'L' &&
                                                                                            'border-green-200 bg-green-50 text-green-700',
                                                                                    )}
                                                                                >
                                                                                    {log.type_display}
                                                                                </Badge>
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className={cn(
                                                                                        'text-xs',
                                                                                        log.result
                                                                                            ? 'border-green-200 bg-green-50 text-green-700'
                                                                                            : 'border-red-200 bg-red-50 text-red-700',
                                                                                    )}
                                                                                >
                                                                                    {log.result ? 'Passed' : 'Not Passed'}
                                                                                </Badge>
                                                                            </div>
                                                                            <div className="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                                                                                <span className="flex items-center gap-1">
                                                                                    <Calendar className="h-3 w-3" />
                                                                                    {new Date(log.session_date).toLocaleDateString()}
                                                                                </span>
                                                                                <span className="flex items-center gap-1">
                                                                                    <User className="h-3 w-3" />
                                                                                    {log.mentor_name}
                                                                                </span>
                                                                                {log.session_duration && (
                                                                                    <span className="flex items-center gap-1">
                                                                                        <Clock className="h-3 w-3" />
                                                                                        {log.session_duration} min
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                        <a
                                                                            href={`/training-logs/${log.id}`}
                                                                            className="text-sm text-primary hover:underline"
                                                                        >
                                                                            View Details
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )
                                                ) : (
                                                    <Alert>
                                                        <AlertCircle className="h-4 w-4" />
                                                        <AlertDescription>Training logs are only visible to mentors of this course</AlertDescription>
                                                    </Alert>
                                                )}
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <BookOpen className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">No Active Courses</h3>
                                    <p className="text-sm text-muted-foreground">This user is not currently enrolled in any courses</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Completed Courses Tab */}
                    <TabsContent value="completed-courses" className="mt-4 space-y-4">
                        {completed_courses.length > 0 ? (
                            <div className="grid gap-4">
                                {completed_courses.map((course) => (
                                    <Card key={course.id}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    {getPositionIcon(course.position)}
                                                    <div>
                                                        <CardTitle className="text-base">{course.name}</CardTitle>
                                                        <CardDescription className="mt-1 flex flex-wrap gap-2">
                                                            <Badge variant="outline">{course.position}</Badge>
                                                            <Badge className={getTypeColor(course.type)}>{course.type}</Badge>
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                                <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700">
                                                    <CheckCircle className="mr-1 h-3 w-3" />
                                                    Completed
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                    </Card>
                                ))}
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <GraduationCap className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">No Completed Courses</h3>
                                    <p className="text-sm text-muted-foreground">This user hasn't completed any courses yet</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Endorsements Tab */}
                    <TabsContent value="endorsements" className="mt-4 space-y-4">
                        {endorsements.length > 0 ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Active Endorsements</CardTitle>
                                    <CardDescription>Position-specific endorsements and their activity status</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {endorsements.map((endorsement, idx) => (
                                            <div key={idx} className="flex items-center justify-between rounded-lg border p-4">
                                                <div className="flex items-center gap-3">
                                                    <Shield className="h-5 w-5 text-muted-foreground" />
                                                    <div>
                                                        <p className="font-medium">{endorsement.position}</p>
                                                        <div className="mt-1 flex items-center gap-2">
                                                            <Clock className="h-3 w-3 text-muted-foreground" />
                                                            <span className="text-xs text-muted-foreground">
                                                                {endorsement.activity_hours}h activity
                                                            </span>
                                                            {endorsement.last_activity_date && (
                                                                <>
                                                                    <span className="text-xs text-muted-foreground">•</span>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        Last: {new Date(endorsement.last_activity_date).toLocaleDateString()}
                                                                    </span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                {getStatusBadge(endorsement.status)}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Shield className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">No Active Endorsements</h3>
                                    <p className="text-sm text-muted-foreground">This user doesn't have any active endorsements</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Moodle Courses Tab */}
                    <TabsContent value="moodle" className="mt-4 space-y-4">
                        {moodle_courses.length > 0 ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Moodle Courses</CardTitle>
                                    <CardDescription>Online training courses and completion status</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {moodle_courses.map((course) => (
                                            <div key={course.id} className="flex items-center justify-between rounded-lg border p-4">
                                                <div className="flex items-center gap-3">
                                                    <GraduationCap className="h-5 w-5 text-muted-foreground" />
                                                    <div>
                                                        <p className="font-medium">{course.name}</p>
                                                        <p className="text-xs text-muted-foreground">Course ID: {course.id}</p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    {course.passed ? (
                                                        <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700">
                                                            <CheckCircle className="mr-1 h-3 w-3" />
                                                            Completed
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="border-yellow-200 bg-yellow-50 text-yellow-700">
                                                            <Clock className="mr-1 h-3 w-3" />
                                                            In Progress
                                                        </Badge>
                                                    )}
                                                    <a
                                                        href={course.link}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        View Course →
                                                    </a>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <GraduationCap className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">No Moodle Courses</h3>
                                    <p className="text-sm text-muted-foreground">This user doesn't have any assigned Moodle courses</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Familiarisations Tab */}
                    <TabsContent value="familiarisations" className="mt-4 space-y-4">
                        {Object.keys(familiarisations).length > 0 ? (
                            <div className="space-y-4">
                                {Object.entries(familiarisations).map(([fir, fams]) => (
                                    <Card key={fir}>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Map className="h-5 w-5" />
                                                {fir}
                                            </CardTitle>
                                            <CardDescription>{fams.length} sector(s) familiarised</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="flex flex-wrap gap-2">
                                                {fams.map((fam) => (
                                                    <Badge key={fam.id} variant="outline" className="text-sm">
                                                        {fam.sector_name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Map className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">No Familiarisations</h3>
                                    <p className="text-sm text-muted-foreground">This user hasn't completed any sector familiarisations</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}