<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('vatsim_id')
                ->label('VATSIM ID')
                ->searchable()
                ->sortable(),
            TextColumn::make('name')
                ->label('Name')
                ->searchable(['first_name', 'last_name'])
                ->sortable(),
            TextColumn::make('email')
                ->searchable(),
            TextColumn::make('subdivision')
                ->badge(),
            TextColumn::make('rating')
                ->badge()
                ->color('success'),
            IconColumn::make('is_staff')
                ->label('Is Staff')
                ->boolean(),
            IconColumn::make('is_superuser')
                ->label('Is Superuser')
                ->boolean(),
            TextColumn::make('roles.name')
                ->badge()
                ->separator(','),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            TernaryFilter::make('is_staff'),
            TernaryFilter::make('is_superuser'),
            SelectFilter::make('subdivision')
                ->options([
                    'GER' => 'Germany',
                    // Add other subdivisions as needed
                ]),
        ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
