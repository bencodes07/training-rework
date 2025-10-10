<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use Filament\Widgets\ChartWidget;

class CourseOverviewChart extends ChartWidget
{
    protected ?string $heading = 'Courses by Type';

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $coursesByType = Course::select('type')
            ->selectRaw('count(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        $labels = [];
        $data = [];

        foreach ($coursesByType as $type => $count) {
            $labels[] = match($type) {
                'RTG' => 'Rating',
                'EDMT' => 'Endorsement',
                'GST' => 'Visitor',
                'FAM' => 'Familiarisation',
                'RST' => 'Roster Reentry',
                default => $type,
            };
            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Courses',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',  // success - RTG
                        'rgb(251, 191, 36)', // warning - EDMT
                        'rgb(59, 130, 246)', // info - GST
                        'rgb(168, 85, 247)', // purple - FAM
                        'rgb(107, 114, 128)', // gray - RST
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}