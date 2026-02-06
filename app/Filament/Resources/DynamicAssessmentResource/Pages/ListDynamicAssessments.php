<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDynamicAssessments extends ListRecords
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_intake')
                ->label('New Intake Assessment')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(fn() => route('filament.admin.resources.dynamic-assessments.create', [
                    'form_slug' => 'intake-assessment'
                ]))
                ->visible(fn() => class_exists('\App\Models\AssessmentFormSchema')),
            
            Actions\CreateAction::make()
                ->label('New Assessment')
                ->disabled()
                ->tooltip('Service assessments are created from Service Point Dashboard'),
        ];
    }
}