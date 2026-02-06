<?php

namespace App\Services;

use App\Models\AssessmentFormSchema;
use App\Models\Visit;
use App\Models\Client;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

/**
 * Enhanced Dynamic Form Builder
 * 
 * Features:
 * - Auto-loads client & visit data
 * - Cascading dropdowns (County → Sub-County → Ward)
 * - Service selection with cost preview
 * - Billing integration
 */
class DynamicFormBuilder
{
    protected ?Visit $visit = null;
    protected ?Client $client = null;

    /**
     * Build complete form from schema with visit context
     * 
     * @param AssessmentFormSchema $schema The form schema to build
     * @param int|null $visitId Visit ID from URL (for create) or null (for edit/view)
     * @param mixed $record The current record being edited (for edit/view pages)
     */
    public static function buildForm(AssessmentFormSchema $schema, ?int $visitId = null, $record = null): array
    {
        $instance = new self();
        
        // Determine visit_id - priority: record > URL parameter
        if ($record && isset($record->visit_id)) {
            $visitId = $record->visit_id;
        } elseif (!$visitId) {
            $visitId = request()->query('visit_id');
        }
        
        // Load visit and client if we have visit_id
        if ($visitId) {
            $instance->visit = Visit::with(['client', 'triage'])->find($visitId);
            $instance->client = $instance->visit?->client;
        }

        $components = [];

        // Add hidden fields for tracking - MUST BE DEHYDRATED TO SAVE
        $components[] = Forms\Components\Hidden::make('form_schema_id')
            ->default($schema->id)
            ->dehydrated(true);

        $components[] = Forms\Components\Hidden::make('visit_id')
            ->default($visitId)
            ->dehydrated(true);

        $components[] = Forms\Components\Hidden::make('client_id')
            ->default($instance->client?->id)
            ->dehydrated(true);

        $components[] = Forms\Components\Hidden::make('branch_id')
            ->default($instance->visit?->branch_id ?? auth()->user()->branch_id)
            ->dehydrated(true);

        $components[] = Forms\Components\Hidden::make('status')
            ->default('in_progress')
            ->dehydrated(true);

        // Build all sections
        foreach ($schema->schema['sections'] ?? [] as $section) {
            $sectionComponent = $instance->buildSection($section, $schema);
            if ($sectionComponent) {
                $components[] = $sectionComponent;
            }
        }

        return $components;
    }

    /**
     * Build a section
     */
    protected function buildSection(array $sectionConfig, AssessmentFormSchema $schema): ?Forms\Components\Section
    {
        $fields = [];

        foreach ($sectionConfig['fields'] ?? [] as $fieldConfig) {
            $field = $this->buildField($fieldConfig, $schema);
            if ($field) {
                $fields[] = $field;
            }
        }

        if (empty($fields)) {
            return null;
        }

        $section = Forms\Components\Section::make($sectionConfig['title'] ?? 'Section')
            ->schema($fields)
            ->columns($sectionConfig['columns'] ?? 1);

        // Apply section configuration
        if (isset($sectionConfig['description'])) {
            $section->description($sectionConfig['description']);
        }

        if (isset($sectionConfig['icon'])) {
            $section->icon($sectionConfig['icon']);
        }

        if ($sectionConfig['collapsible'] ?? false) {
            $section->collapsible();
        }

        if ($sectionConfig['collapsed'] ?? false) {
            $section->collapsed();
        }

        return $section;
    }

    /**
     * Build a field based on type
     */
    protected function buildField(array $fieldConfig, AssessmentFormSchema $schema)
    {
        $fieldType = $fieldConfig['type'] ?? 'text';
        $fieldId = 'response_data.' . ($fieldConfig['id'] ?? Str::random(8));

        $component = match($fieldType) {
            'text' => $this->buildTextField($fieldId, $fieldConfig),
            'textarea' => $this->buildTextareaField($fieldId, $fieldConfig),
            'select' => $this->buildSelectField($fieldId, $fieldConfig),
            'checkbox_list' => $this->buildCheckboxListField($fieldId, $fieldConfig),
            'radio' => $this->buildRadioField($fieldId, $fieldConfig),
            'toggle' => $this->buildToggleField($fieldId, $fieldConfig),
            'date' => $this->buildDateField($fieldId, $fieldConfig),
            'file' => $this->buildFileField($fieldId, $fieldConfig),
            'placeholder' => $this->buildPlaceholderField($fieldId, $fieldConfig),
            'repeater' => $this->buildRepeaterField($fieldId, $fieldConfig, $schema),
            default => null,
        };

        if (!$component) {
            return null;
        }

        // Apply common configurations
        $this->applyCommonConfig($component, $fieldConfig);

        return $component;
    }

    /**
     * Build text input field
     */
    protected function buildTextField(string $fieldId, array $config)
    {
        $field = Forms\Components\TextInput::make($fieldId)
            ->label($config['label'] ?? 'Field');

        if (isset($config['placeholder'])) {
            $field->placeholder($config['placeholder']);
        }

        if (isset($config['inputType'])) {
            match($config['inputType']) {
                'email' => $field->email(),
                'tel' => $field->tel(),
                'number' => $field->numeric(),
                'url' => $field->url(),
                default => null,
            };
        }

        if (isset($config['maxLength'])) {
            $field->maxLength($config['maxLength']);
        }

        return $field;
    }

    /**
     * Build textarea field
     */
    protected function buildTextareaField(string $fieldId, array $config)
    {
        return Forms\Components\Textarea::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->rows($config['rows'] ?? 3)
            ->placeholder($config['placeholder'] ?? null)
            ->maxLength($config['maxLength'] ?? null);
    }

    /**
     * Build select field with cascading support
     */
    protected function buildSelectField(string $fieldId, array $config)
    {
        $field = Forms\Components\Select::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->searchable($config['searchable'] ?? false)
            ->native(false);

        // Handle data source (models)
        if (isset($config['dataSource'])) {
            $this->applyDataSource($field, $config['dataSource'], $fieldId);
        } else {
            // Static options
            $field->options($this->getOptions($config));
        }

        if (isset($config['default'])) {
            $field->default($config['default']);
        }

        if ($config['multiple'] ?? false) {
            $field->multiple();
        }

        // Live for cascading
        if ($config['live'] ?? false) {
            $field->live(onBlur: false);
            
            // Handle reset actions for cascading
            if (isset($config['onChangeActions'])) {
                $field->afterStateUpdated(function (Set $set) use ($config) {
                    foreach ($config['onChangeActions'] as $action) {
                        if ($action['action'] === 'reset') {
                            $set('response_data.' . $action['target'], null);
                        }
                    }
                });
            }
        }

        // Disabled state for cascading
        if (isset($config['disabled']) && str_contains($config['disabled'], 'if')) {
            // Extract the condition: "if county_id == null"
            preg_match('/if\s+(\w+)\s+==\s+null/', $config['disabled'], $matches);
            if (!empty($matches[1])) {
                $dependsOn = $matches[1];
                $field->disabled(fn (Get $get) => empty($get('response_data.' . $dependsOn)));
            }
        }

        return $field;
    }

    /**
     * Apply data source to select field (for County, Sub-County, Ward, Service)
     */
    protected function applyDataSource(Forms\Components\Select $field, array $dataSource, string $fieldId)
    {
        $modelClass = 'App\\Models\\' . $dataSource['model'];
        
        // Check if model exists
        if (!class_exists($modelClass)) {
            return;
        }
        
        // Check if it needs filtering (cascading)
        if (isset($dataSource['filterBy']) && is_array($dataSource['filterBy'])) {
            // Validate filterBy has required keys
            if (!isset($dataSource['filterBy']['field']) || !isset($dataSource['filterBy']['sourceField'])) {
                // Invalid filterBy config, load all records instead
                $field->options(
                    $modelClass::pluck($dataSource['labelField'] ?? 'name', $dataSource['valueField'] ?? 'id')->toArray()
                );
                return;
            }
            
            $filterDbField = $dataSource['filterBy']['field'];  // DB column name (e.g., 'county_id')
            $filterSourceField = $dataSource['filterBy']['sourceField'];  // Form field name (e.g., 'county_id')
            
            $field->options(function (Get $get) use ($modelClass, $dataSource, $filterDbField, $filterSourceField) {
                $filterValue = $get('response_data.' . $filterSourceField);
                
                if (empty($filterValue)) {
                    return [];
                }
                
                try {
                    return $modelClass::where($filterDbField, $filterValue)
                        ->pluck($dataSource['labelField'] ?? 'name', $dataSource['valueField'] ?? 'id')
                        ->toArray();
                } catch (\Exception $e) {
                    // Log error but don't break the form
                    logger()->error('DynamicFormBuilder dataSource error: ' . $e->getMessage());
                    return [];
                }
            });
        } else {
            // No filtering - load all
            try {
                $field->options(
                    $modelClass::pluck($dataSource['labelField'] ?? 'name', $dataSource['valueField'] ?? 'id')->toArray()
                );
            } catch (\Exception $e) {
                logger()->error('DynamicFormBuilder dataSource error: ' . $e->getMessage());
                $field->options([]);
            }
        }
    }

    /**
     * Build checkbox list field
     */
    protected function buildCheckboxListField(string $fieldId, array $config)
    {
        $field = Forms\Components\CheckboxList::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->options($this->getOptions($config))
            ->columns($config['columns'] ?? 1);

        if ($config['live'] ?? false) {
            $field->live();
        }

        return $field;
    }

    /**
     * Build radio field
     */
    protected function buildRadioField(string $fieldId, array $config)
    {
        return Forms\Components\Radio::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->options($this->getOptions($config))
            ->inline($config['inline'] ?? false);
    }

    /**
     * Build toggle field
     */
    protected function buildToggleField(string $fieldId, array $config)
    {
        $field = Forms\Components\Toggle::make($fieldId)
            ->label($config['label'] ?? 'Field');

        if (isset($config['default'])) {
            $field->default($config['default']);
        }

        if ($config['live'] ?? false) {
            $field->live();
        }

        return $field;
    }

    /**
     * Build date field
     */
    protected function buildDateField(string $fieldId, array $config)
    {
        $field = Forms\Components\DatePicker::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->native(false);

        if (isset($config['minDate'])) {
            if ($config['minDate'] === 'today') {
                $field->minDate(now());
            }
        }

        return $field;
    }

    /**
     * Build file upload field
     */
    protected function buildFileField(string $fieldId, array $config)
    {
        $field = Forms\Components\FileUpload::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->directory($config['directory'] ?? 'uploads')
            ->multiple($config['multiple'] ?? false);

        if (isset($config['acceptedFileTypes'])) {
            $field->acceptedFileTypes($config['acceptedFileTypes']);
        }

        return $field;
    }

    /**
     * Build placeholder field (auto-loaded from visit/client)
     */
    protected function buildPlaceholderField(string $fieldId, array $config)
    {
        $value = $this->getPlaceholderValue($config['id']);
        
        return Forms\Components\Placeholder::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->content(new HtmlString(
                '<div class="text-sm font-medium text-gray-900 dark:text-white">' . 
                e($value) . 
                '</div>'
            ));
    }

    /**
     * Get placeholder value from visit/client
     */
    protected function getPlaceholderValue(string $fieldId): string
    {
        if (!$this->visit || !$this->client) {
            return 'N/A';
        }

        return match($fieldId) {
            'client_uci' => $this->client->uci ?? 'N/A',
            'client_name' => $this->client->full_name ?? 'N/A',
            'client_age' => $this->client->age ? $this->client->age . ' years' : 'N/A',
            'client_gender' => $this->client->sex ? ucfirst($this->client->sex) : 'N/A',
            'visit_number' => $this->visit->visit_number ?? 'N/A',
            'triage_status' => $this->visit->triage 
                ? ucfirst($this->visit->triage->risk_level ?? 'Assessed') 
                : 'Not triaged',
            'triage_vitals' => $this->getTriageVitals(),
            'triage_link' => $this->getTriageLink(),
            default => 'N/A',
        };
    }

    /**
     * Get formatted triage vitals
     */
    protected function getTriageVitals(): string
    {
        if (!$this->visit || !$this->visit->triage) {
            return 'N/A';
        }

        $triage = $this->visit->triage;
        $vitals = [];

        if ($triage->blood_pressure_systolic && $triage->blood_pressure_diastolic) {
            $vitals[] = 'BP: ' . $triage->blood_pressure_systolic . '/' . $triage->blood_pressure_diastolic;
        }

        if ($triage->temperature) {
            $vitals[] = 'Temp: ' . $triage->temperature . '°C';
        }

        if ($triage->heart_rate) {
            $vitals[] = 'HR: ' . $triage->heart_rate;
        }

        if ($triage->oxygen_saturation) {
            $vitals[] = 'O2: ' . $triage->oxygen_saturation . '%';
        }

        return !empty($vitals) ? implode(' | ', $vitals) : 'N/A';
    }

    /**
     * Get triage link
     */
    protected function getTriageLink(): string
    {
        if (!$this->visit || !$this->visit->triage) {
            return 'No triage record';
        }

        $triageId = $this->visit->triage->id;
        $url = "/admin/triages/{$triageId}";
        
        return "<a href='{$url}' target='_blank' class='text-primary-600 hover:text-primary-700 underline font-semibold'>
            View Triage Assessment →
        </a>";
    }

    /**
     * Build repeater field
     */
    protected function buildRepeaterField(string $fieldId, array $config, AssessmentFormSchema $schema)
    {
        $subFields = [];
        
        foreach ($config['fields'] ?? [] as $subFieldConfig) {
            $subField = $this->buildField($subFieldConfig, $schema);
            if ($subField) {
                $subFields[] = $subField;
            }
        }

        $repeater = Forms\Components\Repeater::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->schema($subFields);

        if ($config['collapsible'] ?? false) {
            $repeater->collapsible();
        }

        if (isset($config['defaultItems'])) {
            $repeater->defaultItems($config['defaultItems']);
        }

        return $repeater;
    }

    /**
     * Get options for select/radio/checkbox fields
     */
    protected function getOptions(array $config): array
    {
        if (isset($config['options'])) {
            $options = [];
            foreach ($config['options'] as $option) {
                $options[$option['value']] = $option['label'];
            }
            return $options;
        }

        return [];
    }

    /**
     * Apply common configurations to field
     */
    protected function applyCommonConfig($component, array $config): void
    {
        // Required validation
        if (($config['validation']['required'] ?? false) === true) {
            $component->required();
        }

        // Validation rules
        if (isset($config['validation']['rules'])) {
            foreach ($config['validation']['rules'] as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'regex:')) {
                    $component->rule($rule);
                }
            }
        }

        // Helper text
        if (isset($config['helperText'])) {
            $component->helperText($config['helperText']);
        }

        // Prefix icon
        if (isset($config['prefixIcon'])) {
            $component->prefixIcon($config['prefixIcon']);
        }

        // Column span
        if (isset($config['columnSpan'])) {
            $component->columnSpan($config['columnSpan']);
        }

        // Conditional display
        if (isset($config['conditionalDisplay'])) {
            $this->applyConditionalDisplay($component, $config['conditionalDisplay']);
        }
    }

    /**
     * Apply conditional display logic
     */
    protected function applyConditionalDisplay($component, array $condition): void
    {
        // Validate required keys exist
        if (!isset($condition['field']) || !isset($condition['operator'])) {
            // Invalid condition - make field always visible
            return;
        }
        
        $sourceField = 'response_data.' . $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        $component->visible(function (Get $get) use ($sourceField, $operator, $value) {
            $fieldValue = $get($sourceField);

            try {
                return match($operator) {
                    'equals' => $fieldValue == $value,
                    'not_equals' => $fieldValue != $value,
                    'contains' => is_array($fieldValue) && in_array($value, $fieldValue),
                    'not_contains' => is_array($fieldValue) && !in_array($value, $fieldValue),
                    'in' => is_array($value) && in_array($fieldValue, $value),
                    'not_in' => is_array($value) && !in_array($fieldValue, $value),
                    'greater_than' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue > $value,
                    'less_than' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue < $value,
                    default => true,  // Unknown operator - show field
                };
            } catch (\Exception $e) {
                // Log error but don't break the form
                logger()->error('DynamicFormBuilder conditional display error: ' . $e->getMessage());
                return true;  // Show field on error
            }
        });
    }
}