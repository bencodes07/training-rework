import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getPositionColor, getTypeColor, getCourseTypeDisplay } from '@/lib/course-utils';
import { MentorCourse, Trainee } from '@/types/mentor';
import { Archive, Plus, Settings, Users } from 'lucide-react';
import { TraineeRow } from './trainee-row';
import { ManageMentorsModal } from './manage-mentors-modal';
import { PastTraineesModal } from './past-trainees-modal';
import { AddTrainee } from './add-trainee';
import { useState } from 'react';

interface CourseDetailProps {
    course: MentorCourse;
    onRemarkClick: (trainee: Trainee) => void;
    onClaimClick: (trainee: Trainee) => void;
    onAssignClick: (trainee: Trainee) => void;
}

export function CourseDetail({ course, onRemarkClick, onClaimClick, onAssignClick }: CourseDetailProps) {
    const [isPastTraineesOpen, setIsPastTraineesOpen] = useState(false);
    const [isManageMentorsOpen, setIsManageMentorsOpen] = useState(false);

    return (
        <>
            <Card className="gap-0">
                <CardHeader className="border-b">
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="text-xl">{course.name}</CardTitle>
                            <CardDescription className="mt-1 flex items-center gap-2">
                                <Badge variant="outline" className={getPositionColor(course.position)}>
                                    {course.position}
                                </Badge>
                                <Badge variant="outline" className={getTypeColor(course.type)}>
                                    {getCourseTypeDisplay(course.type)}
                                </Badge>
                            </CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" disabled onClick={() => setIsPastTraineesOpen(true)}>
                                <Archive className="mr-2 h-4 w-4" />
                                Past Trainees
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => setIsManageMentorsOpen(true)}>
                                <Settings className="mr-2 h-4 w-4" />
                                Manage Mentors
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="p-0">
                    {course.trainees.length > 0 ? (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">Trainee</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead>Solo</TableHead>
                                        <TableHead>Next Step</TableHead>
                                        <TableHead>Remark</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {course.trainees.map((trainee) => (
                                        <TraineeRow
                                            key={trainee.id}
                                            trainee={trainee}
                                            courseId={course.id}
                                            onRemarkClick={onRemarkClick}
                                            onClaimClick={onClaimClick}
                                            onAssignClick={onAssignClick}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Users className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-medium">No trainees yet</h3>
                            <p className="mb-4 text-sm text-muted-foreground">Add a trainee to this course to get started</p>
                            <AddTrainee courseId={course.id} />
                        </div>
                    )}
                </CardContent>

                {course.trainees.length > 0 && (
                    <CardFooter className="border-t">
                        <div className="flex w-full items-center justify-start gap-2">
                            <AddTrainee courseId={course.id} />
                        </div>
                    </CardFooter>
                )}
            </Card>

            {/* Modals */}
            <PastTraineesModal course={course} isOpen={isPastTraineesOpen} onClose={() => setIsPastTraineesOpen(false)} />
            <ManageMentorsModal course={course} isOpen={isManageMentorsOpen} onClose={() => setIsManageMentorsOpen(false)} />
        </>
    );
}