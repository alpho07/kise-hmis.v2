<?php

namespace App\Filament\Resources\DynamicAssessmentResource\Pages;

use App\Filament\Resources\DynamicAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDynamicAssessment extends ViewRecord
{
    protected static string $resource = DynamicAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->status !== 'completed'),
            
            Actions\Action::make('print')
                ->label('Print Assessment')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn($record) => route('assessments.print', $record))
                ->openUrlInNewTab()
                ->visible(fn($record) => $record->status === 'completed' && \Illuminate\Support\Facades\Route::has('assessments.print')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Assessment Information')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('schema.name')
                                    ->label('Assessment Type')
                                    ->badge()
                                    ->color('info'),
                                
                                Infolists\Components\TextEntry::make('schema.category')
                                    ->label('Category')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('visit.visit_number')
                                    ->label('Visit Number')
                                    ->icon('heroicon-o-hashtag')
                                    ->copyable(),
                            ]),
                        
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'completed' => 'success',
                                        'in_progress' => 'warning',
                                        'draft' => 'gray',
                                        default => 'gray',
                                    }),
                                
                                Infolists\Components\TextEntry::make('completion_percentage')
                                    ->label('Completion')
                                    ->suffix('%'),
                                
                                Infolists\Components\TextEntry::make('submitted_at')
                                    ->label('Submitted')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Client Details')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('client.full_name')
                                    ->label('Client Name')
                                    ->weight('bold'),
                                
                                Infolists\Components\TextEntry::make('client.uci')
                                    ->label('UCI')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('client.age')
                                    ->label('Age')
                                    ->suffix(' years'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Assessment Responses')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('response_data')
                            ->label('')
                            ->columnSpanFull()
                            ->keyLabel('Field')
                            ->valueLabel('Response'),
                    ]),

                Infolists\Components\Section::make('Auto-Referrals')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn($record) => $record->autoReferrals()->exists())
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('autoReferrals')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('service_point')
                                    ->label('Referred To')
                                    ->badge()
                                    ->color('warning'),
                                
                                Infolists\Components\TextEntry::make('priority')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        default => 'gray',
                                    }),
                                
                                Infolists\Components\TextEntry::make('reason')
                                    ->columnSpan(2),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ])
                            ->columns(4),
                    ]),

                Infolists\Components\Section::make('Metadata')
                    ->icon('heroicon-o-information-circle')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_by')
                                    ->label('Created By')
                                    ->getStateUsing(fn($record) => \App\Models\User::find($record->created_by)?->name ?? 'Unknown'),
                                
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                
                                Infolists\Components\TextEntry::make('updated_by')
                                    ->label('Updated By')
                                    ->getStateUsing(fn($record) => $record->updated_by ? \App\Models\User::find($record->updated_by)?->name : 'N/A'),
                                
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }
}