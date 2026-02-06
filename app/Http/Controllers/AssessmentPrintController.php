<?php

namespace App\Http\Controllers;

use App\Models\AssessmentFormResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Assessment Print Controller
 * 
 * Handles printing of assessment forms as PDF
 */
class AssessmentPrintController extends Controller
{
    /**
     * Print assessment as PDF
     */
    public function print(AssessmentFormResponse $assessment)
    {
        // Load relationships
        $assessment->load(['schema', 'client', 'visit', 'autoReferrals']);

        // Get response data
        $responseData = $assessment->response_data ?? [];
        $schema = $assessment->schema;

        // Prepare data for PDF
        $data = [
            'assessment' => $assessment,
            'schema' => $schema,
            'client' => $assessment->client,
            'visit' => $assessment->visit,
            'responseData' => $responseData,
            'formattedData' => $this->formatResponseData($responseData, $schema),
            'autoReferrals' => $assessment->autoReferrals,
            'printedAt' => now(),
            'printedBy' => auth()->user(),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('assessments.print', $data)
            ->setPaper('a4')
            ->setOption('margin-top', '10mm')
            ->setOption('margin-bottom', '10mm')
            ->setOption('margin-left', '10mm')
            ->setOption('margin-right', '10mm');

        // Download filename
        $filename = sprintf(
            '%s_%s_%s.pdf',
            str_replace(' ', '_', $schema->name ?? 'Assessment'),
            $assessment->client->uci ?? 'Client',
            now()->format('Y-m-d')
        );

        return $pdf->stream($filename);
    }

    /**
     * Format response data for display
     */
    protected function formatResponseData(array $responseData, $schema): array
    {
        $formatted = [];

        if (!$schema || !isset($schema->schema['sections'])) {
            // Simple key-value format
            foreach ($responseData as $key => $value) {
                if (is_array($value)) {
                    $formatted[$key] = json_encode($value, JSON_PRETTY_PRINT);
                } else {
                    $formatted[$key] = $value;
                }
            }
            return $formatted;
        }

        // Format according to schema sections
        foreach ($schema->schema['sections'] as $section) {
            $sectionTitle = $section['title'] ?? 'Untitled Section';
            $formatted[$sectionTitle] = [];

            foreach ($section['fields'] as $field) {
                $fieldId = $field['id'] ?? null;
                if (!$fieldId) continue;

                $label = $field['label'] ?? $fieldId;
                $value = $responseData[$fieldId] ?? null;

                // Format value based on field type
                $formatted[$sectionTitle][$label] = $this->formatFieldValue($value, $field);
            }
        }

        return $formatted;
    }

    /**
     * Format individual field value
     */
    protected function formatFieldValue($value, array $field): string
    {
        if (is_null($value) || $value === '') {
            return 'Not provided';
        }

        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'select':
            case 'radio':
                // Try to get label from options
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $option) {
                        if (isset($option['value']) && $option['value'] == $value) {
                            return $option['label'] ?? $value;
                        }
                    }
                }
                return $value;

            case 'checkbox_list':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value;

            case 'date':
                try {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
                } catch (\Exception $e) {
                    return $value;
                }

            default:
                if (is_array($value)) {
                    return json_encode($value, JSON_PRETTY_PRINT);
                }
                return $value;
        }
    }
}