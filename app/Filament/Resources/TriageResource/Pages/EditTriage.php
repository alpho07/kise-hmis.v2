<?php

namespace App\Filament\Resources\TriageResource\Pages;

use App\Filament\Resources\TriageResource;
use App\Models\TriageRedFlag;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTriage extends EditRecord
{
    protected static string $resource = TriageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * Hydrate the red_flag_* virtual checkbox fields from the triage_red_flags relation.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $redFlagMap = [
            'Active Bleeding'         => 'red_flag_active_bleeding',
            'Severe Pain'             => 'red_flag_severe_pain',
            'Seizure/Convulsions'     => 'red_flag_seizure',
            'Altered Consciousness'   => 'red_flag_altered_consciousness',
            'Respiratory Distress'    => 'red_flag_respiratory_distress',
            'SpO₂ < 92%'             => 'red_flag_low_oxygen',
            'Fever with Convulsions'  => 'red_flag_fever_convulsions',
            'Suicidal Ideation'       => 'red_flag_suicidal_ideation',
            'Violent Behavior'        => 'red_flag_violent_behavior',
            'Suspected Abuse/Neglect' => 'red_flag_suspected_abuse',
        ];

        $activeFlags = $this->record->redFlags()->pluck('flag_name')->toArray();

        foreach ($redFlagMap as $flagName => $field) {
            $data[$field] = in_array($flagName, $activeFlags);
        }

        return $data;
    }

    /**
     * Strip virtual red_flag_* fields before saving (they're not triages columns).
     * Cache them for afterSave().
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $redFlagFields = [
            'red_flag_active_bleeding'       => ['name' => 'Active Bleeding',         'category' => 'trauma'],
            'red_flag_severe_pain'           => ['name' => 'Severe Pain',             'category' => 'other'],
            'red_flag_seizure'               => ['name' => 'Seizure/Convulsions',     'category' => 'neurological'],
            'red_flag_altered_consciousness' => ['name' => 'Altered Consciousness',   'category' => 'neurological'],
            'red_flag_respiratory_distress'  => ['name' => 'Respiratory Distress',    'category' => 'respiratory'],
            'red_flag_low_oxygen'            => ['name' => 'SpO₂ < 92%',             'category' => 'respiratory'],
            'red_flag_fever_convulsions'     => ['name' => 'Fever with Convulsions',  'category' => 'neurological'],
            'red_flag_suicidal_ideation'     => ['name' => 'Suicidal Ideation',       'category' => 'behavioral'],
            'red_flag_violent_behavior'      => ['name' => 'Violent Behavior',        'category' => 'behavioral'],
            'red_flag_suspected_abuse'       => ['name' => 'Suspected Abuse/Neglect', 'category' => 'behavioral'],
        ];

        $this->pendingRedFlags = [];

        foreach ($redFlagFields as $field => $meta) {
            if (!empty($data[$field])) {
                $this->pendingRedFlags[] = $meta;
            }
            unset($data[$field]);
        }

        // Also strip other virtual fields
        unset(
            $data['safeguarding_risk'],
            $data['safeguarding_notes'],
            $data['child_accompanied'],
            $data['computed_risk'],
            $data['client_age'],
            $data['client_dob'],
            $data['bmi_category'],
        );

        return $data;
    }

    /**
     * Sync triage_red_flags: delete existing, insert current selection.
     */
    protected function afterSave(): void
    {
        $triage = $this->record;

        $triage->redFlags()->delete();

        foreach ($this->pendingRedFlags ?? [] as $meta) {
            TriageRedFlag::create([
                'triage_id'    => $triage->id,
                'flag_name'    => $meta['name'],
                'flag_category'=> $meta['category'],
                'description'  => $meta['name'],
                'severity'     => 'critical',
            ]);
        }
    }
}
