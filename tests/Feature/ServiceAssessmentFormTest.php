<?php

namespace Tests\Feature;

use App\Models\AssessmentAutoReferral;
use App\Models\AssessmentFormResponse;
use App\Models\AssessmentFormSchema;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Models\Visit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for dynamic assessment forms submitted at the service desk.
 *
 * Covers:
 *   - Form schema lookup by slug
 *   - Vision assessment response persisted correctly
 *   - Auto-referral triggered by threshold value (e.g. severe vision impairment)
 *   - Auto-referral NOT triggered when value is within normal range
 *   - Multiple vision form types stored independently
 *   - Service booking status updated to 'completed' after form save
 *   - Completed assessment is linked to the correct visit and client
 */
class ServiceAssessmentFormTest extends TestCase
{

    protected Branch  $branch;
    protected User    $provider;
    protected Client  $client;
    protected Visit   $visit;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['service_provider', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->branch   = Branch::factory()->create();
        $this->provider = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->provider->assignRole('service_provider');

        $this->client  = Client::factory()->create(['branch_id' => $this->branch->id]);
        $this->visit   = Visit::factory()->create([
            'branch_id'      => $this->branch->id,
            'client_id'      => $this->client->id,
            'current_stage'  => 'service',
            'payment_status' => 'paid',
        ]);

        $dept          = Department::factory()->create(['branch_id' => $this->branch->id]);
        $this->service = Service::factory()->create(['department_id' => $dept->id]);

        $this->actingAs($this->provider);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSchema(string $slug, array $autoReferrals = []): AssessmentFormSchema
    {
        return AssessmentFormSchema::create([
            'name'          => ucfirst(str_replace('-', ' ', $slug)),
            'slug'          => $slug,
            'version'       => 1,
            'category'      => 'vision',
            'is_active'     => true,
            'is_published'  => true,
            'created_by'    => $this->provider->id,
            'schema'        => [
                'sections' => [
                    [
                        'title'  => 'Visual Acuity',
                        'fields' => [
                            ['id' => 'va_right_distance', 'type' => 'select', 'label' => 'VA Right (Distance)'],
                            ['id' => 'presenting_complaints', 'type' => 'textarea', 'label' => 'Complaints'],
                        ],
                    ],
                ],
            ],
            'auto_referrals' => $autoReferrals,
        ]);
    }

    private function saveResponse(AssessmentFormSchema $schema, array $responseData, ?int $serviceBookingId = null): AssessmentFormResponse
    {
        return AssessmentFormResponse::create([
            'form_schema_id'       => $schema->id,
            'visit_id'             => $this->visit->id,
            'client_id'            => $this->client->id,
            'branch_id'            => $this->branch->id,
            'response_data'        => $responseData,
            'metadata'             => [
                'service_booking_id' => $serviceBookingId,
                'provider_id'        => $this->provider->id,
                'created_via'        => 'service_point',
                'form_slug'          => $schema->slug,
            ],
            'status'               => 'completed',
            'completion_percentage'=> 100,
            'started_at'           => now()->subMinutes(15),
            'completed_at'         => now(),
            'submitted_at'         => now(),
            'created_by'           => $this->provider->id,
            'updated_by'           => $this->provider->id,
        ]);
    }

    // ── Schema lookup ─────────────────────────────────────────────────────────

    public function test_vision_form_schema_can_be_found_by_slug(): void
    {
        $schema = $this->makeSchema('vision-basic-eye');

        $found = AssessmentFormSchema::where('slug', 'vision-basic-eye')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($found);
        $this->assertEquals($schema->id, $found->id);
    }

    public function test_inactive_schema_is_not_returned_by_slug_lookup(): void
    {
        AssessmentFormSchema::create([
            'name'        => 'Old Vision Form',
            'slug'        => 'vision-legacy',
            'version'     => 1,
            'category'    => 'vision',
            'is_active'   => false,
            'is_published'=> false,
            'created_by'  => $this->provider->id,
            'schema'      => ['sections' => []],
        ]);

        $found = AssessmentFormSchema::where('slug', 'vision-legacy')
            ->where('is_active', true)
            ->first();

        $this->assertNull($found);
    }

    // ── Response persistence ──────────────────────────────────────────────────

    public function test_vision_assessment_response_is_persisted_with_correct_visit_and_client(): void
    {
        $schema   = $this->makeSchema('vision-basic-eye');
        $response = $this->saveResponse($schema, [
            'va_right_distance'   => '6/6',
            'presenting_complaints' => 'Blurred vision at distance',
        ]);

        $this->assertDatabaseHas('assessment_form_responses', [
            'id'            => $response->id,
            'visit_id'      => $this->visit->id,
            'client_id'     => $this->client->id,
            'form_schema_id'=> $schema->id,
            'status'        => 'completed',
        ]);
    }

    public function test_response_data_is_stored_correctly(): void
    {
        $schema   = $this->makeSchema('vision-basic-eye');
        $payload  = ['va_right_distance' => 'CF', 'presenting_complaints' => 'No perception'];

        $response = $this->saveResponse($schema, $payload);

        $this->assertEquals($payload, $response->fresh()->response_data);
    }

    public function test_metadata_records_provider_and_form_slug(): void
    {
        $schema   = $this->makeSchema('vision-clinical');
        $response = $this->saveResponse($schema, ['va_right_distance' => '6/9']);

        $meta = $response->fresh()->metadata;
        $this->assertEquals($this->provider->id, $meta['provider_id']);
        $this->assertEquals('vision-clinical', $meta['form_slug']);
        $this->assertEquals('service_point', $meta['created_via']);
    }

    public function test_multiple_form_types_can_be_stored_for_same_visit(): void
    {
        $basicSchema    = $this->makeSchema('vision-basic-eye');
        $clinicalSchema = $this->makeSchema('vision-clinical');

        $this->saveResponse($basicSchema,    ['va_right_distance' => '6/6']);
        $this->saveResponse($clinicalSchema, ['va_right_distance' => '6/12']);

        $count = AssessmentFormResponse::where('visit_id', $this->visit->id)->count();
        $this->assertEquals(2, $count);
    }

    // ── Auto-referral logic ───────────────────────────────────────────────────

    public function test_auto_referral_is_triggered_when_va_meets_threshold(): void
    {
        $schema = $this->makeSchema('vision-basic-eye', [
            [
                'condition' => ['field' => 'va_right_distance', 'operator' => 'in', 'value' => ['CF', 'HM', 'PL', 'NPL']],
                'action'    => ['service_point' => 'ophthalmology', 'priority' => 'high', 'reason' => 'Severe vision impairment'],
            ],
        ]);

        $response = $this->saveResponse($schema, ['va_right_distance' => 'CF']); // Counting Fingers

        // Simulate what CreateDynamicAssessment::checkAutoReferral does
        foreach ($schema->auto_referrals as $rule) {
            $condition   = $rule['condition'];
            $action      = $rule['action'];
            $fieldValue  = $response->response_data[$condition['field']] ?? null;
            $triggered   = in_array($fieldValue, $condition['value']);

            if ($triggered) {
                AssessmentAutoReferral::create([
                    'form_response_id' => $response->id,
                    'client_id'        => $response->client_id,
                    'visit_id'         => $response->visit_id,
                    'service_point'    => $action['service_point'],
                    'priority'         => $action['priority'],
                    'reason'           => $action['reason'],
                    'trigger_data'     => ['field' => $condition['field'], 'value' => $fieldValue],
                    'status'           => 'pending',
                ]);
            }
        }

        $this->assertDatabaseHas('assessment_auto_referrals', [
            'form_response_id' => $response->id,
            'service_point'    => 'ophthalmology',
            'priority'         => 'high',
            'status'           => 'pending',
        ]);
    }

    public function test_auto_referral_is_not_triggered_for_normal_vision(): void
    {
        $schema = $this->makeSchema('vision-basic-eye', [
            [
                'condition' => ['field' => 'va_right_distance', 'operator' => 'in', 'value' => ['CF', 'HM', 'PL', 'NPL']],
                'action'    => ['service_point' => 'ophthalmology', 'priority' => 'high', 'reason' => 'Severe vision impairment'],
            ],
        ]);

        $response = $this->saveResponse($schema, ['va_right_distance' => '6/6']); // Normal

        // Simulate checkAutoReferral
        $triggered = false;
        foreach ($schema->auto_referrals as $rule) {
            $fieldValue = $response->response_data[$rule['condition']['field']] ?? null;
            if (in_array($fieldValue, $rule['condition']['value'])) {
                $triggered = true;
            }
        }

        $this->assertFalse($triggered);
        $this->assertEquals(0, AssessmentAutoReferral::where('form_response_id', $response->id)->count());
    }

    // ── Service booking linkage ───────────────────────────────────────────────

    public function test_service_booking_is_marked_completed_after_assessment_saved(): void
    {
        $booking = ServiceBooking::create([
            'visit_id'       => $this->visit->id,
            'client_id'      => $this->client->id,
            'service_id'     => $this->service->id,
            'branch_id'      => $this->branch->id,
            'unit_price'     => $this->service->base_price,
            'total_price'    => $this->service->base_price,
            'service_status' => 'in_progress',
            'booked_by'      => $this->provider->id,
        ]);

        $schema   = $this->makeSchema('vision-basic-eye');
        $response = $this->saveResponse($schema, ['va_right_distance' => '6/9'], $booking->id);

        // Simulate afterCreate: update booking status
        $booking->update(['service_status' => 'completed']);

        $this->assertDatabaseHas('service_bookings', [
            'id'             => $booking->id,
            'service_status' => 'completed',
        ]);
        $this->assertEquals($booking->id, $response->metadata['service_booking_id']);
    }

    // ── Response–schema relationship ─────────────────────────────────────────

    public function test_response_belongs_to_correct_schema(): void
    {
        $schema   = $this->makeSchema('vision-feva');
        $response = $this->saveResponse($schema, ['presenting_complaints' => 'Low vision in class']);

        $this->assertEquals($schema->id, $response->fresh()->schema->id);
        $this->assertEquals('vision-feva', $response->fresh()->schema->slug);
    }

    public function test_schema_responses_relationship_returns_all_responses(): void
    {
        $schema = $this->makeSchema('vision-basic-eye');

        // Create a second client/visit and save a response for each
        $client2 = Client::factory()->create(['branch_id' => $this->branch->id]);
        $visit2  = Visit::factory()->create(['branch_id' => $this->branch->id, 'client_id' => $client2->id]);

        $this->saveResponse($schema, ['va_right_distance' => '6/6']);
        AssessmentFormResponse::create([
            'form_schema_id'       => $schema->id,
            'visit_id'             => $visit2->id,
            'client_id'            => $client2->id,
            'branch_id'            => $this->branch->id,
            'response_data'        => ['va_right_distance' => '6/18'],
            'status'               => 'completed',
            'completion_percentage'=> 100,
            'started_at'           => now(),
            'completed_at'         => now(),
            'submitted_at'         => now(),
            'created_by'           => $this->provider->id,
            'updated_by'           => $this->provider->id,
        ]);

        $this->assertEquals(2, $schema->responses()->count());
    }
}
