<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TriageResource\Pages;
use App\Models\Triage;
use App\Models\Visit;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TriageResource extends Resource
{
    protected static ?string $model = Triage::class;
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'Triage Assessments';
    protected static ?string $modelLabel = 'Triage';
    protected static ?string $pluralModelLabel = 'Triage Assessments';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden Fields
                Forms\Components\Hidden::make('visit_id')
                    ->default(fn() => request()->query('visit'))
                    ->required(),

                Forms\Components\Hidden::make('client_id')
                    ->default(function () {
                        $visitId = request()->query('visit');
                        if ($visitId) {
                            $visit = Visit::find($visitId);
                            return $visit?->client_id;
                        }
                        return null;
                    })
                    ->required(),

                // ============================
                // CLIENT & VISIT INFORMATION
                // ============================
                Forms\Components\Section::make('Client & Visit Information')
                    ->description('Automatically loaded from queue')
                    ->icon('heroicon-o-user-circle')
                    ->collapsible()
                    ->collapsed(false)
                    ->extraAttributes(['class' => 'bg-gradient-to-br from-primary-50 via-white to-primary-100/40 rounded-xl shadow-md border border-primary-100 p-5'])
                    ->schema([
                        Forms\Components\Placeholder::make('client_info')
                            ->label('')
                            ->content(function (): HtmlString {
                                $visitId = request()->query('visit');
                                if (!$visitId) {
                                    return new HtmlString('<p class="text-sm text-gray-500 italic">No visit selected.</p>');
                                }

                                $visit = Visit::with(['client.county', 'client.ward'])->find($visitId);
                                if (!$visit || !$visit->client) {
                                    return new HtmlString('<p class="text-sm text-red-600 font-semibold">Visit or client not found.</p>');
                                }

                                $client = $visit->client;
                                $age = $client->date_of_birth 
                                    ? \Carbon\Carbon::parse($client->date_of_birth)->age 
                                    : $client->estimated_age;

                                $avatarInitial = strtoupper(substr($client->full_name, 0, 1));

                                $html = '
                                <div class="flex flex-col md:flex-row items-center gap-5 bg-white/80 rounded-xl border border-primary-100 shadow-sm p-5">
                                    <div class="relative flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-tr from-primary-500 to-primary-700 text-white font-bold text-3xl shadow-md select-none">
                                        ' . $avatarInitial . '
                                    </div>
                                    <div class="flex-1 w-full">
                                        <div class="flex flex-wrap items-center justify-between mb-3">
                                            <h3 class="text-xl font-semibold text-primary-800 flex items-center gap-2">
                                                <x-heroicon-o-user class="w-5 h-5 text-primary-600" />
                                                ' . e($client->full_name) . '
                                            </h3>
                                            <span class="px-3 py-1 bg-primary-600 text-white text-xs font-semibold rounded-full shadow-sm tracking-wider">
                                                ' . e($client->uci) . '
                                            </span>
                                        </div>

                                        <div class="grid md:grid-cols-3 gap-4 text-sm mb-3">
                                            <div>
                                                <p class="text-gray-600">Age</p>
                                                <p class="font-semibold text-gray-900">' . ($age ?? 'N/A') . ' yrs</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Gender</p>
                                                <p class="font-semibold text-gray-900">' . ucfirst($client->gender) . '</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Phone</p>
                                                <p class="font-semibold text-gray-900">' . ($client->phone_primary ?? 'N/A') . '</p>
                                            </div>
                                        </div>

                                        <div class="grid md:grid-cols-2 gap-4 text-sm mb-4">
                                            <div>
                                                <p class="text-gray-600">Location</p>
                                                <p class="font-semibold text-gray-900">' . ($client->ward?->name ?? 'N/A') . ', ' . ($client->county?->name ?? 'N/A') . '</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">NCPWD Number</p>
                                                <p class="font-semibold text-gray-900">' . ($client->ncpwd_number ?? 'Not registered') . '</p>
                                            </div>
                                        </div>

                                        <div class="pt-3 border-t border-primary-200 grid md:grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p class="text-gray-600">Visit Number</p>
                                                <p class="font-semibold text-gray-900">' . e($visit->visit_number) . '</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Visit Type</p>
                                                <p class="font-semibold text-gray-900">' . ucwords(str_replace("_", " ", $visit->visit_type)) . '</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Purpose</p>
                                                <p class="font-semibold text-gray-900">' . ucfirst($visit->visit_purpose) . '</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Check-In Time</p>
                                                <p class="font-semibold text-gray-900">' . $visit->check_in_time->format('H:i') . '</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                // ==========================================================
                // VITAL SIGNS SECTION
                // ==========================================================
                Forms\Components\Section::make('Vital Signs')
                    ->description('Record all vital measurements accurately')
                    ->icon('heroicon-o-heart')
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-white/80 rounded-xl border border-gray-100 shadow-sm p-4'])
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('temperature')
                                    ->label('Temperature (°C)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(35)
                                    ->maxValue(45)
                                    ->suffix('°C')
                                    ->placeholder('36.5')
                                    ->helperText('Normal: 36.5–37.5°C')
                                    ->live()
                                    ->afterStateUpdated(fn($state, Set $set) => 
                                        $set('temp_alert', $state && ($state < 36 || $state > 38))
                                    ),

                                Forms\Components\TextInput::make('heart_rate')
                                    ->label('Heart Rate (bpm)')
                                    ->numeric()
                                    ->suffix('bpm')
                                    ->placeholder('72')
                                    ->helperText('Normal: 60–100 bpm'),

                                Forms\Components\TextInput::make('respiratory_rate')
                                    ->label('Respiratory Rate (/min)')
                                    ->numeric()
                                    ->suffix('/min')
                                    ->placeholder('16')
                                    ->helperText('Normal: 12–20/min'),

                                Forms\Components\TextInput::make('systolic_bp')
                                    ->label('Systolic BP (mmHg)')
                                    ->numeric()
                                    ->suffix('mmHg')
                                    ->placeholder('120')
                                    ->required(),

                                Forms\Components\TextInput::make('diastolic_bp')
                                    ->label('Diastolic BP (mmHg)')
                                    ->numeric()
                                    ->suffix('mmHg')
                                    ->placeholder('80')
                                    ->required()
                                    ->helperText('Normal: 120/80 mmHg'),

                                Forms\Components\TextInput::make('oxygen_saturation')
                                    ->label('SpO₂ (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->placeholder('98')
                                    ->required()
                                    ->helperText('Normal: >95%')
                                    ->live()
                                    ->afterStateUpdated(fn($state, Set $set) => 
                                        $set('red_flag_low_oxygen', $state && $state < 92)
                                    ),

                                Forms\Components\TextInput::make('weight')
                                    ->label('Weight (kg)')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->placeholder('70.0')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $height = $get('height');
                                        if ($state && $height) {
                                            $bmi = $state / (($height / 100) ** 2);
                                            $set('bmi', round($bmi, 2));
                                            if ($bmi < 18.5) $set('bmi_category', 'Underweight');
                                            elseif ($bmi < 25) $set('bmi_category', 'Normal');
                                            elseif ($bmi < 30) $set('bmi_category', 'Overweight');
                                            else $set('bmi_category', 'Obese');
                                        }
                                    }),

                                Forms\Components\TextInput::make('height')
                                    ->label('Height (cm)')
                                    ->numeric()
                                    ->suffix('cm')
                                    ->placeholder('170.0')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $weight = $get('weight');
                                        if ($state && $weight) {
                                            $bmi = $weight / (($state / 100) ** 2);
                                            $set('bmi', round($bmi, 2));
                                            if ($bmi < 18.5) $set('bmi_category', 'Underweight');
                                            elseif ($bmi < 25) $set('bmi_category', 'Normal');
                                            elseif ($bmi < 30) $set('bmi_category', 'Overweight');
                                            else $set('bmi_category', 'Obese');
                                        }
                                    }),

                                Forms\Components\TextInput::make('bmi')
                                    ->label('BMI (Auto)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('Auto-calculated')
                                    ->helperText(fn(Get $get) => $get('bmi_category') ?? 'Calculated automatically'),
                            ]),
                    ]),

                // ==========================================================
                // CHIEF COMPLAINT
                // ==========================================================
                Forms\Components\Section::make('Chief Complaint & Symptoms')
                    ->description('Reason for visit and reported symptoms')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-50 border border-gray-100 rounded-xl shadow-sm p-4'])
                    ->schema([
                        Forms\Components\Textarea::make('chief_complaint')
                            ->label('Chief Complaint')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder("Describe the patient's main concern or symptom...")
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('triage_notes')
                            ->label('Additional Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Other relevant observations or remarks...')
                            ->columnSpanFull(),
                    ]),

                // ==========================================================
                // RED FLAGS
                // ==========================================================
                Forms\Components\Section::make('Red Flags - Critical Indicators')
                    ->description('Check if any apply (trigger immediate action)')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsible()
                    ->collapsed(true)
                    ->extraAttributes(['class' => 'bg-rose-50/70 border border-rose-100 rounded-xl p-4'])
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Checkbox::make('red_flag_active_bleeding')->label('🔴 Active Bleeding'),
                                Forms\Components\Checkbox::make('red_flag_severe_pain')->label('🔴 Severe Pain (8–10/10)'),
                                Forms\Components\Checkbox::make('red_flag_seizure')->label('🔴 Seizure/Convulsions'),
                                Forms\Components\Checkbox::make('red_flag_altered_consciousness')->label('🔴 Altered Consciousness'),
                                Forms\Components\Checkbox::make('red_flag_respiratory_distress')->label('🔴 Respiratory Distress'),
                                Forms\Components\Checkbox::make('red_flag_low_oxygen')->label('🔴 SpO₂ < 92%')->disabled()->dehydrated(),
                                Forms\Components\Checkbox::make('red_flag_fever_convulsions')->label('🔴 Fever with Convulsions'),
                                Forms\Components\Checkbox::make('red_flag_suicidal_ideation')->label('🔴 Suicidal Ideation / Self-Harm'),
                                Forms\Components\Checkbox::make('red_flag_violent_behavior')->label('🔴 Violent / Aggressive Behavior'),
                                Forms\Components\Checkbox::make('red_flag_suspected_abuse')->label('🔴 Suspected Abuse / Neglect'),
                            ]),
                    ]),

                // ==========================================================
                // PAIN ASSESSMENT
                // ==========================================================
                Forms\Components\Section::make('Pain Assessment')
                    ->icon('heroicon-o-scale')
                    ->collapsible()
                    ->collapsed(true)
                    ->extraAttributes(['class' => 'bg-orange-50/80 border border-orange-100 rounded-xl p-4'])
                    ->schema([
                        Forms\Components\Radio::make('pain_scale')
                            ->label('Pain Level (0–10)')
                            ->options([
                                0 => '0 - No Pain',
                                1 => '1–2 - Mild',
                                3 => '3–4 - Moderate',
                                5 => '5–6 - Severe',
                                7 => '7–8 - Very Severe',
                                9 => '9–10 - Worst Possible',
                            ])
                            ->inline()
                            ->default(0),
                    ]),

                // ==========================================================
                // CONSCIOUSNESS
                // ==========================================================
                Forms\Components\Section::make('Consciousness Assessment')
                    ->icon('heroicon-o-eye')
                    ->collapsible()
                    ->collapsed(true)
                    ->extraAttributes(['class' => 'bg-sky-50/70 border border-sky-100 rounded-xl p-4'])
                    ->schema([
                        Forms\Components\Select::make('consciousness_level')
                            ->label('Level of Consciousness')
                            ->options([
                                'alert' => 'Alert & Oriented',
                                'drowsy' => 'Drowsy but Responsive',
                                'confused' => 'Confused / Disoriented',
                                'unresponsive' => 'Unresponsive',
                            ])
                            ->required()
                            ->default('alert')
                            ->native(false),
                    ]),

                // ==========================================================
                // TRIAGE DECISION
                // ==========================================================
                Forms\Components\Section::make('Triage Decision')
                    ->description('Final assessment and routing decision')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->collapsed(false)
                    ->extraAttributes(['class' => 'bg-emerald-50/80 border border-emerald-100 rounded-xl shadow-sm p-4'])
                    ->schema([
                        Forms\Components\Select::make('risk_level')
                            ->label('Risk Level')
                            ->options([
                                'low' => 'Low Risk',
                                'medium' => 'Medium Risk',
                                'high' => 'High Risk',
                                'critical' => 'Critical Risk',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Based on vitals, red flags, and clinical judgment'),

                        Forms\Components\Select::make('triage_status')
                            ->label('Triage Decision')
                            ->options([
                                'cleared' => '✅ Cleared - Proceed to Next Stage',
                                'medical_hold' => '⏸️ Medical Hold - Requires Clearance',
                                'crisis' => '🚨 Crisis - Activate Protocol',
                            ])
                            ->required()
                            ->default('cleared')
                            ->native(false)
                            ->helperText(function (Get $get) {
                                return match ($get('triage_status')) {
                                    'cleared' => 'Client is stable and ready for next stage.',
                                    'medical_hold' => 'Hold: requires medical attention.',
                                    'crisis' => '🚨 Immediate emergency action required.',
                                    default => '',
                                };
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit.visit_number')->label('Visit #')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->sortable(),
                Tables\Columns\BadgeColumn::make('risk_level')
                    ->colors(['success' => 'low','warning' => 'medium','danger' => ['high','critical']]),
                Tables\Columns\BadgeColumn::make('triage_status')
                    ->colors(['success' => 'cleared','warning' => 'medical_hold','danger' => 'crisis']),
                Tables\Columns\TextColumn::make('temperature')->label('Temp')->suffix('°C'),
                Tables\Columns\TextColumn::make('oxygen_saturation')->label('SpO₂')->suffix('%')->color(fn($state)=>$state<92?'danger':'success'),
                Tables\Columns\TextColumn::make('created_at')->label('Triaged At')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('triagedBy.name')->label('Triaged By')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('risk_level')->options(['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical']),
                Tables\Filters\SelectFilter::make('triage_status')->options(['cleared'=>'Cleared','medical_hold'=>'Medical Hold','crisis'=>'Crisis']),
            ])
            ->actions([Tables\Actions\ViewAction::make()->icon('heroicon-o-eye')])
            ->defaultSort('created_at','desc');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTriages::route('/'),
            'create' => Pages\CreateTriage::route('/create'),
            'view' => Pages\ViewTriage::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Triage should not be editable
    }
}
