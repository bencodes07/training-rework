import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { WYSIWYGEditor } from '@/components/logs/wysiwyg-editor';
import { CheckCircle2, XCircle, ChevronDown, Info, Loader2, InfoIcon } from 'lucide-react';
import { useState, useEffect, Fragment } from 'react';
import { useDebounce } from '@/hooks/use-debounce';
import { cn } from '@/lib/utils';
import { BreadcrumbItem } from '@/types';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Training Logs',
        href: '',
    },
    {
        title: 'Create Log',
        href: route('courses.index'),
    },
];

interface EvaluationCategory {
    name: string;
    label: string;
    description: string;
}

interface TrainingLogCreateProps {
    trainee: {
        id: number;
        name: string;
        vatsim_id: number;
    };
    course: {
        id: number;
        name: string;
        position: string;
        type: string;
    };
    categories: EvaluationCategory[];
    sessionTypes: { value: string; label: string }[];
    ratingOptions: { value: number; label: string }[];
    trafficLevels: { value: string; label: string }[];
}

interface LogFormData {
    trainee_id: number;
    course_id: number;
    session_date: string;
    position: string;
    type: string;
    traffic_level: string;
    traffic_complexity: string;
    runway_configuration: string;
    surrounding_stations: string;
    session_duration: string;
    special_procedures: string;
    airspace_restrictions: string;
    theory: number;
    theory_positives: string;
    theory_negatives: string;
    phraseology: number;
    phraseology_positives: string;
    phraseology_negatives: string;
    coordination: number;
    coordination_positives: string;
    coordination_negatives: string;
    tag_management: number;
    tag_management_positives: string;
    tag_management_negatives: string;
    situational_awareness: number;
    situational_awareness_positives: string;
    situational_awareness_negatives: string;
    problem_recognition: number;
    problem_recognition_positives: string;
    problem_recognition_negatives: string;
    traffic_planning: number;
    traffic_planning_positives: string;
    traffic_planning_negatives: string;
    reaction: number;
    reaction_positives: string;
    reaction_negatives: string;
    separation: number;
    separation_positives: string;
    separation_negatives: string;
    efficiency: number;
    efficiency_positives: string;
    efficiency_negatives: string;
    ability_to_work_under_pressure: number;
    ability_to_work_under_pressure_positives: string;
    ability_to_work_under_pressure_negatives: string;
    motivation: number;
    motivation_positives: string;
    motivation_negatives: string;
    internal_remarks: string;
    final_comment: string;
    result: boolean | null;
    next_step: string;
}

export default function CreateTrainingLog({ trainee, course, categories, sessionTypes, ratingOptions, trafficLevels }: TrainingLogCreateProps) {
    const [showAdditionalDetails, setShowAdditionalDetails] = useState(false);
    const [autoSaveStatus, setAutoSaveStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
    const storageKey = `training-log-draft-${trainee.id}-${course.id}`;

    const { data, setData, post, processing, errors } = useForm<LogFormData>({
        trainee_id: trainee.id,
        course_id: course.id,
        session_date: new Date().toISOString().split('T')[0],
        position: '',
        type: '',
        traffic_level: '',
        traffic_complexity: '',
        runway_configuration: '',
        surrounding_stations: '',
        session_duration: '',
        special_procedures: '',
        airspace_restrictions: '',
        theory: 0,
        theory_positives: '',
        theory_negatives: '',
        phraseology: 0,
        phraseology_positives: '',
        phraseology_negatives: '',
        coordination: 0,
        coordination_positives: '',
        coordination_negatives: '',
        tag_management: 0,
        tag_management_positives: '',
        tag_management_negatives: '',
        situational_awareness: 0,
        situational_awareness_positives: '',
        situational_awareness_negatives: '',
        problem_recognition: 0,
        problem_recognition_positives: '',
        problem_recognition_negatives: '',
        traffic_planning: 0,
        traffic_planning_positives: '',
        traffic_planning_negatives: '',
        reaction: 0,
        reaction_positives: '',
        reaction_negatives: '',
        separation: 0,
        separation_positives: '',
        separation_negatives: '',
        efficiency: 0,
        efficiency_positives: '',
        efficiency_negatives: '',
        ability_to_work_under_pressure: 0,
        ability_to_work_under_pressure_positives: '',
        ability_to_work_under_pressure_negatives: '',
        motivation: 0,
        motivation_positives: '',
        motivation_negatives: '',
        internal_remarks: '',
        final_comment: '',
        result: null,
        next_step: '',
    });

    // Load draft from localStorage
    useEffect(() => {
        const savedDraft = localStorage.getItem(storageKey);
        if (savedDraft) {
            try {
                const parsedDraft = JSON.parse(savedDraft);
                setData(parsedDraft);
            } catch (error) {
                console.error('Failed to load draft:', error);
            }
        }
    }, [storageKey, setData]);

    // Auto-save with debounce
    const debouncedData = useDebounce(data, 1000);

    useEffect(() => {
        if (debouncedData) {
            setAutoSaveStatus('saving');
            try {
                localStorage.setItem(storageKey, JSON.stringify(debouncedData));
                setAutoSaveStatus('saved');
                setTimeout(() => setAutoSaveStatus('idle'), 2000);
            } catch (error) {
                console.error('Failed to save draft:', error);
            }
        }
    }, [debouncedData, storageKey]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('training-logs.store'), {
            onSuccess: () => localStorage.removeItem(storageKey),
        });
    };

    const RatingButton = ({ value, label, selected, onClick }: { value: number; label: string; selected: boolean; onClick: () => void }) => {
        const getColorClasses = () => {
            if (!selected) return '';

            switch (value) {
                case 4:
                    return 'border-2 border-green-500 bg-green-500 text-white hover:bg-green-600 hover:text-white';
                case 3:
                    return 'border-2 border-blue-500 bg-blue-500 text-white hover:bg-blue-600 hover:text-white';
                case 2:
                    return 'border-2 border-yellow-500 bg-yellow-500 text-white hover:bg-yellow-600 hover:text-white';
                case 1:
                    return 'border-2 border-red-500 bg-red-500 text-white hover:bg-red-600 hover:text-white';
                default:
                    return 'border-2 dark:border-primary';
            }
        };

        return (
            <Tooltip delayDuration={200}>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        onClick={onClick}
                        variant={'outline'}
                        className={cn(
                            'flex size-8 items-center justify-center rounded-lg text-base font-bold transition-all duration-200',
                            getColorClasses(),
                        )}
                    >
                        {value === 0 ? '—' : value}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{label}</p>
                </TooltipContent>
            </Tooltip>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Training Log - ${trainee.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Session Information */}
                    <Card>
                        <CardContent className="gap-0">
                            <div className="mb-6 flex items-center justify-between">
                                <div>
                                    <h2 className="text-xl font-semibold">Session Information</h2>
                                    <p className="mt-1 flex gap-1 text-sm text-muted-foreground">
                                        {trainee.name} • <Badge variant={'outline'}>{course.name}</Badge>
                                    </p>
                                </div>
                                {autoSaveStatus !== 'idle' && (
                                    <Badge variant={autoSaveStatus === 'saved' ? 'default' : 'secondary'}>
                                        {autoSaveStatus === 'saving' ? (
                                            <>
                                                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                Saved
                                            </>
                                        )}
                                    </Badge>
                                )}
                            </div>

                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                <div className="space-y-2">
                                    <Label htmlFor="session_date">Date *</Label>
                                    <Input
                                        id="session_date"
                                        type="date"
                                        value={data.session_date}
                                        onChange={(e) => setData('session_date', e.target.value)}
                                        max={new Date().toISOString().split('T')[0]}
                                        className={errors.session_date ? 'border-red-500' : ''}
                                    />
                                    {errors.session_date && <p className="text-sm text-red-600">{errors.session_date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="position">Position *</Label>
                                    <Input
                                        id="position"
                                        value={data.position}
                                        onChange={(e) => setData('position', e.target.value)}
                                        placeholder="e.g., EDDF_N_APP"
                                        maxLength={25}
                                        className={errors.position ? 'border-red-500' : ''}
                                    />
                                    {errors.position && <p className="text-sm text-red-600">{errors.position}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="session_duration">Duration (minutes)</Label>
                                    <Input
                                        id="session_duration"
                                        type="number"
                                        value={data.session_duration}
                                        onChange={(e) => setData('session_duration', e.target.value)}
                                        placeholder="90"
                                        min="1"
                                        max="480"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="type">Session Type</Label>
                                    <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                        <SelectTrigger id="type">
                                            <SelectValue placeholder="Select type..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sessionTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Additional Details - Collapsible */}
                            <div className="mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowAdditionalDetails(!showAdditionalDetails)}
                                    className="flex w-full items-center justify-between rounded-lg border bg-muted/30 p-4 text-left transition-colors hover:bg-muted/50"
                                >
                                    <span className="font-medium">Additional Session Details</span>
                                    <ChevronDown className={cn('h-5 w-5 transition-transform duration-200', showAdditionalDetails && 'rotate-180')} />
                                </button>

                                {showAdditionalDetails && (
                                    <div className="mt-4 space-y-4 rounded-lg border bg-muted/10 p-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="traffic_level">Traffic Level</Label>
                                                <Select
                                                    value={data.traffic_level || 'none'}
                                                    onValueChange={(value) => setData('traffic_level', value === 'none' ? '' : value)}
                                                >
                                                    <SelectTrigger id="traffic_level">
                                                        <SelectValue placeholder="Not specified" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">Not specified</SelectItem>
                                                        {trafficLevels.map((level) => (
                                                            <SelectItem key={level.value} value={level.value}>
                                                                {level.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="traffic_complexity">Traffic Complexity</Label>
                                                <Select
                                                    value={data.traffic_complexity || 'none'}
                                                    onValueChange={(value) => setData('traffic_complexity', value === 'none' ? '' : value)}
                                                >
                                                    <SelectTrigger id="traffic_complexity">
                                                        <SelectValue placeholder="Not specified" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">Not specified</SelectItem>
                                                        {trafficLevels.map((level) => (
                                                            <SelectItem key={level.value} value={level.value}>
                                                                {level.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="runway_configuration">Runway Configuration</Label>
                                                <Input
                                                    id="runway_configuration"
                                                    value={data.runway_configuration}
                                                    onChange={(e) => setData('runway_configuration', e.target.value)}
                                                    placeholder="e.g., 25L/07R"
                                                    maxLength={50}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="surrounding_stations">Surrounding Stations</Label>
                                                <Input
                                                    id="surrounding_stations"
                                                    value={data.surrounding_stations}
                                                    onChange={(e) => setData('surrounding_stations', e.target.value)}
                                                    placeholder="e.g., EDDF_C_TWR, EDDF_C_GND"
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="special_procedures">Special Procedures</Label>
                                            <WYSIWYGEditor
                                                value={data.special_procedures}
                                                onChange={(value) => setData('special_procedures', value)}
                                                placeholder="Describe any special procedures used..."
                                                minHeight="120px"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="airspace_restrictions">Airspace Restrictions</Label>
                                            <WYSIWYGEditor
                                                value={data.airspace_restrictions}
                                                onChange={(value) => setData('airspace_restrictions', value)}
                                                placeholder="Note any airspace restrictions..."
                                                minHeight="120px"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Performance Evaluation */}
                    <div className="space-y-4">
                        <h2 className="text-2xl font-bold">Performance Evaluation</h2>

                        <div className="space-y-4">
                            <Card className="overflow-hidden">
                                <CardContent className="space-y-8">
                                    {categories.map((category, index) => (
                                        <Fragment key={category.name}>
                                            <div className="mb-6 flex items-center justify-between">
                                                <div className="flex items-center justify-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-lg font-bold text-primary dark:bg-primary/20">
                                                        {index + 1}
                                                    </div>
                                                    <h3 className="text-center text-lg font-semibold">{category.label}</h3>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <InfoIcon className="size-3 text-blue-500" />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{category.description}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                                <div className="flex gap-2">
                                                    {ratingOptions.map((option) => (
                                                        <RatingButton
                                                            key={option.value}
                                                            value={option.value}
                                                            label={option.label}
                                                            selected={data[category.name as keyof LogFormData] === option.value}
                                                            onClick={() => setData(category.name as keyof LogFormData, option.value as any)}
                                                        />
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label className="flex items-center gap-2 text-base text-green-700">
                                                        <CheckCircle2 className="h-4 w-4" />
                                                        Strengths
                                                    </Label>
                                                    <WYSIWYGEditor
                                                        value={data[`${category.name}_positives` as keyof LogFormData] as string}
                                                        onChange={(value) => setData(`${category.name}_positives` as keyof LogFormData, value as any)}
                                                        placeholder=""
                                                        minHeight="150px"
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label className="flex items-center gap-2 text-base text-amber-700">
                                                        <Info className="h-4 w-4" />
                                                        Areas for Improvement
                                                    </Label>
                                                    <WYSIWYGEditor
                                                        value={data[`${category.name}_negatives` as keyof LogFormData] as string}
                                                        onChange={(value) => setData(`${category.name}_negatives` as keyof LogFormData, value as any)}
                                                        placeholder=""
                                                        minHeight="150px"
                                                    />
                                                </div>
                                            </div>
                                        </Fragment>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Final Assessment */}
                    <Card className="border-2">
                        <CardContent className="gap-0">
                            <h2 className="mb-6 text-2xl font-bold">Final Assessment</h2>

                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="final_comment" className="text-base">
                                        Overall Session Summary
                                    </Label>
                                    <WYSIWYGEditor
                                        value={data.final_comment}
                                        onChange={(value) => setData('final_comment', value)}
                                        placeholder="Provide a comprehensive assessment of the trainee's overall performance during this session..."
                                        minHeight="200px"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="next_step" className="text-base">
                                        Next Training Step
                                    </Label>
                                    <Input
                                        id="next_step"
                                        value={data.next_step}
                                        onChange={(e) => setData('next_step', e.target.value)}
                                        placeholder="e.g., Continue with complex approach scenarios"
                                    />
                                </div>

                                <Separator />

                                <div className="space-y-2">
                                    <Label htmlFor="internal_remarks" className="flex items-center gap-2 text-base text-muted-foreground">
                                        Internal Mentor Notes
                                        <Badge variant="secondary" className="text-xs">
                                            Private
                                        </Badge>
                                    </Label>
                                    <WYSIWYGEditor
                                        value={data.internal_remarks}
                                        onChange={(value) => setData('internal_remarks', value)}
                                        placeholder="Private notes for mentors only (not visible to trainee)..."
                                        minHeight="150px"
                                    />
                                </div>

                                <Separator />

                                {/* Session Result */}
                                <div className="flex flex-col space-y-3">
                                    <Label className="text-lg font-semibold">Session Result *</Label>
                                    <div className="grid grid-cols-2 gap-4">
                                        <Button
                                            type="button"
                                            variant={data.result ? 'success' : 'outline'}
                                            className="py-8"
                                            onClick={() => setData('result', true)}
                                        >
                                            <CheckCircle2
                                                className={cn(
                                                    'h-12 w-12 transition-colors',
                                                    data.result === true ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500',
                                                )}
                                            />
                                            <span
                                                className={cn(
                                                    'text-lg font-bold transition-colors',
                                                    data.result === true ? 'text-green-700' : 'text-gray-600 group-hover:text-green-600',
                                                )}
                                            >
                                                Passed
                                            </span>
                                        </Button>

                                        <Button
                                            type="button"
                                            onClick={() => setData('result', false)}
                                            className="py-8"
                                            variant={!data.result ? 'destructive' : 'outline'}
                                        >
                                            <XCircle className="h-12 w-12 transition-colors" />
                                            <span className="text-lg font-bold transition-colors">Not Passed</span>
                                        </Button>
                                    </div>
                                    {errors.result && <p className="text-sm text-red-600">{errors.result}</p>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    <div className="flex items-center justify-between rounded-xl border-2 bg-muted/30 p-6">
                        <Button type="button" variant="outline" size="lg" onClick={() => router.visit(route('overview.overview'))}>
                            Cancel
                        </Button>
                        <Button type="submit" size="lg" disabled={processing} className="min-w-[200px]">
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                                    Submitting...
                                </>
                            ) : (
                                <>
                                    <CheckCircle2 className="mr-2 h-5 w-5" />
                                    Submit Training Log
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}