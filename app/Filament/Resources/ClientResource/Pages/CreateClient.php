<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate UCI (Unique Client Identifier)
        $data['uci'] = $this->generateUCI();
        
        // Auto-determine client type
        $data['client_type'] = $this->determineClientType($data);
        
        // Set registration date if not provided
        if (empty($data['registration_date'])) {
            $data['registration_date'] = now();
        }
        
        // If unknown_dob is true, ensure we have calculated DOB from estimated age
        if (!empty($data['unknown_dob']) && !empty($data['estimated_age'])) {
            $data['date_of_birth'] = now()->subYears($data['estimated_age'])->format('Y-m-d');
        }
        
        // Remove temporary field
        unset($data['unknown_dob']);
        
        return $data;
    }

    /**
     * Generate UCI in format: KISE/A/00000X/YEAR
     * 
     * Format Breakdown:
     * - KISE: Organization prefix
     * - A: Category (A for all clients)
     * - 00000X: Sequential number (last ID + 1, left-padded to 6 digits)
     * - YEAR: Current year (4 digits)
     * 
     * Example: KISE/A/000123/2024
     */
    protected function generateUCI(): string
    {
        $year = now()->year;
        
        // Get the last client ID from database
        $lastClient = \App\Models\Client::orderBy('id', 'desc')->first();
        
        // Calculate next sequential number (last ID + 1)
        $nextId = $lastClient ? ($lastClient->id + 1) : 1;
        
        // Left-pad to 6 digits with zeros
        $paddedId = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        
        // Format: KISE/A/00000X/YEAR
        return sprintf('KISE/A/%s/%s', $paddedId, $year);
    }

    protected function determineClientType(array $data): string
    {
        // Check registration source to determine client type
        $source = $data['registration_source'] ?? 'main_center';
        
        // If from satellite or outreach, it's old-new
        if (in_array($source, ['satellite', 'outreach'])) {
            return 'old_new';
        }
        
        // Check if this phone/name combination exists
        // If exists, it's returning, otherwise new
        if (!empty($data['phone_primary'])) {
            $existingClient = \App\Models\Client::where('phone_primary', $data['phone_primary'])
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->exists();
            
            if ($existingClient) {
                return 'returning';
            }
        }
        
        // Default to new client
        return 'new';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Client registered successfully';
    }

    protected function afterCreate(): void
    {
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->log('Client registered with UCI: ' . $this->record->uci . ' (Type: ' . $this->record->client_type . ')');
    }
}