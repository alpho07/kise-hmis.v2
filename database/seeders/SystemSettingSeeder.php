<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'app_name',
                'value' => 'KISE HMIS',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Application Name',
                'description' => 'Name of the application displayed across the system',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'app_timezone',
                'value' => 'Africa/Nairobi',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Timezone',
                'description' => 'Default timezone for the application',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'currency',
                'value' => 'KES',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Currency',
                'description' => 'Default currency code',
                'is_public' => true,
                'is_editable' => false,
                'sort_order' => 1,
            ],
            [
                'key' => 'tax_rate',
                'value' => '0.16',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Tax Rate',
                'description' => 'Default tax rate (VAT)',
                'is_public' => true,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'sms_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'label' => 'SMS Notifications Enabled',
                'description' => 'Enable/disable SMS notifications',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'email_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'label' => 'Email Notifications Enabled',
                'description' => 'Enable/disable email notifications',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'auto_uci_generation',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'clients',
                'label' => 'Auto Generate UCI',
                'description' => 'Automatically generate Unique Client Identifiers',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'require_triage',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'workflow',
                'label' => 'Triage Required',
                'description' => 'Make triage mandatory for all new visits',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'queue_sms_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'queue',
                'label' => 'Queue SMS Notifications',
                'description' => 'Send SMS when client is called from queue',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'max_daily_clients_default',
                'value' => '100',
                'type' => 'integer',
                'group' => 'general',
                'label' => 'Default Max Daily Clients',
                'description' => 'Default maximum clients per day for new branches',
                'is_public' => false,
                'is_editable' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }

        $this->command->info('10 system settings seeded successfully!');
    }
}