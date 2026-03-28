<?php

namespace App\Filament\Resources\IntakeAssessmentResource\Pages;

use App\Filament\Resources\IntakeAssessmentResource;
use App\Models\ClientMedicalHistory;
use App\Models\ClientSocioDemographic;
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

        // ── Section D socio-demographics ─────────────────────────────────────
        $socio = ClientSocioDemographic::where('client_id', $this->record->client_id)->first();
        if ($socio) {
            $caregiverRaw   = $socio->primary_caregiver ?? null;
            $caregiverOther = str_starts_with($caregiverRaw ?? '', 'other: ');
            $langRaw        = $socio->primary_language ?? null;
            $langOther      = str_starts_with($langRaw ?? '', 'other: ');

            $data['socio_marital_status']        = $socio->marital_status;
            $data['socio_marital_other']         = $socio->marital_status_other;
            $data['socio_living_arrangement']    = $socio->living_arrangement;
            $data['socio_living_other']          = $socio->living_arrangement_other;
            $data['socio_household_size']        = $socio->household_size;
            $data['socio_primary_caregiver']     = $caregiverOther ? 'other' : $caregiverRaw;
            $data['socio_caregiver_other']       = $caregiverOther ? substr($caregiverRaw, 7) : null;
            $data['socio_source_of_support']     = $socio->source_of_support ?? [];
            $data['socio_other_support']         = $socio->other_support_source;
            $data['socio_school_enrolled']       = $socio->school_enrolled;
            $data['socio_primary_language']      = $langOther ? 'other' : $langRaw;
            $data['socio_language_other']        = $langOther ? substr($langRaw, 7) : null;
            $data['socio_other_languages']       = $socio->other_languages[0] ?? null;
            $data['socio_accessibility_at_home'] = $socio->accessibility_at_home;
            $data['socio_notes']                 = $socio->socio_notes;
        }

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

        // ── Section D socio-demographics ─────────────────────────────────────
        $primaryCaregiver = ($data['socio_primary_caregiver'] ?? null) === 'other'
            ? 'other: ' . ($data['socio_caregiver_other'] ?? 'unspecified')
            : ($data['socio_primary_caregiver'] ?? null);
        $primaryLanguage = ($data['socio_primary_language'] ?? null) === 'other'
            ? 'other: ' . ($data['socio_language_other'] ?? 'unspecified')
            : ($data['socio_primary_language'] ?? null);

        ClientSocioDemographic::updateOrCreate(
            ['client_id' => $clientId],
            [
                'marital_status'           => $data['socio_marital_status']        ?? null,
                'marital_status_other'     => $data['socio_marital_other']         ?? null,
                'living_arrangement'       => $data['socio_living_arrangement']    ?? null,
                'living_arrangement_other' => $data['socio_living_other']          ?? null,
                'household_size'           => $data['socio_household_size']        ?? null,
                'primary_caregiver'        => $primaryCaregiver,
                'source_of_support'        => $data['socio_source_of_support']     ?? [],
                'other_support_source'     => $data['socio_other_support']         ?? null,
                'school_enrolled'          => $data['socio_school_enrolled']       ?? null,
                'primary_language'         => $primaryLanguage,
                'other_languages'          => !empty($data['socio_other_languages']) ? [$data['socio_other_languages']] : [],
                'accessibility_at_home'    => $data['socio_accessibility_at_home'] ?? null,
                'socio_notes'              => $data['socio_notes']                 ?? null,
            ]
        );
    }
}