import { CourseDetail } from '@/components/overview/course-detail';
import { CourseFilter } from '@/components/overview/course-filter';
import { RemarkDialog } from '@/components/overview/remark-dialog';
import { ClaimConfirmDialog, AssignDialog } from '@/components/overview/claim-dialogs';
import { StatisticsCards } from '@/components/overview/statistics-cards';
import { useMentorStorage } from '@/hooks/use-mentor-storage';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { MentorCourse, MentorStatistics, Trainee } from '@/types/mentor';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mentor Overview',
        href: route('overview.index'),
    },
];

interface Props {
    courses: MentorCourse[];
    statistics: MentorStatistics;
}

export default function MentorOverview({ courses: initialCourses, statistics }: Props) {
    const [courses, setCourses] = useState<MentorCourse[]>(initialCourses);
    const [loadingCourses, setLoadingCourses] = useState<Set<number>>(new Set());

    const { activeCategory, selectedCourse, setActiveCategory, setSelectedCourse, isInitialized } = useMentorStorage(courses);

    const [selectedTrainee, setSelectedTrainee] = useState<Trainee | null>(null);
    const [isRemarkDialogOpen, setIsRemarkDialogOpen] = useState(false);
    const [isClaimDialogOpen, setIsClaimDialogOpen] = useState(false);
    const [isAssignDialogOpen, setIsAssignDialogOpen] = useState(false);

    const filteredCourses = courses.filter((course) => {
        if (activeCategory === 'EDMT_FAM') {
            return course.type === 'EDMT' || course.type === 'FAM';
        }
        return course.type === activeCategory;
    });

    const loadCourseData = async (courseId: number) => {
        if (loadingCourses.has(courseId)) return;

        const course = courses.find((c) => c.id === courseId);
        if (course?.loaded) return;

        setLoadingCourses((prev) => new Set(prev).add(courseId));

        try {
            const response = await axios.get(route('overview.course.trainees', { courseId }));
            const courseData = response.data;

            setCourses((prevCourses) => prevCourses.map((c) => (c.id === courseId ? { ...courseData, loaded: true } : c)));
        } catch (error) {
            console.error('Failed to load course data:', error);
        } finally {
            setLoadingCourses((prev) => {
                const next = new Set(prev);
                next.delete(courseId);
                return next;
            });
        }
    };

    useEffect(() => {
        if (!isInitialized) return;

        if (filteredCourses.length > 0) {
            if (!selectedCourse || !filteredCourses.find((c) => c.id === selectedCourse.id)) {
                const newSelectedCourse = filteredCourses[0];
                setSelectedCourse(newSelectedCourse);
                if (!newSelectedCourse.loaded) {
                    loadCourseData(newSelectedCourse.id);
                }
            }
        } else {
            setSelectedCourse(null);
        }
    }, [activeCategory, filteredCourses.length, isInitialized]);

    const handleCourseSelect = async (course: MentorCourse) => {
        setSelectedCourse(course);
        if (!course.loaded) {
            await loadCourseData(course.id);
        }
    };

    const handleRemarkClick = (trainee: Trainee) => {
        setSelectedTrainee(trainee);
        setIsRemarkDialogOpen(true);
    };

    const handleClaimClick = (trainee: Trainee) => {
        setSelectedTrainee(trainee);
        setIsClaimDialogOpen(true);
    };

    const handleAssignClick = (trainee: Trainee) => {
        setSelectedTrainee(trainee);
        setIsAssignDialogOpen(true);
    };

    const handleRemarkClose = () => {
        setIsRemarkDialogOpen(false);
        setSelectedTrainee(null);
    };

    const handleClaimClose = () => {
        setIsClaimDialogOpen(false);
        setSelectedTrainee(null);
    };

    const handleAssignClose = () => {
        setIsAssignDialogOpen(false);
        setSelectedTrainee(null);
    };

    const currentCourse = courses.find((c) => c.id === selectedCourse?.id) || selectedCourse;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mentor Overview" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <StatisticsCards statistics={statistics} />

                <CourseFilter
                    courses={courses}
                    activeCategory={activeCategory}
                    selectedCourse={currentCourse}
                    onCategoryChange={setActiveCategory}
                    onCourseSelect={handleCourseSelect}
                />

                {currentCourse && (
                    <CourseDetail
                        course={currentCourse}
                        onRemarkClick={handleRemarkClick}
                        onClaimClick={handleClaimClick}
                        onAssignClick={handleAssignClick}
                        isLoading={loadingCourses.has(currentCourse.id)}
                    />
                )}

                <RemarkDialog
                    trainee={selectedTrainee}
                    courseId={currentCourse?.id || null}
                    isOpen={isRemarkDialogOpen}
                    onClose={handleRemarkClose}
                />

                <ClaimConfirmDialog
                    trainee={selectedTrainee}
                    courseId={currentCourse?.id || null}
                    isOpen={isClaimDialogOpen}
                    onClose={handleClaimClose}
                />

                <AssignDialog
                    trainee={selectedTrainee}
                    courseId={currentCourse?.id || null}
                    isOpen={isAssignDialogOpen}
                    onClose={handleAssignClose}
                />
            </div>
        </AppLayout>
    );
}