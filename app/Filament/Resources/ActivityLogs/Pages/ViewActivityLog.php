<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\WaitingLists\WaitingListResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\KeyValue;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Details')
                    ->schema([
                        Placeholder::make('id')
                            ->label('Log ID')
                            ->content(fn ($record) => $record->id),
                        
                        Placeholder::make('action')
                            ->label('Action')
                            ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                                '<span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-' 
                                . $record->getActionColor() . '-50 text-' . $record->getActionColor() 
                                . '-700 ring-' . $record->getActionColor() . '-600/20">'
                                . e($record->getActionLabel())
                                . '</span>'
                            )),
                        
                        Placeholder::make('user')
                            ->label('User')
                            ->content(fn ($record) => $record->user 
                                ? new \Illuminate\Support\HtmlString(
                                    '<a href="' . UserResource::getUrl('edit', ['record' => $record->user]) . '" class="text-primary-600 hover:underline">' 
                                    . e($record->user->name) 
                                    . '</a>'
                                )
                                : 'System'
                            ),
                        
                        Placeholder::make('ip_address')
                            ->label('IP Address')
                            ->content(fn ($record) => $record->ip_address),
                        
                        Placeholder::make('created_at')
                            ->label('Timestamp')
                            ->content(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),
                    ])->columns(2),

                Section::make('Description')
                    ->schema([
                        Placeholder::make('description')
                            ->hiddenLabel()
                            ->content(fn ($record) => $record->description ?? 'No description provided')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Subject Information')
                    ->schema([
                        Placeholder::make('model_type')
                            ->label('Model Type')
                            ->content(fn ($record) => $record->model_type ? class_basename($record->model_type) : '-'),
                        
                        Placeholder::make('model_id')
                            ->label('Model ID')
                            ->content(fn ($record) => $record->model_id 
                                ? self::getModelLink($record->model_type, $record->model_id)
                                : '-'
                            ),
                    ])
                    ->visible(fn ($record) => $record->model_type !== null)
                    ->collapsible()
                    ->columns(2),

                Section::make('Properties')
                    ->schema([
                        KeyValue::make('properties')
                            ->hiddenLabel()
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->properties))
                    ->collapsible(),

                Section::make('Request Information')
                    ->schema([
                        Placeholder::make('user_agent')
                            ->label('User Agent')
                            ->content(fn ($record) => $record->user_agent ?? '-')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected static function getModelLink(?string $modelType, ?int $modelId): \Illuminate\Support\HtmlString|string
    {
        if (!$modelType || !$modelId) {
            return '-';
        }

        $resourceMap = [
            'App\Models\User' => UserResource::class,
            'App\Models\Course' => CourseResource::class,
            'App\Models\WaitingListEntry' => WaitingListResource::class,
        ];

        if (!isset($resourceMap[$modelType])) {
            return (string) $modelId;
        }

        $resourceClass = $resourceMap[$modelType];

        try {
            $model = $modelType::find($modelId);
            
            if (!$model) {
                return $modelId . ' (deleted)';
            }

            $url = $resourceClass::getUrl('edit', ['record' => $model]);
            
            return new \Illuminate\Support\HtmlString(
                '<a href="' . e($url) . '" class="text-primary-600 hover:underline">' 
                . e($modelId) 
                . '</a>'
            );
        } catch (\Exception $e) {
            return (string) $modelId;
        }
    }
}