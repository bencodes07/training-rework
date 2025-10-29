import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { 
    CheckCircle2, 
    ChevronDown, 
    Info, 
    Loader2, 
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';
import { MarkdownEditor } from '@/components/logs/markdown-editor';
import { cn } from '@/lib/utils';
import { BreadcrumbItem } from '@/types';

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

    const { data, setData, post, processing, errors, reset } = useForm<LogFormData>({
        trainee_id: trainee.id,
        course_id: course.id,
        session_date: new Date().toISOString().split('T')[0],
        position: course.position,
        type: 'O',
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
    }, []);

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
    }, [debouncedData]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('training-logs.store'), {
            onSuccess: () => localStorage.removeItem(storageKey),
        });
    };

    const RatingButton = ({ value, label, selected, onClick }: { value: number; label: string; selected: boolean; onClick: () => void }) => {
        const colors = {
            4: 'border-green-500 bg-green-500 text-white hover:bg-green-600',
            3: 'border-blue-500 bg-blue-500 text-white hover:bg-blue-600',
            2: 'border-yellow-500 bg-yellow-500 text-white hover:bg-yellow-600',
            1: 'border-red-500 bg-red-500 text-white hover:bg-red-600',
            0: 'border-gray-300 bg-gray-100 text-gray-700 hover:bg-gray-200',
        };

        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={onClick}
                            className={cn(
                                'flex h-10 w-10 items-center justify-center rounded-lg border-2 text-sm font-semibold transition-all',
                                selected ? colors[value as keyof typeof colors] : 'border-gray-200 bg-white text-gray-400 hover:border-gray-300 hover:bg-gray-50'
                            )}
                        >
                            {value === 0 ? '—' : value}
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p className="font-medium">{label}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Training Log - ${trainee.name}`} />

            <div className="container mx-auto max-w-5xl px-4 py-6">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">New Training Log</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {trainee.name} • {course.name}
                        </p>
                    </div>
                    {autoSaveStatus === 'saved' && (
                        <div className="flex items-center gap-2 text-sm text-green-600">
                            <CheckCircle2 className="h-4 w-4" />
                            <span>Saved</span>
                        </div>
                    )}
                    {autoSaveStatus === 'saving' && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span>Saving...</span>
                        </div>
                    )}
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Info */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="session_date">Date *</Label>
                                    <Input
                                        id="session_date"
                                        type="date"
                                        value={data.session_date}
                                        onChange={(e) => setData('session_date', e.target.value)}
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.session_date && <p className="text-sm text-red-600">{errors.session_date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="position">Position *</Label>
                                    <Input
                                        id="position"
                                        value={data.position}
                                        onChange={(e) => setData('position', e.target.value)}
                                        placeholder="EDDF_APP"
                                        maxLength={25}
                                    />
                                    {errors.position && <p className="text-sm text-red-600">{errors.position}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="type">Type</Label>
                                    <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                        <SelectTrigger id="type">
                                            <SelectValue />
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

                            {/* Additional Details */}
                            <Collapsible open={showAdditionalDetails} onOpenChange={setShowAdditionalDetails} className="mt-4">
                                <CollapsibleTrigger asChild>
                                    <Button variant="ghost" size="sm" className="w-full justify-between">
                                        <span className="text-sm font-medium">Additional Details</span>
                                        <ChevronDown className={cn('h-4 w-4 transition-transform', showAdditionalDetails && 'rotate-180')} />
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-4 space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="traffic_level">Traffic Level</Label>
                                            <Select value={data.traffic_level} onValueChange={(value) => setData('traffic_level', value)}>
                                                <SelectTrigger id="traffic_level">
                                                    <SelectValue placeholder="Not specified" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="">Not specified</SelectItem>
                                                    {trafficLevels.map((level) => (
                                                        <SelectItem key={level.value} value={level.value}>
                                                            {level.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="traffic_complexity">Complexity</Label>
                                            <Select value={data.traffic_complexity} onValueChange={(value) => setData('traffic_complexity', value)}>
                                                <SelectTrigger id="traffic_complexity">
                                                    <SelectValue placeholder="Not specified" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="">Not specified</SelectItem>
                                                    {trafficLevels.map((level) => (
                                                        <SelectItem key={level.value} value={level.value}>
                                                            {level.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="session_duration">Duration (min)</Label>
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
                                            <Label htmlFor="runway_configuration">Runway Config</Label>
                                            <Input
                                                id="runway_configuration"
                                                value={data.runway_configuration}
                                                onChange={(e) => setData('runway_configuration', e.target.value)}
                                                placeholder="25L/07R"
                                                maxLength={50}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="surrounding_stations">Surrounding Stations</Label>
                                        <Input
                                            id="surrounding_stations"
                                            value={data.surrounding_stations}
                                            onChange={(e) => setData('surrounding_stations', e.target.value)}
                                            placeholder="EDDF_TWR, EDDF_GND"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </CardContent>
                    </Card>

                    {/* Evaluation Categories */}
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Evaluation</h2>
                        {categories.map((category) => (
                            <Card key={category.name}>
                                <CardContent className="pt-6">
                                    <div className="mb-4 flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-medium">{category.label}</h3>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Info className="h-4 w-4 text-muted-foreground" />
                                                        </TooltipTrigger>
                                                        <TooltipContent className="max-w-xs">
                                                            <p className="text-sm">{category.description}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
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

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label className="text-green-700">Strengths</Label>
                                            <MarkdownEditor
                                                value={data[`${category.name}_positives` as keyof LogFormData] as string}
                                                onChange={(value) => setData(`${category.name}_positives` as keyof LogFormData, value as any)}
                                                placeholder="What went well..."
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label className="text-amber-700">Areas to Improve</Label>
                                            <MarkdownEditor
                                                value={data[`${category.name}_negatives` as keyof LogFormData] as string}
                                                onChange={(value) => setData(`${category.name}_negatives` as keyof LogFormData, value as any)}
                                                placeholder="What needs work..."
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {/* Final Assessment */}
                    <Card>
                        <CardContent className="space-y-6 pt-6">
                            <div className="space-y-2">
                                <Label htmlFor="final_comment">Final Comment</Label>
                                <MarkdownEditor
                                    value={data.final_comment}
                                    onChange={(value) => setData('final_comment', value)}
                                    placeholder="Overall assessment..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="internal_remarks" className="text-muted-foreground">
                                    Internal Remarks (private)
                                </Label>
                                <MarkdownEditor
                                    value={data.internal_remarks}
                                    onChange={(value) => setData('internal_remarks', value)}
                                    placeholder="Notes for mentors only..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="next_step">Next Step</Label>
                                <Input
                                    id="next_step"
                                    value={data.next_step}
                                    onChange={(e) => setData('next_step', e.target.value)}
                                    placeholder="Continue with..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Result *</Label>
                                <div className="flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setData('result', true)}
                                        className={cn(
                                            'flex-1 rounded-lg border-2 py-3 font-medium transition-all',
                                            data.result === true
                                                ? 'border-green-500 bg-green-50 text-green-700'
                                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                        )}
                                    >
                                        ✓ Passed
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setData('result', false)}
                                        className={cn(
                                            'flex-1 rounded-lg border-2 py-3 font-medium transition-all',
                                            data.result === false
                                                ? 'border-red-500 bg-red-50 text-red-700'
                                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                        )}
                                    >
                                        ✗ Not Passed
                                    </button>
                                </div>
                                {errors.result && <p className="text-sm text-red-600">{errors.result}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="outline" onClick={() => router.visit(route('overview.overview'))}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Submitting...
                                </>
                            ) : (
                                'Submit Log'
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}