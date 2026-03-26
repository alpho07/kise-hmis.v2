<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\ClientMedicalHistory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditIntakeAssessment extends EditRecord
{
    protected static string $resource = IntakeAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $med = ClientMedicalHistory::where('client_id', $this->record->client_id)->first();
        $peri = $med?->perinatal_history ?? [];
        $imm  = $med?->immunization_records ?? [];

        $data['peri_pregnancy_complications'] = $peri['pregnancy_complications'] ?? [];
        $data['peri_place_of_birth']          = $peri['place_of_birth']          ?? null;
        $data['peri_mode_of_delivery']        = $peri['mode_of_delivery']        ?? null;
        $data['peri_gestation_weeks']         = $peri['gestation_weeks']         ?? null;
        $data['peri_birth_weight_kg']         = $peri['birth_weight_kg']         ?? null;
        $data['peri_neonatal_care']           = $peri['neonatal_care']           ?? [];
        $data['peri_early_medical_issues']    = $peri['early_medical_issues']    ?? [];
        $data['peri_developmental_concerns']  = $peri['developmental_concerns']  ?? [];

        $data['imm_epi_status']              = $imm['epi_status']               ?? [];
        $data['imm_epi_card_seen']           = $imm['epi_card_seen']            ?? null;
        $data['imm_missed_doses']            = $imm['missed_doses']             ?? null;
        $data['imm_missed_doses_which']      = $imm['missed_doses_which']       ?? null;
        $data['imm_recent_illness_post_vaccine'] = $imm['recent_illness_post_vaccine'] ?? null;
        $data['imm_recent_illness_notes']    = $imm['recent_illness_notes']     ?? null;
        $data['med_immunization_status']     = $med?->immunization_status       ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        // Use $this->data (raw Livewire property) instead of form->getState() so
        // fields in conditionally-visible sections (E3/E4, hidden when visitId=0 in
        // tests or when age can't be resolved) are always persisted correctly.
        $data     = $this->data;
        $clientId = $this->record->client_id;

        // ── E3 perinatal ──────────────────────────────────────────────────────
        $periComplications = $data['peri_pregnancy_complications'] ?? [];
        if (!empty($data['peri_pregnancy_complications_other'])) {
            $periComplications = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_pregnancy_complications_other'] : $v,
                $periComplications
            );
        }
        $neonatalCare = $data['peri_neonatal_care'] ?? [];
        if (!empty($data['peri_neonatal_care_other'])) {
            $neonatalCare = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_neonatal_care_other'] : $v,
                $neonatalCare
            );
        }
        $earlyMedical = $data['peri_early_medical_issues'] ?? [];
        if (!empty($data['peri_early_medical_issues_other'])) {
            $earlyMedical = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_early_medical_issues_other'] : $v,
                $earlyMedical
            );
        }
        $devConcerns = $data['peri_developmental_concerns'] ?? [];
        if (!empty($data['peri_developmental_concerns_other'])) {
            $devConcerns = array_map(
                fn($v) => $v === 'other' ? 'other: ' . $data['peri_developmental_concerns_other'] : $v,
                $devConcerns
            );
        }
        $placeOfBirth = ($data['peri_place_of_birth'] ?? null) === 'other'
            ? 'other: ' . ($data['peri_place_of_birth_other'] ?? 'unspecified')
            : ($data['peri_place_of_birth'] ?? null);

        $perinatHistory = array_filter([
            'pregnancy_complications' => $periComplications ?: null,
            'place_of_birth'          => $placeOfBirth,
            'mode_of_delivery'        => $data['peri_mode_of_delivery']  ?? null,
            'gestation_weeks'         => $data['peri_gestation_weeks']   ?? null,
            'birth_weight_kg'         => $data['peri_birth_weight_kg']   ?? null,
            'neonatal_care'           => $neonatalCare ?: null,
            'early_medical_issues'    => $earlyMedical ?: null,
            'developmental_concerns'  => $devConcerns ?: null,
        ], fn($v) => $v !== null && $v !== []);

        // ── E4 immunization ───────────────────────────────────────────────────
        $immunizationRecords = array_filter([
            'epi_status'                  => $data['imm_epi_status']                  ?? [],
            'epi_card_seen'               => $data['imm_epi_card_seen']               ?? null,
            'missed_doses'                => $data['imm_missed_doses']                ?? null,
            'missed_doses_which'          => $data['imm_missed_doses_which']          ?? null,
            'recent_illness_post_vaccine' => $data['imm_recent_illness_post_vaccine'] ?? null,
            'recent_illness_notes'        => $data['imm_recent_illness_notes']        ?? null,
        ], fn($v) => $v !== null && $v !== []);

        ClientMedicalHistory::updateOrCreate(
            ['client_id' => $clientId],
            [
                'perinatal_history'    => $perinatHistory ?: null,
                'immunization_records' => $immunizationRecords ?: null,
                'immunization_status'  => $data['med_immunization_status'] ?? null,
                'developmental_concerns_notes' => $data['developmental_history'] ?? null,
            ]
        );
    }
}