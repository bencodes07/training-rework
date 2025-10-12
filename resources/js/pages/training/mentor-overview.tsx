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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mentor Overview',
        href: route('overview'),
    },
];

interface Props {
    courses: MentorCourse[];
    statistics: MentorStatistics;
}

export default function MentorOverview({ courses, statistics }: Props) {
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

    useEffect(() => {
        if (!isInitialized) return;

        if (filteredCourses.length > 0) {
            // If no course is selected or the selected course is not in filtered courses
            if (!selectedCourse || !filteredCourses.find((c) => c.id === selectedCourse.id)) {
                setSelectedCourse(filteredCourses[0]);
            }
        } else {
            setSelectedCourse(null);
        }
    }, [activeCategory, filteredCourses.length, isInitialized]);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mentor Overview" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <StatisticsCards statistics={statistics} />

                <CourseFilter
                    courses={courses}
                    activeCategory={activeCategory}
                    selectedCourse={selectedCourse}
                    onCategoryChange={setActiveCategory}
                    onCourseSelect={setSelectedCourse}
                />

                {selectedCourse && (
                    <CourseDetail
                        course={selectedCourse}
                        onRemarkClick={handleRemarkClick}
                        onClaimClick={handleClaimClick}
                        onAssignClick={handleAssignClick}
                    />
                )}

                <RemarkDialog
                    trainee={selectedTrainee}
                    courseId={selectedCourse?.id || null}
                    isOpen={isRemarkDialogOpen}
                    onClose={handleRemarkClose}
                />

                <ClaimConfirmDialog
                    trainee={selectedTrainee}
                    courseId={selectedCourse?.id || null}
                    isOpen={isClaimDialogOpen}
                    onClose={handleClaimClose}
                />

                <AssignDialog
                    trainee={selectedTrainee}
                    courseId={selectedCourse?.id || null}
                    isOpen={isAssignDialogOpen}
                    onClose={handleAssignClose}
                />
            </div>
        </AppLayout>
    );
}