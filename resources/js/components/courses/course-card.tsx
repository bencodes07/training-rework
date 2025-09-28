import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Course } from '@/pages/training/courses';
import { Clock, MapPin, AlertCircle, CheckCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import WaitingListButton from './waiting-list-button';

interface CourseCardProps {
    course: Course;
    onCourseUpdate?: (courseId: number, updates: Partial<Course>) => void;
    userHasActiveRtgCourse?: boolean;
}

const getTypeColor = (type: string) => {
    switch (type) {
        case 'RTG':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'EDMT':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'FAM':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800';
        case 'GST':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 border-green-200 dark:border-green-800';
        case 'RST':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 border-red-200 dark:border-red-800';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800';
    }
};

const getStatusColor = (course: Course) => {
    if (course.is_on_waiting_list) {
        return 'text-blue-600 dark:text-blue-400';
    }
    if (course.can_join) {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-muted-foreground';
};

export default function CourseCard({ course: initialCourse, onCourseUpdate, userHasActiveRtgCourse = false }: CourseCardProps) {
    const [course, setCourse] = useState(initialCourse);

    // Update local state when props change (for page refreshes)
    useEffect(() => {
        setCourse(initialCourse);
    }, [initialCourse]);

    // Handle course updates from the button component
    const handleCourseUpdate = (courseId: number, updates: Partial<Course>) => {
        setCourse((prev) => ({ ...prev, ...updates }));
        onCourseUpdate?.(courseId, updates);
    };

    return (
        <Card className="group h-full">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div className="flex-1">
                        <CardTitle className="mb-2 text-xl leading-tight font-bold">{course.trainee_display_name}</CardTitle>
                        <CardDescription className="flex items-center gap-2 text-sm font-medium">
                            <MapPin className="h-4 w-4" />
                            {course.airport_icao}
                        </CardDescription>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Badge variant="outline" className={getTypeColor(course.type)}>
                            {course.type_display}
                        </Badge>
                        <Badge variant="secondary">{course.position_display}</Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="-mt-4 space-y-3">
                {course.description && <p className="pt-0 text-sm text-muted-foreground">{course.description}</p>}

                <div className="space-y-3">
                    {/* Status Indicator */}
                    <div className={cn('flex items-center gap-2 text-sm font-medium', getStatusColor(course))}>
                        {course.is_on_waiting_list ? (
                            <>
                                <Clock className="h-4 w-4" />
                                <span>
                                    Queue Position #{course.waiting_list_position}
                                    {course.waiting_list_activity !== undefined &&
                                        course.waiting_list_activity !== null &&
                                        ` • ${course.waiting_list_activity}h activity`}
                                </span>
                            </>
                        ) : course.can_join ? (
                            <>
                                <CheckCircle className="h-4 w-4" />
                                <span>Available to Join</span>
                            </>
                        ) : (
                            <>
                                <AlertCircle className="h-4 w-4" />
                                <span>Currently Unavailable</span>
                            </>
                        )}
                    </div>
                </div>
            </CardContent>

            <CardFooter>
                <WaitingListButton
                    course={course}
                    onCourseUpdate={handleCourseUpdate}
                    className="w-full transition-all duration-200"
                    size="sm"
                    userHasActiveRtgCourse={userHasActiveRtgCourse}
                />
            </CardFooter>
        </Card>
    );
}