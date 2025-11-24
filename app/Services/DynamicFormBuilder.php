<?php

namespace App\Services;

use App\Models\AssessmentFormSchema;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class DynamicFormBuilder
{
    /**
     * Build complete form from schema
     */
    public static function buildForm(AssessmentFormSchema $schema): array
    {
        $components = [];

        // Add hidden fields for tracking
        $components[] = Forms\Components\Hidden::make('form_schema_id')
            ->default($schema->id);

        $components[] = Forms\Components\Hidden::make('status')
            ->default('in_progress');

        // Build all sections
        foreach ($schema->schema['sections'] ?? [] as $section) {
            $sectionComponent = self::buildSection($section, $schema);
            if ($sectionComponent) {
                $components[] = $sectionComponent;
            }
        }

        return $components;
    }

    /**
     * Build a section
     */
    protected static function buildSection(array $sectionConfig, AssessmentFormSchema $schema): ?Forms\Components\Section
    {
        $fields = [];

        foreach ($sectionConfig['fields'] ?? [] as $fieldConfig) {
            $field = self::buildField($fieldConfig, $schema);
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

        // Apply conditional display for section
        if (isset($sectionConfig['conditionalDisplay'])) {
            $section->visible(function (Get $get, $record) use ($sectionConfig) {
                return self::evaluateConditional($get, $sectionConfig['conditionalDisplay'], $record);
            });
        }

        return $section;
    }

    /**
     * Build a field based on type
     */
    protected static function buildField(array $fieldConfig, AssessmentFormSchema $schema)
    {
        $fieldType = $fieldConfig['type'] ?? 'text';
        $fieldId = 'response_data.' . ($fieldConfig['id'] ?? Str::random(8));

        $component = match($fieldType) {
            'text' => self::buildTextField($fieldId, $fieldConfig),
            'textarea' => self::buildTextareaField($fieldId, $fieldConfig),
            'select' => self::buildSelectField($fieldId, $fieldConfig),
            'checkbox_list' => self::buildCheckboxListField($fieldId, $fieldConfig),
            'radio' => self::buildRadioField($fieldId, $fieldConfig),
            'toggle' => self::buildToggleField($fieldId, $fieldConfig),
            'date' => self::buildDateField($fieldId, $fieldConfig),
            'file' => self::buildFileField($fieldId, $fieldConfig),
            'placeholder' => self::buildPlaceholderField($fieldId, $fieldConfig),
            'repeater' => self::buildRepeaterField($fieldId, $fieldConfig, $schema),
            default => null,
        };

        if (!$component) {
            return null;
        }

        // Apply common configurations
        self::applyCommonConfig($component, $fieldConfig);

        return $component;
    }

    /**
     * Build text input field
     */
    protected static function buildTextField(string $fieldId, array $config)
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
    protected static function buildTextareaField(string $fieldId, array $config)
    {
        return Forms\Components\Textarea::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->rows($config['rows'] ?? 3)
            ->placeholder($config['placeholder'] ?? null)
            ->maxLength($config['maxLength'] ?? null);
    }

    /**
     * Build select field
     */
    protected static function buildSelectField(string $fieldId, array $config)
    {
        $field = Forms\Components\Select::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->options(self::getOptions($config))
            ->searchable($config['searchable'] ?? false)
            ->native(false);

        if (isset($config['default'])) {
            $field->default($config['default']);
        }

        if ($config['multiple'] ?? false) {
            $field->multiple();
        }

        return $field;
    }

    /**
     * Build checkbox list field
     */
    protected static function buildCheckboxListField(string $fieldId, array $config)
    {
        return Forms\Components\CheckboxList::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->options(self::getOptions($config))
            ->columns($config['columns'] ?? 1);
    }

    /**
     * Build radio field
     */
    protected static function buildRadioField(string $fieldId, array $config)
    {
        return Forms\Components\Radio::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->options(self::getOptions($config))
            ->inline($config['inline'] ?? false);
    }

    /**
     * Build toggle field
     */
    protected static function buildToggleField(string $fieldId, array $config)
    {
        return Forms\Components\Toggle::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->default($config['default'] ?? false)
            ->inline($config['inline'] ?? true);
    }

    /**
     * Build date field
     */
    protected static function buildDateField(string $fieldId, array $config)
    {
        $field = Forms\Components\DatePicker::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->native(false);

        if (isset($config['minDate'])) {
            $field->minDate($config['minDate']);
        }

        if (isset($config['maxDate'])) {
            $field->maxDate($config['maxDate']);
        }

        return $field;
    }

    /**
     * Build file upload field
     */
    protected static function buildFileField(string $fieldId, array $config)
    {
        $field = Forms\Components\FileUpload::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->multiple($config['multiple'] ?? false)
            ->directory($config['directory'] ?? 'form-uploads');

        if (isset($config['acceptedFileTypes'])) {
            $field->acceptedFileTypes($config['acceptedFileTypes']);
        }

        if (isset($config['image']) && $config['image']) {
            $field->image();
        }

        return $field;
    }

    /**
     * Build placeholder field
     */
    protected static function buildPlaceholderField(string $fieldId, array $config)
    {
        return Forms\Components\Placeholder::make($fieldId)
            ->label($config['label'] ?? '')
            ->content($config['content'] ?? '');
    }

    /**
     * Build repeater field
     */
    protected static function buildRepeaterField(string $fieldId, array $config, AssessmentFormSchema $schema)
    {
        $subFields = [];
        foreach ($config['fields'] ?? [] as $subFieldConfig) {
            $subField = self::buildField($subFieldConfig, $schema);
            if ($subField) {
                $subFields[] = $subField;
            }
        }

        return Forms\Components\Repeater::make($fieldId)
            ->label($config['label'] ?? 'Field')
            ->schema($subFields)
            ->columns($config['columns'] ?? 1)
            ->collapsible($config['collapsible'] ?? false)
            ->defaultItems($config['defaultItems'] ?? 0)
            ->addActionLabel($config['addActionLabel'] ?? 'Add item');
    }

    /**
     * Apply common configuration to all fields
     */
    protected static function applyCommonConfig($component, array $config): void
    {
        // Helper text
        if (isset($config['helperText'])) {
            $component->helperText($config['helperText']);
        }

        // Prefix/Suffix icons
        if (isset($config['prefixIcon'])) {
            $component->prefixIcon($config['prefixIcon']);
        }

        if (isset($config['suffixIcon'])) {
            $component->suffixIcon($config['suffixIcon']);
        }

        // Column span
        if (isset($config['columnSpan'])) {
            $component->columnSpan($config['columnSpan']);
        }

        // Validation
        if (isset($config['validation'])) {
            $validation = $config['validation'];
            
            if ($validation['required'] ?? false) {
                $component->required();
            }

            if (isset($validation['rules'])) {
                foreach ($validation['rules'] as $rule) {
                    $component->rule($rule);
                }
            }
        }

        // Conditional display
        if (isset($config['conditionalDisplay'])) {
            $component->visible(function (Get $get, $record) use ($config) {
                return self::evaluateConditional($get, $config['conditionalDisplay'], $record);
            });
        }

        // Disabled state
        if (isset($config['disabled'])) {
            if (is_bool($config['disabled'])) {
                $component->disabled($config['disabled']);
            } else {
                // Dynamic disabled logic
                $component->disabled(function (Get $get, $record) use ($config) {
                    return self::evaluateConditional($get, ['logic' => $config['disabled']], $record);
                });
            }
        }

        // Live updates
        if ($config['live'] ?? false) {
            $component->live();
        }

        // On change actions
        if (isset($config['onChangeActions'])) {
            $component->afterStateUpdated(function (Set $set, $state) use ($config) {
                foreach ($config['onChangeActions'] as $action) {
                    if ($action['action'] === 'reset') {
                        $set($action['target'], null);
                    }
                }
            });
        }
    }

    /**
     * Get options for select/checkbox fields
     */
    protected static function getOptions(array $config): array
    {
        // Static options
        if (isset($config['options'])) {
            return collect($config['options'])->pluck('label', 'value')->toArray();
        }

        // Dynamic options from database
        if (isset($config['dataSource'])) {
            $dataSource = $config['dataSource'];
            $modelClass = "App\\Models\\{$dataSource['model']}";
            
            if (!class_exists($modelClass)) {
                return [];
            }

            $query = $modelClass::query();
            
            // Apply filters (simplified - in production, handle Get context properly)
            if (isset($dataSource['filterBy'])) {
                // Would need proper context handling here
            }

            return $query->pluck($dataSource['labelField'], $dataSource['valueField'])->toArray();
        }

        return [];
    }

    /**
     * Evaluate conditional logic
     */
    protected static function evaluateConditional(Get $get, array $condition, $record = null): bool
    {
        // Simple string logic (e.g., "if county_id == null")
        if (isset($condition['logic'])) {
            // Parse simple conditions
            // In production, use a proper expression evaluator
            return true; // Simplified
        }

        // Field-based conditions
        if (isset($condition['field'])) {
            $fieldPath = 'response_data.' . $condition['field'];
            $fieldValue = $get($fieldPath);
            $operator = $condition['operator'] ?? 'equals';
            $compareValue = $condition['value'] ?? null;

            return match($operator) {
                'equals' => $fieldValue == $compareValue,
                'not_equals' => $fieldValue != $compareValue,
                'contains' => is_array($fieldValue) && in_array($compareValue, $fieldValue),
                'not_contains' => is_array($fieldValue) && !in_array($compareValue, $fieldValue),
                'greater_than' => $fieldValue > $compareValue,
                'less_than' => $fieldValue < $compareValue,
                'is_empty' => empty($fieldValue),
                'is_not_empty' => !empty($fieldValue),
                default => true,
            };
        }

        // Age-based conditions
        if (isset($condition['ageCondition'])) {
            if (!$record || !$record->client) {
                return true;
            }
            
            $age = $record->client->estimated_age ?? 0;
            $condition = $condition['ageCondition'];
            
            if (isset($condition['min']) && $age < $condition['min']) {
                return false;
            }
            
            if (isset($condition['max']) && $age > $condition['max']) {
                return false;
            }
            
            return true;
        }

        return true;
    }
}