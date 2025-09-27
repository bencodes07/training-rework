import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Course } from '@/pages/training/courses';
import { Clock, MapPin, AlertCircle, CheckCircle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface CourseCardProps {
    course: Course;
    onCourseUpdate?: (courseId: number, updates: Partial<Course>) => void;
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

export default function CourseCard({ course: initialCourse, onCourseUpdate }: CourseCardProps) {
    const [course, setCourse] = useState(initialCourse);
    const [isLoading, setIsLoading] = useState(false);

    // Update local state when props change (for page refreshes)
    useEffect(() => {
        setCourse(initialCourse);
    }, [initialCourse]);

    const handleToggleWaitingList = async () => {
        if (isLoading) return;

        // Optimistic update - immediately show the change
        const wasOnWaitingList = course.is_on_waiting_list;
        const optimisticUpdates: Partial<Course> = {
            is_on_waiting_list: !wasOnWaitingList,
            waiting_list_position: !wasOnWaitingList ? 1 : undefined, // Placeholder position
        };

        setCourse((prev) => ({ ...prev, ...optimisticUpdates }));
        onCourseUpdate?.(course.id, optimisticUpdates);
        setIsLoading(true);

        // Show immediate feedback
        toast.success(wasOnWaitingList ? 'Left waiting list' : 'Joined waiting list');

        try {
            const response = await fetch(`/courses/${course.id}/waiting-list`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (data.success) {
                // Update with actual server response
                const serverUpdates: Partial<Course> = {
                    is_on_waiting_list: data.action === 'joined',
                    waiting_list_position: data.position || undefined,
                };

                setCourse((prev) => ({ ...prev, ...serverUpdates }));
                onCourseUpdate?.(course.id, serverUpdates);

                // Update toast with actual position if joined
                if (data.action === 'joined' && data.position) {
                    toast.info('Queue position confirmed');
                }
            } else {
                // Revert optimistic update on failure
                setCourse((prev) => ({
                    ...prev,
                    is_on_waiting_list: wasOnWaitingList,
                    waiting_list_position: initialCourse.waiting_list_position,
                }));
                onCourseUpdate?.(course.id, {
                    is_on_waiting_list: wasOnWaitingList,
                    waiting_list_position: initialCourse.waiting_list_position,
                });

                toast.error('Action failed');
            }
        } catch (error) {
            // Revert optimistic update on error
            setCourse((prev) => ({
                ...prev,
                is_on_waiting_list: wasOnWaitingList,
                waiting_list_position: initialCourse.waiting_list_position,
            }));
            onCourseUpdate?.(course.id, {
                is_on_waiting_list: wasOnWaitingList,
                waiting_list_position: initialCourse.waiting_list_position,
            });

            console.error('Error toggling waiting list:', error);
            toast.error('Connection error');
        } finally {
            setIsLoading(false);
        }
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
                                    {course.waiting_list_activity !== undefined && ` • ${course.waiting_list_activity}h activity`}
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
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="w-full">
                                <Button
                                    onClick={handleToggleWaitingList}
                                    disabled={isLoading || (!course.can_join && !course.is_on_waiting_list)}
                                    variant={course.is_on_waiting_list ? 'destructive' : 'default'}
                                    className={'w-full transition-all duration-200'}
                                    size="sm"
                                >
                                    {isLoading ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Loading...
                                        </>
                                    ) : course.is_on_waiting_list ? (
                                        <>
                                            <CheckCircle className="h-4 w-4" />
                                            Leave Queue
                                        </>
                                    ) : (
                                        <>
                                            <Clock className="h-4 w-4" />
                                            Join Queue
                                        </>
                                    )}
                                </Button>
                            </div>
                        </TooltipTrigger>
                        {!course.can_join && !course.is_on_waiting_list && (
                            <TooltipContent side="top" className="max-w-xs">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="h-4 w-4" />
                                    <span>{course.join_error || 'Cannot join this course at the moment'}</span>
                                </div>
                            </TooltipContent>
                        )}
                    </Tooltip>
                </TooltipProvider>
            </CardFooter>
        </Card>
    );
}