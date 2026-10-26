<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MembershipApplicationApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_configuration_returns_active_membership_types_for_the_current_organization(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'test-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $active = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Standard',
            'code' => 'standard',
            'description' => 'Standard membership.',
            'is_active' => true,
            'metadata' => [],
        ]);

        MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Archived',
            'code' => 'archived',
            'description' => 'No longer available.',
            'is_active' => false,
            'metadata' => [],
        ]);

        $otherOrganization = Organization::factory()->create([
            'slug' => 'other-' . uniqid(),
        ]);

        MembershipType::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other',
            'code' => 'other',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->getJson(
            $this->organizationUrl($organization, '/api/v1/membership-application')
        )
            ->assertOk()
            ->assertJsonCount(1, 'membership_types')
            ->assertJsonPath('membership_types.0.id', $active->id)
            ->assertJsonPath('membership_types.0.code', 'standard')
            ->assertJsonPath('membership_types.0.name', 'Standard');
    }

    public function test_configuration_returns_base_fields_and_steps(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'test-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $response = $this->getJson(
            $this->organizationUrl($organization, '/api/v1/membership-application')
        );

        $response
            ->assertOk()
            ->assertJsonPath('personal.fields.first_name.type', 'string')
            ->assertJsonPath('personal.fields.first_name.required', true)
            ->assertJsonPath('personal.fields.blood_group.required', true)
            ->assertJsonPath('address.fields.address_line_1.required', true)
            ->assertJsonPath('steps.0.key', 'personal')
            ->assertJsonPath('steps.1.key', 'address')
            ->assertJsonPath('steps.2.key', 'documents')
            ->assertJsonPath('extensions', [])
            ->assertJsonPath('identifications', [])
            ->assertJsonPath('documents', []);
    }

    public function test_configuration_includes_enabled_module_fields_and_steps(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'test-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        app(OrganizationModuleService::class)->enable($organization, 'Saradhi');

        $response = $this->getJson(
            $this->organizationUrl($organization, '/api/v1/membership-application')
        );

        $response
            ->assertOk()
            ->assertJsonPath('extensions.Saradhi.governorate.type', 'string')
            ->assertJsonPath('steps.2.key', 'extension.Saradhi')
            ->assertJsonPath('steps.3.key', 'documents');
    }

    public function test_submission_creates_only_a_submitted_application(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('organization_id', $organization->id)
            ->assertJsonPath('user_id', $applicant->id)
            ->assertJsonPath('membership_type_id', $membershipType->id)
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseHas('membership_applications', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'status' => MembershipApplicationStatus::SUBMITTED->value,
        ]);

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);

        $this->assertDatabaseMissing('memberships', [
            'member_id' => 1,
        ]);

        $this->assertDatabaseMissing('user_details', [
            'user_id' => $applicant->id,
        ]);

        $this->assertDatabaseMissing('user_addresses', [
            'user_id' => $applicant->id,
        ]);
    }

    public function test_submission_stores_identification_and_application_documents_without_creating_user_identification(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $civilId,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front', 'back'],
        );

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                [
                    'identifications' => ['civil_id' => 'CIV-123456'],
                    'identification_documents' => [
                        'civil_id' => [
                            'front' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
                            'back' => UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
                        ],
                    ],
                ],
            ),
        );

        $response->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->assertSame(
            'CIV-123456',
            data_get($application->data, 'identifications.civil_id.identification_number')
        );

        $this->assertCount(2, $application->getMedia('application-documents'));

        $this->assertDatabaseMissing('user_identifications', [
            'user_id' => $applicant->id,
            'identification_type_id' => $civilId->id,
        ]);
    }

    public function test_rejecting_at_verification_can_allow_reapplication(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $civilId,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front', 'back'],
        );

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                [
                    'identifications' => ['civil_id' => 'CIV-REAPPLY-123'],
                    'identification_documents' => [
                        'civil_id' => [
                            'front' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
                            'back' => UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
                        ],
                    ],
                ],
            ),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();
        $this->assertCount(2, $application->getMedia('application-documents'));

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}/verify"
            ),
            [
                'decision' => 'reject',
                'notes' => 'Please correct the address.',
                'allow_reapply' => true,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $application->refresh();

        $this->assertTrue($application->latestRejectionAllowsReapply());

        // Required identification fields and documents must be supplied again
        // when the rejected application is resubmitted.
        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType, 'Updated'),
                [
                    'identifications' => ['civil_id' => 'CIV-REAPPLY-456'],
                    'identification_documents' => [
                        'civil_id' => [
                            'front' => UploadedFile::fake()->create('reapply-front.pdf', 100, 'application/pdf'),
                            'back' => UploadedFile::fake()->create('reapply-back.pdf', 100, 'application/pdf'),
                        ],
                    ],
                ],
            ),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseCount('membership_applications', 1);
        $this->assertSame($application->id, MembershipApplication::query()->firstOrFail()->id);
        $reappliedDocuments = $application->fresh()->getMedia('application-documents');
        $this->assertCount(2, $reappliedDocuments);
        $this->assertEqualsCanonicalizing(
            ['reapply-front.pdf', 'reapply-back.pdf'],
            $reappliedDocuments->pluck('file_name')->all(),
        );
        $this->assertSame(
            'CIV-REAPPLY-456',
            data_get($application->fresh()->data, 'identifications.civil_id.identification_number'),
        );
        $this->assertDatabaseCount('membership_application_status_histories', 4);
        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
        $this->assertDatabaseMissing('membership_application_status_histories', [
            'membership_application_id' => $application->id,
            'to_status' => MembershipApplicationStatus::CONFIRMED->value,
        ]);
    }

    public function test_reapplication_is_denied_when_rejection_does_not_allow_it(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}/verify"
            ),
            [
                'decision' => 'reject',
                'notes' => 'Please correct the address.',
                'allow_reapply' => false,
            ],
        )->assertOk()->assertJsonPath('status', 'rejected');

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType, 'Updated'),
        )->assertUnprocessable();

        $this->assertDatabaseCount('membership_applications', 1);
        $this->assertSame(
            MembershipApplicationStatus::REJECTED,
            MembershipApplication::query()->firstOrFail()->status,
        );
    }

    public function test_workflow_reaches_confirmation_and_creates_active_membership(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/verify"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'verified');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/review"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'reviewed');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'approved');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/payment"),
        )->assertOk()->assertJsonPath('status', 'pending_confirmation');

        $response = $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/confirm"),
            [
                'membership_number' => '100',
                'starts_at' => '2026-10-05',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'membership_number' => 'TOA-0100',
        ]);

        $member = \Modules\Member\Models\Member::query()->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $applicant->id,
            'first_name' => 'John',
            'blood_group' => 'O+',
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $applicant->id,
            'address_line_1' => '123 Main Street',
        ]);

        // A repeated confirmation must not create another member or membership.
        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/confirm"),
            [
                'membership_number' => 'MEM-0100',
                'starts_at' => '2026-10-05',
            ],
        )->assertUnprocessable();

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('memberships', 1);
        // Seven legitimate transitions: submitted, verified, reviewed, approved,
        // pending payment, pending confirmation, and confirmed. The repeated
        // confirmation attempt must not add another history entry.
        $this->assertDatabaseCount('membership_application_status_histories', 7);
        $this->assertDatabaseHas('membership_applications', [
            'id' => $application->id,
            'status' => MembershipApplicationStatus::CONFIRMED->value,
            'member_id' => $member->id,
        ]);
    }

    public function test_confirmation_is_rejected_when_an_application_obligation_is_unpaid(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/verify"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/review"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/payment"),
        )->assertOk()->assertJsonPath('status', 'pending_confirmation');

        // Simulate an outstanding balance at the confirmation boundary.
        $obligation = $application->paymentObligations()->firstOrFail();
        $obligation->update([
            'status' => MemberPaymentObligationStatus::PENDING,
            'paid_amount' => 0,
            'paid_at' => null,
        ]);
        $historyCountBeforeConfirmation = $application->statusHistories()->count();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/confirm"),
            [
                'membership_number' => 'MEM-UNPAID-01',
                'starts_at' => '2026-10-05',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors(['payment']);

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('user_details', 0);
        $this->assertDatabaseCount('user_addresses', 0);
        $this->assertDatabaseHas('membership_applications', [
            'id' => $application->id,
            'status' => MembershipApplicationStatus::PENDING_CONFIRMATION->value,
            'member_id' => null,
        ]);
        $this->assertSame($historyCountBeforeConfirmation, $application->statusHistories()->count());
    }

    public function test_confirmation_rolls_back_member_and_membership_when_activation_fails(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/verify"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/review"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertOk();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/payment"),
        )->assertOk()->assertJsonPath('status', 'pending_confirmation');

        $historyCountBeforeConfirmation = $application->statusHistories()->count();

        $this->mock(\Modules\Member\Services\MembershipLifecycleService::class)
            ->shouldReceive('activate')
            ->once()
            ->andThrow(new \RuntimeException('Simulated activation failure.'));

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/confirm"),
            [
                'membership_number' => '1',
                'starts_at' => '2026-10-05',
            ],
        )->assertServerError();

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('user_addresses', 0);
        $this->assertDatabaseHas('membership_applications', [
            'id' => $application->id,
            'status' => MembershipApplicationStatus::PENDING_CONFIRMATION->value,
            'member_id' => null,
        ]);
        $this->assertSame($historyCountBeforeConfirmation, $application->statusHistories()->count());
    }

    public function test_application_cannot_skip_workflow_states(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertUnprocessable();

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
    }

    public function test_disabled_identification_type_is_rejected(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $passport,
            isEnabled: false,
        );

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                ['identifications' => ['passport' => 'P1234567']],
            ),
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifications']);

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
    }

    public function test_submission_rejects_a_unit_from_another_organization(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();
        $otherOrganization = Organization::factory()->create([
            'slug' => 'other-' . uniqid(),
        ]);
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                ['unit_id' => $foreignUnit->id],
            ),
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_id']);

        $this->assertDatabaseMissing('membership_applications', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
    }

    public function test_application_edit_rejects_a_unit_from_another_organization(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();
        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();
        $otherOrganization = Organization::factory()->create([
            'slug' => 'other-' . uniqid(),
        ]);
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}",
            ),
            ['member' => ['unit_id' => $foreignUnit->id]],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member.unit_id']);
    }

    public function test_application_edit_applies_enabled_extension_field_rules(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();
        app(OrganizationModuleService::class)->enable($organization, 'Saradhi');

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}",
            ),
            [
                'extension_data' => [
                    'Saradhi' => [
                        'governorate' => ['not', 'a', 'string'],
                    ],
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['extension_data.Saradhi.governorate']);
    }

    public function test_application_edit_runs_enabled_extension_validation(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();
        app(OrganizationModuleService::class)->enable($organization, 'Saradhi');

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();
        $otherOrganization = Organization::factory()->create([
            'slug' => 'other-' . uniqid(),
        ]);
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}",
            ),
            [
                'extension_data' => [
                    'Saradhi' => [
                        'introducer_unit_id' => $foreignUnit->id,
                    ],
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'extension_data.Saradhi.introducer_unit_id',
            ]);
    }

    public function test_application_edit_records_before_and_after_data_with_actor_and_reason(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();
        $actorId = auth()->id();
        $before = $application->data;

        $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}",
            ),
            [
                'member' => ['first_name' => 'Reviewed'],
                'reason' => 'Corrected name after document verification.',
            ],
        )->assertOk();

        $change = $application->changes()->firstOrFail();

        $this->assertSame($actorId, $change->actor_user_id);
        $this->assertSame('Corrected name after document verification.', $change->reason);
        $this->assertSame($before, $change->before_data);
        $this->assertSame('John', data_get($change->before_data, 'member.first_name'));
        $this->assertSame('Reviewed', data_get($change->after_data, 'member.first_name'));
        $this->assertSame('Doe', data_get($change->after_data, 'member.last_name'));
        $this->assertSame('Reviewed', data_get($application->fresh()->data, 'member.first_name'));
        $this->assertDatabaseCount('membership_application_changes', 1);
    }

    public function test_unit_scoped_reviewer_cannot_move_application_to_another_unit(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $assignedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $otherUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $submission = $this->validSubmission($applicant, $membershipType);
        $submission['unit_id'] = $assignedUnit->id;

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $submission,
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $reviewer = User::factory()->create();
        $reviewerMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $reviewer->id,
            'status' => 'active',
        ]);

        $permission = Permission::query()
            ->where('code', 'membership.application.edit')
            ->firstOrFail();

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Application Reviewer',
            'code' => 'unit_application_reviewer_' . uniqid(),
        ]);
        $role->permissions()->attach($permission);

        app(RoleAssignmentService::class)->assign(
            $reviewerMembership,
            $role,
            $assignedUnit,
        );

        $this->actingAs($reviewer)
            ->putJson(
                $this->organizationUrl(
                    $organization,
                    "/api/v1/membership-applications/{$application->id}",
                ),
                ['member' => ['first_name' => 'Reviewed']],
            )
            ->assertOk();

        $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}",
            ),
            ['member' => ['unit_id' => $otherUnit->id]],
        )->assertForbidden();

        $this->assertSame(
            $assignedUnit->id,
            $application->fresh()->unitId(),
        );
    }

    private function applicationContext(): array
    {
        $organization = Organization::factory()->create([
            'slug' => 'test-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $applicant = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
            'is_active' => true,
        ]);

        $charge = MemberPaymentItem::create([
            'organization_id' => $organization->id,
            'membership_type_id' => $membershipType->id,
            'title' => 'Registration Fee',
            'slug' => 'registration-' . uniqid(),
            'is_recurring' => false,
            'is_mandatory' => true,
            'is_active' => true,
            'metadata' => [],
        ]);

        $rate = MemberPaymentItemRate::create([
            'member_payment_item_id' => $charge->id,
            'amount' => 1000,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
        ]);
        $rate->allocations()->create(['scope_type' => 'organization', 'percentage' => 100]);

        return [$organization, $applicant, $membershipType];
    }

    private function validSubmission(
        User $applicant,
        MembershipType $membershipType,
        string $firstName = 'John',
    ): array {
        return [
            'user_id' => $applicant->id,
            'membership_type_id' => $membershipType->id,
            'first_name' => $firstName,
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'blood_group' => 'O+',
            'whatsapp' => '771234567',
            'whatsapp_calling_code' => '+94',
            'home_contact' => '0112345678',
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
            'address' => [
                'address_type' => 'home',
                'address_line_1' => '123 Main Street',
                'city' => 'Colombo',
                'country' => 'Sri Lanka',
                'is_primary' => true,
            ],
        ];
    }
}
