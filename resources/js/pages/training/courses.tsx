import AppLayout from '@/layouts/app-layout';
import CourseCard from '@/components/courses/course-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AlertCircle, BookOpen, Search, Filter, Grid3X3, List, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import SortableCoursesTable from '@/components/courses/courses-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Courses',
        href: route('courses.index'),
    },
];

export interface Course {
    id: number;
    name: string;
    trainee_display_name: string;
    description?: string;
    airport_name: string;
    airport_icao: string;
    type: string;
    type_display: string;
    position: string;
    position_display: string;
    mentor_group?: string;
    min_rating: number;
    max_rating: number;
    is_on_waiting_list: boolean;
    waiting_list_position?: number;
    waiting_list_activity?: number;
    can_join: boolean;
    join_error?: string;
}

interface PageProps {
    courses: Course[];
    isVatsimUser: boolean;
    error?: string;
}

export default function Courses({ courses = [], isVatsimUser, error }: PageProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [typeFilter, setTypeFilter] = useState('all');
    const [firFilter, setFirFilter] = useState('all');
    const [activeTab, setActiveTab] = useState('all');
    const [viewMode, setViewMode] = useState<'cards' | 'table'>('cards');
    const [showFilters, setShowFilters] = useState(false);
    const [loadingCourses, setLoadingCourses] = useState<Set<number>>(new Set());

    const filteredCourses = useMemo(() => {
        if (!Array.isArray(courses)) return [];

        return courses.filter((course) => {
            const matchesSearch =
                !searchTerm ||
                course.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                course.trainee_display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                course.airport_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                course.airport_icao.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesType = typeFilter === 'all' || course.type === typeFilter;
            const matchesFir = firFilter === 'all' || (course.mentor_group && course.mentor_group.includes(firFilter));

            const matchesTab =
                activeTab === 'all' ||
                (activeTab === 'waiting' && course.is_on_waiting_list) ||
                (activeTab === 'available' && course.can_join && !course.is_on_waiting_list);

            return matchesSearch && matchesType && matchesFir && matchesTab;
        });
    }, [courses, searchTerm, typeFilter, firFilter, activeTab]);

    const handleCourseToggle = async (courseId: number) => {
        if (loadingCourses.has(courseId)) return;

        setLoadingCourses((prev) => new Set(prev.add(courseId)));

        try {
            await router.post(
                `/courses/${courseId}/waiting-list`,
                {},
                {
                    preserveState: true,
                    onFinish: () =>
                        setLoadingCourses((prev) => {
                            const newSet = new Set(prev);
                            newSet.delete(courseId);
                            return newSet;
                        }),
                },
            );
        } catch (error) {
            console.error('Error:', error);
            setLoadingCourses((prev) => {
                const newSet = new Set(prev);
                newSet.delete(courseId);
                return newSet;
            });
        }
    };

    const clearFilters = () => {
        setSearchTerm('');
        setTypeFilter('all');
        setFirFilter('all');
    };

    if (!isVatsimUser) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-8">
                    <div className="text-center">
                        <AlertCircle className="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                        <h1 className="mb-2 text-2xl font-bold">VATSIM Account Required</h1>
                        <p className="mb-4 text-muted-foreground">You need a VATSIM Germany account to view and join training courses.</p>
                        <Button onClick={() => (window.location.href = '/auth/vatsim')}>Connect VATSIM Germany Account</Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Courses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Error Message */}
                {error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                        <div className="flex items-center gap-2 text-red-800">
                            <AlertCircle className="h-5 w-5" />
                            <span>{error}</span>
                        </div>
                    </div>
                )}

                {/* Compact Filter Bar */}
                <div className="flex flex-wrap items-center gap-3">
                    {/* Search */}
                    <div className="relative min-w-[300px] flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                        <Input
                            placeholder="Search courses, airports..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-10"
                        />
                    </div>

                    {/* Course Status Tabs */}
                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="available">Available</TabsTrigger>
                            <TabsTrigger value="waiting">My Queue</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {/* View Mode Toggle */}
                    <div className="flex items-center gap-1 rounded-lg bg-muted p-1">
                        <Button variant={viewMode === 'cards' ? 'default' : 'ghost'} size="sm" onClick={() => setViewMode('cards')}>
                            <Grid3X3 className="h-4 w-4" />
                        </Button>
                        <Button variant={viewMode === 'table' ? 'default' : 'ghost'} size="sm" onClick={() => setViewMode('table')}>
                            <List className="h-4 w-4" />
                        </Button>
                    </div>

                    {/* Advanced Filters Toggle */}
                    <Button variant="outline" size="sm" onClick={() => setShowFilters(!showFilters)} className={cn(showFilters && 'bg-muted')}>
                        <Filter className="mr-2 h-4 w-4" />
                        Filters
                    </Button>

                    {/* Clear Filters - only show when advanced filters are active */}
                    {(typeFilter !== 'all' || firFilter !== 'all' || searchTerm) && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="text-muted-foreground">
                            <X className="mr-1 h-4 w-4" />
                            Clear
                        </Button>
                    )}
                </div>

                {/* Advanced Filters (Collapsible) */}
                {showFilters && (
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium">Course Type</label>
                                <Select value={typeFilter} onValueChange={setTypeFilter}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="RTG">Rating</SelectItem>
                                        <SelectItem value="EDMT">Endorsement</SelectItem>
                                        <SelectItem value="FAM">Familiarisation</SelectItem>
                                        <SelectItem value="GST">Visitor</SelectItem>
                                        <SelectItem value="RST">Roster</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium">FIR</label>
                                <Select value={firFilter} onValueChange={setFirFilter}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All FIRs" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All FIRs</SelectItem>
                                        <SelectItem value="EDGG">EDGG</SelectItem>
                                        <SelectItem value="EDMM">EDMM</SelectItem>
                                        <SelectItem value="EDWW">EDWW</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                )}

                {/* Results */}
                {filteredCourses.length === 0 ? (
                    <Card className="py-12 text-center">
                        <CardContent>
                            <BookOpen className="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">No courses found</h3>
                            <p className="text-muted-foreground">Try adjusting your filters or search criteria.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Card View */}
                        {viewMode === 'cards' && (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {filteredCourses.map((course) => (
                                    <CourseCard
                                        key={course.id}
                                        course={course}
                                        onToggleWaitingList={handleCourseToggle}
                                        isLoading={loadingCourses.has(course.id)}
                                    />
                                ))}
                            </div>
                        )}

                        {/* Table View */}
                        {viewMode === 'table' && (
                            <div className="rounded-md border">
                                <SortableCoursesTable courses={filteredCourses} onToggleWaitingList={handleCourseToggle} />
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}