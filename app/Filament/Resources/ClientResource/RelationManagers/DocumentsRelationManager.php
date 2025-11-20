<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Document Information')
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Document Type')
                            ->required()
                            ->options([
                                'national_id' => 'National ID',
                                'birth_certificate' => 'Birth Certificate',
                                'passport' => 'Passport',
                                'ncpwd_card' => 'NCPWD Card',
                                'medical_report' => 'Medical Report',
                                'assessment_report' => 'Assessment Report',
                                'school_report' => 'School Report',
                                'photo' => 'Photo/Image',
                                'other' => 'Other Document',
                            ])
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Document Number / ID')
                            ->maxLength(100)
                            ->placeholder('e.g., ID number, certificate number')
                            ->columnSpan(1),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Upload Document')
                            ->required()
                            ->directory('client-documents')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(10240) // 10MB
                            ->downloadable()
                            ->previewable()
                            ->helperText('Max size: 10MB. Accepted: PDF, Images, Word documents')
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('For documents that expire (e.g., IDs, passports)')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('verified')
                            ->label('Document Verified')
                            ->helperText('Has this document been verified?')
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder('Any additional information about this document')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                Tables\Columns\BadgeColumn::make('document_type')
                    ->label('Type')
                    ->colors([
                        'primary' => fn ($state) => in_array($state, ['national_id', 'birth_certificate', 'passport']),
                        'success' => 'ncpwd_card',
                        'info' => fn ($state) => in_array($state, ['medical_report', 'assessment_report']),
                        'warning' => 'school_report',
                        'gray' => fn ($state) => in_array($state, ['photo', 'other']),
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucwords($state, '_'))),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Document #')
                    ->searchable()
                    ->placeholder('N/A')
                    ->copyable(),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->file_name),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => number_format($state / 1024, 2) . ' KB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date('d M Y')
                    ->placeholder('N/A')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\IconColumn::make('verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-question-mark-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->options([
                        'national_id' => 'National ID',
                        'birth_certificate' => 'Birth Certificate',
                        'passport' => 'Passport',
                        'ncpwd_card' => 'NCPWD Card',
                        'medical_report' => 'Medical Report',
                        'assessment_report' => 'Assessment Report',
                        'school_report' => 'School Report',
                        'photo' => 'Photo',
                        'other' => 'Other',
                    ]),

                Tables\Filters\TernaryFilter::make('verified')
                    ->placeholder('All documents')
                    ->trueLabel('Verified only')
                    ->falseLabel('Unverified only'),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired Documents')
                    ->query(fn ($query) => $query->whereNotNull('expiry_date')->where('expiry_date', '<', now())),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Upload Document')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => route('client.document.download', $record->id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => !$record->verified)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'verified' => true,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('verify_selected')
                        ->label('Verify Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'verified' => true,
                                    'verified_by' => auth()->id(),
                                    'verified_at' => now(),
                                ]);
                            });
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->emptyStateHeading('No documents')
            ->emptyStateDescription('Upload documents for this client.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}