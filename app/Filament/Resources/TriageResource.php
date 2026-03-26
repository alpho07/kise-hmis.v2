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
use Carbon\Carbon;

class TriageResource extends Resource
{
    protected static ?string $model = Triage::class;
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'Triage Assessments';
    protected static ?string $modelLabel = 'Triage';
    protected static ?string $pluralModelLabel = 'Triage Assessments';
    protected static ?string $navigationGroup = 'Clinical Workflow';
    protected static ?int $navigationSort = 3;

     public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /** Age-appropriate heart rate ranges (bpm) */
    public static function getHRRange(int $age): array
    {
        if ($age < 1)  return ['min' => 80, 'max' => 160, 'label' => 'Infant <1yr'];
        if ($age < 2)  return ['min' => 80, 'max' => 140, 'label' => 'Infant 1-2yr'];
        if ($age < 5)  return ['min' => 80, 'max' => 130, 'label' => 'Toddler'];
        if ($age < 7)  return ['min' => 75, 'max' => 120, 'label' => 'Preschool'];
        if ($age < 13) return ['min' => 70, 'max' => 110, 'label' => 'School Age'];
        if ($age < 18) return ['min' => 60, 'max' => 100, 'label' => 'Adolescent'];
        return ['min' => 60, 'max' => 100, 'label' => 'Adult'];
    }

    /** Age-appropriate respiratory rate ranges (/min) */
    public static function getRRRange(int $age): array
    {
        if ($age < 1)  return ['min' => 24, 'max' => 50, 'label' => 'Infant <1yr'];
        if ($age < 2)  return ['min' => 22, 'max' => 40, 'label' => 'Infant 1-2yr'];
        if ($age < 5)  return ['min' => 18, 'max' => 34, 'label' => 'Toddler'];
        if ($age < 7)  return ['min' => 18, 'max' => 30, 'label' => 'Preschool'];
        if ($age < 13) return ['min' => 16, 'max' => 24, 'label' => 'School Age'];
        if ($age < 18) return ['min' => 12, 'max' => 22, 'label' => 'Adolescent'];
        return ['min' => 12, 'max' => 20, 'label' => 'Adult'];
    }

    /**
     * Auto-calculate risk level from all current form state.
     * Returns 'low' | 'medium' | 'high' | 'critical'.
     * Nurse can still override the result in the select.
     */
    public static function computeRiskLevel(Get $get): string
    {
        $age  = (int) ($get('client_age') ?? 18);
        $temp = is_numeric($get('temperature'))      ? (float) $get('temperature')      : null;
        $hr   = is_numeric($get('heart_rate'))        ? (float) $get('heart_rate')        : null;
        $rr   = is_numeric($get('respiratory_rate'))  ? (float) $get('respiratory_rate')  : null;
        $sys  = is_numeric($get('systolic_bp'))       ? (float) $get('systolic_bp')       : null;
        $spo2 = is_numeric($get('oxygen_saturation')) ? (float) $get('oxygen_saturation') : null;
        $pain = is_numeric($get('pain_scale'))        ? (int)   $get('pain_scale')        : 0;
        $lvoc = $get('consciousness_level') ?? 'alert';

        $criticalVital = false;
        $warningVital  = false;

        if ($temp !== null) {
            if ($temp < 35 || $temp > 39.5)           $criticalVital = true;
            elseif ($temp < 36.0 || $temp > 38.0)     $warningVital  = true;
        }
        if ($hr !== null) {
            $r = static::getHRRange($age);
            if ($hr < $r['min'] * 0.75 || $hr > $r['max'] * 1.4) $criticalVital = true;
            elseif ($hr < $r['min'] || $hr > $r['max'])            $warningVital  = true;
        }
        if ($rr !== null) {
            $r = static::getRRRange($age);
            if ($rr < $r['min'] * 0.7 || $rr > $r['max'] * 1.5) $criticalVital = true;
            elseif ($rr < $r['min'] || $rr > $r['max'])           $warningVital  = true;
        }
        if ($sys !== null) {
            if ($sys < 80 || $sys >= 180)        $criticalVital = true;
            elseif ($sys < 90 || $sys >= 140)    $warningVital  = true;
        }
        if ($spo2 !== null) {
            if ($spo2 < 92)          $criticalVital = true;
            elseif ($spo2 < 95)      $warningVital  = true;
        }

        $flagFields = [
            'red_flag_active_bleeding', 'red_flag_severe_pain', 'red_flag_seizure',
            'red_flag_altered_consciousness', 'red_flag_respiratory_distress', 'red_flag_low_oxygen',
            'red_flag_fever_convulsions', 'red_flag_suicidal_ideation',
            'red_flag_violent_behavior', 'red_flag_suspected_abuse',
        ];
        $flagCount = count(array_filter($flagFields, fn($f) => (bool) $get($f)));

        // Critical: any vital extreme, unresponsive, or 2+ red flags
        if ($criticalVital || $lvoc === 'unresponsive' || $flagCount >= 2) {
            return 'critical';
        }
        // High: any vital warning, severe pain, confused consciousness, or 1 flag
        if ($warningVital || $pain >= 7 || $lvoc === 'confused' || $flagCount >= 1) {
            return 'high';
        }
        // Medium: moderate pain or drowsy
        if ($pain >= 5 || $lvoc === 'drowsy') {
            return 'medium';
        }
        return 'low';
    }

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

                Forms\Components\Hidden::make('client_age')
                    ->default(function () {
                        $visitId = request()->query('visit');
                        if ($visitId) {
                            $visit = Visit::with('client')->find($visitId);
                            $client = $visit?->client;
                            if ($client) {
                                return $client->date_of_birth
                                    ? Carbon::parse($client->date_of_birth)->age
                                    : ($client->estimated_age ?? 18);
                            }
                        }
                        return 18;
                    }),

                // ============================
                // CLIENT & VISIT INFORMATION
                // ============================
                Forms\Components\Section::make('')
                    ->hiddenLabel()
                    ->schema([
                        Forms\Components\Placeholder::make('client_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                // $get('visit_id') survives Livewire re-renders; query param only works on first load
                                $visitId = $get('visit_id') ?: request()->query('visit');
                                if (!$visitId) {
                                    return new HtmlString(
                                        '<div class="rounded-lg bg-slate-50 border border-slate-200 px-5 py-4 text-sm text-slate-500 italic">No visit selected.</div>'
                                    );
                                }

                                $visit = Visit::with(['client.county', 'client.ward'])->find($visitId);
                                if (!$visit || !$visit->client) {
                                    return new HtmlString(
                                        '<div class="rounded-lg bg-red-50 border border-red-200 px-5 py-4 text-sm text-red-700 font-medium">Visit or client not found.</div>'
                                    );
                                }

                                $client  = $visit->client;
                                $age     = $client->date_of_birth
                                    ? Carbon::parse($client->date_of_birth)->age
                                    : $client->estimated_age;
                                $initial = strtoupper(substr($client->full_name, 0, 2));

                                $genderColor = match ($client->gender) {
                                    'male'   => 'bg-blue-500/20 text-blue-200 border-blue-400/30',
                                    'female' => 'bg-pink-500/20 text-pink-200 border-pink-400/30',
                                    default  => 'bg-slate-500/20 text-slate-300 border-slate-400/30',
                                };
                                $typeColor = match ($client->client_type) {
                                    'returning' => 'bg-violet-500/20 text-violet-200 border-violet-400/30',
                                    'old_new'   => 'bg-amber-500/20 text-amber-200 border-amber-400/30',
                                    default     => 'bg-emerald-500/20 text-emerald-200 border-emerald-400/30',
                                };
                                $typeLabel = match ($client->client_type) {
                                    'returning' => 'Returning',
                                    'old_new'   => 'Old-New',
                                    default     => 'New Client',
                                };

                                $ncpwdHtml = $client->ncpwd_number
                                    ? '<span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">'
                                        . '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                                        . e($client->ncpwd_number) . '</span>'
                                    : '<span class="text-slate-400 text-xs">Not registered</span>';

                                $shaHtml = $client->sha_number
                                    ? '<span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">'
                                        . '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                                        . e($client->sha_number) . '</span>'
                                    : '<span class="text-slate-400 text-xs">Not enrolled</span>';

                                $location = trim(($client->ward?->name ?? '') . ', ' . ($client->county?->name ?? ''), ', ');

                                // Avatar background based on gender
                                $avatarBg = match ($client->gender) {
                                    'male'   => '#3b82f6',
                                    'female' => '#ec4899',
                                    default  => '#8b5cf6',
                                };
                                $genderBadge = match ($client->gender) {
                                    'male'   => 'background:rgba(59,130,246,0.25);color:#bfdbfe;border:1px solid rgba(59,130,246,0.4);',
                                    'female' => 'background:rgba(236,72,153,0.25);color:#fbcfe8;border:1px solid rgba(236,72,153,0.4);',
                                    default  => 'background:rgba(139,92,246,0.25);color:#ddd6fe;border:1px solid rgba(139,92,246,0.4);',
                                };
                                $typeBadge = match ($client->client_type) {
                                    'returning' => 'background:rgba(139,92,246,0.25);color:#ddd6fe;border:1px solid rgba(139,92,246,0.4);',
                                    'old_new'   => 'background:rgba(245,158,11,0.25);color:#fde68a;border:1px solid rgba(245,158,11,0.4);',
                                    default     => 'background:rgba(16,185,129,0.25);color:#a7f3d0;border:1px solid rgba(16,185,129,0.4);',
                                };

                                $S = 'font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 3px 0;';
                                $V = 'font-size:14px;font-weight:600;color:#1e293b;margin:0;';
                                $badge = 'display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;';

                                return new HtmlString('
<div style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.08);">

  <!-- Identity bar -->
  <div style="background:#1e293b;padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">

    <div style="width:56px;height:56px;border-radius:50%;background:' . $avatarBg . ';display:flex;align-items:center;justify-content:center;flex-shrink:0;border:3px solid rgba(255,255,255,0.25);">
      <span style="color:#fff;font-weight:800;font-size:20px;line-height:1;font-family:sans-serif;">' . $initial . '</span>
    </div>

    <div style="flex:1;min-width:160px;">
      <p style="color:#fff;font-weight:700;font-size:20px;line-height:1.25;margin:0 0 3px 0;">' . e($client->full_name) . '</p>
      <p style="color:#94a3b8;font-size:12px;font-family:monospace;margin:0;">' . e($client->uci) . '</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex-shrink:0;">
      <span style="' . $badge . 'background:rgba(255,255,255,0.12);color:#cbd5e1;">' . ($age ?? '?') . ' yrs</span>
      <span style="' . $badge . $genderBadge . '">' . ucfirst($client->gender ?? 'Unknown') . '</span>
      <span style="' . $badge . $typeBadge . '">' . $typeLabel . '</span>
    </div>
  </div>

  <!-- Details grid -->
  <div style="background:#fff;padding:16px 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px 24px;">
    <div>
      <p style="' . $S . '">Phone</p>
      <p style="' . $V . '">' . e($client->phone_primary ?? '—') . '</p>
    </div>
    <div>
      <p style="' . $S . '">Location</p>
      <p style="' . $V . '">' . e($location ?: '—') . '</p>
    </div>
    <div>
      <p style="' . $S . '">NCPWD No.</p>
      <p style="' . $V . '">' . ($client->ncpwd_number
                                    ? '<span style="color:#059669;">' . e($client->ncpwd_number) . '</span>'
                                    : '<span style="color:#cbd5e1;font-size:12px;font-weight:400;">Not registered</span>') . '</p>
    </div>
    <div>
      <p style="' . $S . '">SHA No.</p>
      <p style="' . $V . '">' . ($client->sha_number
                                    ? '<span style="color:#059669;">' . e($client->sha_number) . '</span>'
                                    : '<span style="color:#cbd5e1;font-size:12px;font-weight:400;">Not enrolled</span>') . '</p>
    </div>
  </div>

  <!-- Visit strip -->
  <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px 24px;">
    <div>
      <p style="' . $S . '">Visit No.</p>
      <p style="font-size:13px;font-weight:600;color:#1e293b;font-family:monospace;margin:0;">' . e($visit->visit_number) . '</p>
    </div>
    <div>
      <p style="' . $S . '">Type</p>
      <p style="font-size:13px;font-weight:600;color:#1e293b;margin:0;">' . ucwords(str_replace('_', ' ', $visit->visit_type ?? '')) . '</p>
    </div>
    <div>
      <p style="' . $S . '">Purpose</p>
      <p style="font-size:13px;font-weight:600;color:#1e293b;margin:0;">' . ucfirst($visit->visit_purpose ?? '') . '</p>
    </div>
    <div>
      <p style="' . $S . '">Check-in</p>
      <p style="font-size:13px;font-weight:600;color:#1e293b;margin:0;">' . ($visit->check_in_time?->format('H:i') ?? '—') . '</p>
    </div>
  </div>

</div>');
                            })
                            ->columnSpanFull(),
                    ]),

                // ==========================================================
                // VITAL SIGNS SECTION
                // ==========================================================
                Forms\Components\Section::make('Vital Signs')
                    ->description('Record all measurements — status panel updates live below')
                    ->icon('heroicon-o-heart')
                    ->collapsible()
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
                                    ->helperText('Normal: 36.5–37.5°C | Fever: >38.0°C | High Fever: >39.5°C | Hypothermia: <36.0°C')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!is_numeric($state)) return;
                                        $t = (float) $state;
                                        if ($t < 35 || $t > 39.5)        $set('temp_status', 'critical');
                                        elseif ($t < 36.0 || $t > 38.0)  $set('temp_status', 'warning');
                                        else                              $set('temp_status', 'normal');
                                        $set('risk_level', TriageResource::computeRiskLevel($get));
                                    }),

                                Forms\Components\TextInput::make('heart_rate')
                                    ->label('Heart Rate (bpm)')
                                    ->numeric()
                                    ->suffix('bpm')
                                    ->placeholder('72')
                                    ->helperText(function (Get $get): string {
                                        $r = TriageResource::getHRRange((int) ($get('client_age') ?? 18));
                                        return "Normal ({$r['label']}): {$r['min']}–{$r['max']} bpm";
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!is_numeric($state)) return;
                                        $hr = (float) $state;
                                        $r  = TriageResource::getHRRange((int) ($get('client_age') ?? 18));
                                        if ($hr < $r['min'] * 0.75 || $hr > $r['max'] * 1.4) $set('hr_status', 'critical');
                                        elseif ($hr < $r['min'] || $hr > $r['max'])            $set('hr_status', 'warning');
                                        else                                                    $set('hr_status', 'normal');
                                        $set('risk_level', TriageResource::computeRiskLevel($get));
                                    }),

                                Forms\Components\TextInput::make('respiratory_rate')
                                    ->label('Respiratory Rate (/min)')
                                    ->numeric()
                                    ->suffix('/min')
                                    ->placeholder('16')
                                    ->helperText(function (Get $get): string {
                                        $r = TriageResource::getRRRange((int) ($get('client_age') ?? 18));
                                        return "Normal ({$r['label']}): {$r['min']}–{$r['max']} /min";
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!is_numeric($state)) return;
                                        $rr = (float) $state;
                                        $r  = TriageResource::getRRRange((int) ($get('client_age') ?? 18));
                                        if ($rr < $r['min'] * 0.7 || $rr > $r['max'] * 1.5) {
                                            $set('rr_status', 'critical');
                                            $set('red_flag_respiratory_distress', true);
                                        } elseif ($rr < $r['min'] || $rr > $r['max']) {
                                            $set('rr_status', 'warning');
                                        } else {
                                            $set('rr_status', 'normal');
                                        }
                                        $set('risk_level', TriageResource::computeRiskLevel($get));
                                    }),

                                Forms\Components\TextInput::make('systolic_bp')
                                    ->label('Systolic BP (mmHg)')
                                    ->numeric()
                                    ->suffix('mmHg')
                                    ->placeholder('120')
                                    ->helperText('Normal: 90–139 mmHg | Hypotension: <90 | Stage 1 HTN: 140–159 | Crisis: ≥180')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!is_numeric($state)) return;
                                        $s = (float) $state;
                                        if ($s < 80 || $s >= 180)        $set('bp_status', 'critical');
                                        elseif ($s < 90 || $s >= 140)    $set('bp_status', 'warning');
                                        else                              $set('bp_status', 'normal');
                                        $set('risk_level', TriageResource::computeRiskLevel($get));
                                    }),

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
                                    ->helperText('Normal: ≥95% | Mild concern: 92–94% | Critical: <92%')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!is_numeric($state)) return;
                                        $v = (float) $state;
                                        $set('red_flag_low_oxygen', $v < 92);
                                        if ($v < 92)       $set('spo2_status', 'critical');
                                        elseif ($v < 95)   $set('spo2_status', 'warning');
                                        else               $set('spo2_status', 'normal');
                                        $set('risk_level', TriageResource::computeRiskLevel($get));
                                    }),

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
                // VITALS STATUS PANEL
                // ==========================================================
                Forms\Components\Section::make('Vitals Assessment')
                    ->description('Live status — updates as you enter readings above')
                    ->icon('heroicon-o-chart-bar')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Forms\Components\Placeholder::make('vitals_status_panel')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $age = (int) ($get('client_age') ?? 18);

                                $temp = is_numeric($get('temperature'))      ? (float) $get('temperature')      : null;
                                $hr   = is_numeric($get('heart_rate'))        ? (float) $get('heart_rate')        : null;
                                $rr   = is_numeric($get('respiratory_rate'))  ? (float) $get('respiratory_rate')  : null;
                                $sys  = is_numeric($get('systolic_bp'))       ? (float) $get('systolic_bp')       : null;
                                $dia  = is_numeric($get('diastolic_bp'))      ? (float) $get('diastolic_bp')      : null;
                                $spo2 = is_numeric($get('oxygen_saturation')) ? (float) $get('oxygen_saturation') : null;
                                $bmi  = is_numeric($get('bmi'))               ? (float) $get('bmi')               : null;

                                $cards = [];

                                if ($temp !== null) {
                                    if ($temp < 35 || $temp > 39.5) {
                                        $s = 'critical'; $lbl = $temp < 35 ? 'Hypothermia' : 'High Fever';
                                    } elseif ($temp < 36.0 || $temp > 38.0) {
                                        $s = 'warning'; $lbl = $temp < 36.0 ? 'Below Normal' : 'Fever';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $cards[] = ['icon' => '🌡️', 'name' => 'Temperature', 'value' => "{$temp}°C", 'label' => $lbl, 'ref' => '36.5–37.5°C', 'status' => $s];
                                }

                                if ($hr !== null) {
                                    $hrR = TriageResource::getHRRange($age);
                                    if ($hr < $hrR['min'] * 0.75 || $hr > $hrR['max'] * 1.4) {
                                        $s = 'critical'; $lbl = $hr < $hrR['min'] ? 'Severe Bradycardia' : 'Severe Tachycardia';
                                    } elseif ($hr < $hrR['min'] || $hr > $hrR['max']) {
                                        $s = 'warning'; $lbl = $hr < $hrR['min'] ? 'Bradycardia' : 'Tachycardia';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $cards[] = ['icon' => '❤️', 'name' => 'Heart Rate', 'value' => "{$hr} bpm", 'label' => $lbl, 'ref' => "{$hrR['min']}–{$hrR['max']} bpm ({$hrR['label']})", 'status' => $s];
                                }

                                if ($rr !== null) {
                                    $rrR = TriageResource::getRRRange($age);
                                    if ($rr < $rrR['min'] * 0.7 || $rr > $rrR['max'] * 1.5) {
                                        $s = 'critical'; $lbl = $rr < $rrR['min'] ? 'Apnoea / Bradypnoea' : 'Severe Tachypnoea';
                                    } elseif ($rr < $rrR['min'] || $rr > $rrR['max']) {
                                        $s = 'warning'; $lbl = $rr < $rrR['min'] ? 'Bradypnoea' : 'Tachypnoea';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $cards[] = ['icon' => '🫁', 'name' => 'Resp. Rate', 'value' => "{$rr}/min", 'label' => $lbl, 'ref' => "{$rrR['min']}–{$rrR['max']} /min ({$rrR['label']})", 'status' => $s];
                                }

                                if ($sys !== null) {
                                    if ($sys < 80 || $sys >= 180) {
                                        $s = 'critical'; $lbl = $sys < 80 ? 'Severe Hypotension' : 'Hypertensive Crisis';
                                    } elseif ($sys < 90 || $sys >= 140) {
                                        $s = 'warning'; $lbl = $sys < 90 ? 'Hypotension' : 'Hypertension';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $bpVal = $dia !== null ? "{$sys}/{$dia} mmHg" : "{$sys}/? mmHg";
                                    $cards[] = ['icon' => '🩸', 'name' => 'Blood Pressure', 'value' => $bpVal, 'label' => $lbl, 'ref' => '90–139 / 60–89 mmHg', 'status' => $s];
                                }

                                if ($spo2 !== null) {
                                    if ($spo2 < 92) {
                                        $s = 'critical'; $lbl = 'Critical Hypoxia';
                                    } elseif ($spo2 < 95) {
                                        $s = 'warning'; $lbl = 'Mild Concern';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $cards[] = ['icon' => '💨', 'name' => 'SpO₂', 'value' => "{$spo2}%", 'label' => $lbl, 'ref' => '≥95%', 'status' => $s];
                                }

                                if ($bmi !== null) {
                                    if ($bmi < 16 || $bmi >= 40) {
                                        $s = 'critical'; $lbl = $bmi < 16 ? 'Severe Underweight' : 'Severely Obese';
                                    } elseif ($bmi < 18.5 || $bmi >= 30) {
                                        $s = 'warning'; $lbl = $bmi < 18.5 ? 'Underweight' : 'Obese';
                                    } elseif ($bmi >= 25) {
                                        $s = 'warning'; $lbl = 'Overweight';
                                    } else {
                                        $s = 'normal'; $lbl = 'Normal';
                                    }
                                    $cards[] = ['icon' => '⚖️', 'name' => 'BMI', 'value' => number_format($bmi, 1), 'label' => $lbl, 'ref' => '18.5–24.9', 'status' => $s];
                                }

                                if (empty($cards)) {
                                    return new HtmlString('<p style="font-size:13px;color:#94a3b8;font-style:italic;padding:8px 0;">Enter vital signs above to see live status assessment.</p>');
                                }

                                $hasCritical = collect($cards)->contains('status', 'critical');
                                $hasWarning  = collect($cards)->contains('status', 'warning');

                                if ($hasCritical) {
                                    $banner = '<div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#fef2f2;border:2px solid #f87171;color:#991b1b;font-weight:700;font-size:13px;">🚨 CRITICAL VITALS DETECTED — Review Red Flags section and escalate immediately.</div>';
                                } elseif ($hasWarning) {
                                    $banner = '<div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#fffbeb;border:1px solid #fbbf24;color:#92400e;font-size:13px;">⚠️ Some vitals outside normal range — clinical assessment required.</div>';
                                } else {
                                    $banner = '<div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:13px;">✅ All recorded vitals within normal range for age.</div>';
                                }

                                $grid = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">';
                                foreach ($cards as $card) {
                                    [$cardBg, $cardBorder, $cardTxt, $badgeStyle, $badgeTxt] = match ($card['status']) {
                                        'critical' => ['#fef2f2', '#fca5a5', '#991b1b', 'background:#fee2e2;color:#b91c1c;', '🔴 Critical'],
                                        'warning'  => ['#fffbeb', '#fcd34d', '#92400e', 'background:#fef3c7;color:#b45309;', '⚠️ Warning'],
                                        default    => ['#f0fdf4', '#86efac', '#166534', 'background:#dcfce7;color:#15803d;', '✅ Normal'],
                                    };
                                    $grid .= '
<div style="border-radius:8px;padding:12px;background:' . $cardBg . ';border:1px solid ' . $cardBorder . ';color:' . $cardTxt . ';">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
    <span style="font-size:13px;font-weight:600;">' . $card['icon'] . ' ' . $card['name'] . '</span>
    <span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;' . $badgeStyle . '">' . $badgeTxt . '</span>
  </div>
  <p style="font-size:20px;font-weight:800;margin:0 0 2px 0;">' . $card['value'] . '</p>
  <p style="font-size:12px;font-weight:600;margin:0 0 2px 0;">' . $card['label'] . '</p>
  <p style="font-size:11px;color:#64748b;margin:0;">Ref: ' . $card['ref'] . '</p>
</div>';
                                }
                                $grid .= '</div>';

                                return new HtmlString($banner . $grid);
                            })
                            ->columnSpanFull(),
                    ]),

                // ==========================================================
                // COMPLAINTS & OBSERVATIONS
                // ==========================================================
                Forms\Components\Section::make('Any Complaints & Observations')
                    ->description('Client-reported complaints and nurse observations')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('chief_complaint')
                            ->label('Complaints')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Describe what the client is complaining about or presenting with...')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('triage_notes')
                            ->label('Nurse Observations')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Physical appearance, behaviour, anything notable on presentation...')
                            ->columnSpanFull(),
                    ]),

                // ==========================================================
                // PAIN ASSESSMENT  (before red flags — feeds into them)
                // ==========================================================
                Forms\Components\Section::make('Pain Assessment')
                    ->icon('heroicon-o-scale')
                    ->description('Pain ≥ 5 automatically flags Severe Pain in the Red Flags section below')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Forms\Components\Radio::make('pain_scale')
                            ->label('Pain Level (0–10)')
                            ->options([
                                0 => '0 — No Pain',
                                1 => '1–2 — Mild',
                                3 => '3–4 — Moderate',
                                5 => '5–6 — Severe',
                                7 => '7–8 — Very Severe',
                                9 => '9–10 — Worst Possible',
                            ])
                            ->inline()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $pain = is_numeric($state) ? (int) $state : 0;
                                // Auto-check severe pain flag when pain ≥ 5
                                $set('red_flag_severe_pain', $pain >= 5);
                                $set('risk_level', TriageResource::computeRiskLevel($get));
                            }),
                    ]),

                // ==========================================================
                // RED FLAGS
                // ==========================================================
                Forms\Components\Section::make('Red Flags')
                    ->description('Severe Pain auto-checked from pain score. Check any others that apply.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([

                        // ── Active flags live summary ──────────────────────
                        Forms\Components\Placeholder::make('active_flags_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $flags = [
                                    'red_flag_active_bleeding'       => 'Active Bleeding',
                                    'red_flag_severe_pain'           => 'Severe Pain (≥5/10)',
                                    'red_flag_seizure'               => 'Seizure / Convulsions',
                                    'red_flag_altered_consciousness' => 'Altered Consciousness',
                                    'red_flag_respiratory_distress'  => 'Respiratory Distress',
                                    'red_flag_low_oxygen'            => 'SpO₂ < 92%',
                                    'red_flag_fever_convulsions'     => 'Fever with Convulsions',
                                    'red_flag_suicidal_ideation'     => 'Suicidal Ideation / Self-Harm',
                                    'red_flag_violent_behavior'      => 'Violent / Aggressive Behavior',
                                    'red_flag_suspected_abuse'       => 'Suspected Abuse / Neglect',
                                ];

                                $active = array_filter(
                                    $flags,
                                    fn($_, $key) => (bool) $get($key),
                                    ARRAY_FILTER_USE_BOTH
                                );

                                if (empty($active)) {
                                    return new HtmlString(
                                        '<div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#94a3b8;padding:6px 0;">'
                                        . '<span style="width:8px;height:8px;border-radius:50%;background:#cbd5e1;display:inline-block;flex-shrink:0;"></span>'
                                        . 'No red flags active. Check any that apply below.'
                                        . '</div>'
                                    );
                                }

                                $pills = '';
                                foreach ($active as $label) {
                                    $pills .= '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;background:rgba(255,255,255,0.18);color:#fff;font-size:12px;font-weight:700;border:1px solid rgba(255,255,255,0.3);">'
                                        . '<span style="width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block;flex-shrink:0;"></span>'
                                        . e($label)
                                        . '</span>';
                                }

                                $count = count($active);
                                return new HtmlString(
                                    '<div style="border-radius:10px;background:#dc2626;padding:14px 18px;">'
                                    . '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">'
                                    . '<svg style="width:20px;height:20px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">'
                                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>'
                                    . '</svg>'
                                    . '<span style="color:#fff;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;">'
                                    . $count . ' Active Red Flag' . ($count > 1 ? 's' : '') . ' — Immediate attention required'
                                    . '</span>'
                                    . '</div>'
                                    . '<div style="display:flex;flex-wrap:wrap;gap:8px;">' . $pills . '</div>'
                                    . '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        // ── Flag checkboxes ────────────────────────────────
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Checkbox::make('red_flag_active_bleeding')
                                    ->label('Active Bleeding')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_severe_pain')
                                    ->label('Severe Pain (≥5/10) — auto from pain score')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_seizure')
                                    ->label('Seizure / Convulsions')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_altered_consciousness')
                                    ->label('Altered Consciousness')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_respiratory_distress')
                                    ->label('Respiratory Distress')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_low_oxygen')
                                    ->label('SpO₂ < 92% — auto from oxygen reading')
                                    ->disabled()
                                    ->dehydrated()
                                    ->live(),
                                Forms\Components\Checkbox::make('red_flag_fever_convulsions')
                                    ->label('Fever with Convulsions')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_suicidal_ideation')
                                    ->label('Suicidal Ideation / Self-Harm')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_violent_behavior')
                                    ->label('Violent / Aggressive Behavior')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                                Forms\Components\Checkbox::make('red_flag_suspected_abuse')
                                    ->label('Suspected Abuse / Neglect')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $set('risk_level', TriageResource::computeRiskLevel($get))),
                            ]),
                    ]),

                // ==========================================================
                // CONSCIOUSNESS
                // ==========================================================
                Forms\Components\Section::make('Consciousness Assessment')
                    ->icon('heroicon-o-eye')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Forms\Components\Select::make('consciousness_level')
                            ->label('Level of Consciousness')
                            ->options([
                                'alert'        => 'Alert & Oriented',
                                'drowsy'       => 'Drowsy but Responsive',
                                'confused'     => 'Confused / Disoriented',
                                'unresponsive' => 'Unresponsive',
                            ])
                            ->required()
                            ->default('alert')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) =>
                                $set('risk_level', TriageResource::computeRiskLevel($get))
                            ),
                    ]),

                // ==========================================================
                // TRIAGE DECISION
                // ==========================================================
                Forms\Components\Section::make('Triage Decision')
                    ->description('Final assessment and routing decision')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('risk_level')
                                    ->label('Risk Level')
                                    ->options([
                                        'low'      => 'Low Risk',
                                        'medium'   => 'Medium Risk',
                                        'high'     => 'High Risk',
                                        'critical' => 'Critical Risk',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->helperText('Auto-calculated from vitals, pain, consciousness, and red flags. Override if clinically indicated.')
                                    ->live(),

                                Forms\Components\Select::make('triage_status')
                                    ->label('Decision / Routing')
                                    ->options([
                                        'cleared'      => 'Cleared — Proceed to Next Stage',
                                        'medical_hold' => 'Medical Hold — Requires Clearance',
                                        'crisis'       => 'Crisis — Activate Protocol',
                                    ])
                                    ->required()
                                    ->default('cleared')
                                    ->native(false)
                                    ->live()
                                    ->helperText(function (Get $get): string {
                                        return match ($get('triage_status')) {
                                            'cleared'      => 'Client is stable and will proceed to the next stage.',
                                            'medical_hold' => 'Client is placed on hold pending medical clearance.',
                                            'crisis'       => 'Immediate emergency protocol will be activated.',
                                            default        => '',
                                        };
                                    }),
                            ]),

                        Forms\Components\Placeholder::make('decision_status_banner')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $status = $get('triage_status');
                                $risk   = $get('risk_level');
                                if (!$status) return new HtmlString('');
                                [$bgColor, $borderColor, $iconPath, $text] = match ($status) {
                                    'crisis' => [
                                        '#dc2626', '#b91c1c',
                                        'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
                                        '<strong>CRISIS PROTOCOL ACTIVATED</strong> — Emergency team must be notified immediately.',
                                    ],
                                    'medical_hold' => [
                                        '#d97706', '#b45309',
                                        'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                        '<strong>Medical Hold</strong> — Client awaiting medical clearance before proceeding.',
                                    ],
                                    default => [
                                        '#059669', '#047857',
                                        'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                                        '<strong>Cleared</strong> — Client is stable and ready for the next stage.',
                                    ],
                                };
                                $riskLabel = $risk ? ' &nbsp;&middot;&nbsp; Risk: <strong>' . ucfirst($risk) . '</strong>' : '';
                                return new HtmlString(
                                    '<div style="display:flex;align-items:center;gap:12px;border-radius:8px;padding:12px 16px;color:#fff;font-size:13px;background:' . $bgColor . ';border:1px solid ' . $borderColor . ';">'
                                    . '<svg style="width:20px;height:20px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' . $iconPath . '"/></svg>'
                                    . '<span>' . $text . $riskLabel . '</span>'
                                    . '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit.visit_number')->label('Visit #')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->sortable(),
                Tables\Columns\TextColumn::make('risk_level')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'low'      => 'success',
                        'medium'   => 'warning',
                        'high', 'critical' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state ?? '—')),
                Tables\Columns\TextColumn::make('triage_status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'cleared'      => 'success',
                        'medical_hold' => 'warning',
                        'crisis'       => 'danger',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'cleared'      => 'Cleared',
                        'medical_hold' => 'Medical Hold',
                        'crisis'       => 'Crisis',
                        default        => ucfirst($state ?? '—'),
                    }),
                Tables\Columns\TextColumn::make('temperature')
                    ->label('Temp')
                    ->suffix('°C')
                    ->color(fn($state) => match (true) {
                        $state === null => 'gray',
                        $state < 35 || $state > 39.5 => 'danger',
                        $state < 36.0 || $state > 38.0 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('heart_rate')
                    ->label('HR')
                    ->suffix(' bpm')
                    ->color(fn($state) => match (true) {
                        $state === null => 'gray',
                        $state < 45 || $state > 140 => 'danger',
                        $state < 60 || $state > 100 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('systolic_bp')
                    ->label('BP')
                    ->formatStateUsing(fn($state, $record) => $state ? "{$state}/{$record->diastolic_bp}" : '—')
                    ->color(fn($state) => match (true) {
                        $state === null => 'gray',
                        $state < 80 || $state >= 180 => 'danger',
                        $state < 90 || $state >= 140 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('oxygen_saturation')
                    ->label('SpO₂')
                    ->suffix('%')
                    ->color(fn($state) => match (true) {
                        $state === null => 'gray',
                        $state < 92 => 'danger',
                        $state < 95 => 'warning',
                        default => 'success',
                    }),
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
            'index'  => Pages\ListTriages::route('/'),
            'create' => Pages\CreateTriage::route('/create'),
            'edit'   => Pages\EditTriage::route('/{record}/edit'),
            'view'   => Pages\ViewTriage::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin', 'triage_nurse']);
    }
}
