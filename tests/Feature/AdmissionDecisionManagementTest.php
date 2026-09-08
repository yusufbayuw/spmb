<?php

namespace Tests\Feature;

use App\Models\AdmissionQuota;
use App\Models\AdmissionTest;
use App\Models\Announcement;
use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Selection;
use App\Models\SelectionBatch;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\AdmissionDecisionService;
use App\Services\RegistrationWorkflowService;
use App\Services\ReRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmissionDecisionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_capacity_never_limits_form_registration(): void
    {
        [, $opening] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $this->registration($opening, '3273010101010001');
        $this->registration($opening, '3273010101010002');
        $this->registration($opening, '3273010101010003');

        $this->assertSame(3, Registration::query()->where('registration_opening_id', $opening->id)->count());
    }

    public function test_ranking_is_deterministic_and_human_finalization_uses_admission_capacity(): void
    {
        Queue::fake();
        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);
        $first = $this->selectionRegistration($opening, '3273010101010001', 90);
        $second = $this->selectionRegistration($opening, '3273010101010002', 90);
        $third = $this->selectionRegistration($opening, '3273010101010003', 80);
        $batch = SelectionBatch::create(['registration_opening_id' => $opening->id, 'name' => 'Gelombang 1', 'waitlist_limit' => 1]);

        app(AdmissionDecisionService::class)->rank($batch, $staff);

        $this->assertSame('accepted', $first->selection()->value('system_recommendation'));
        $this->assertSame('waiting_list', $second->selection()->value('system_recommendation'));
        $this->assertSame('rejected', $third->selection()->value('system_recommendation'));
        $this->assertSame(1, $first->selection()->value('rank'));
        $this->assertSame(2, $second->selection()->value('rank'));

        app(AdmissionDecisionService::class)->finalize($batch, $staff);

        $this->assertSame('announcement', $first->fresh()->current_stage);
        $this->assertSame('accepted', $first->selection()->value('decision'));
        $this->assertSame('waiting_list', $second->selection()->value('decision'));
    }

    public function test_flexible_selection_mode_allows_direct_result_without_batch(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $registration = $this->selectionRegistration($opening, '3273010101010021', 88);

        $this->assertSame('flexible', $registration->selectionMode());

        app(RegistrationWorkflowService::class)->decide(
            $registration,
            $staff,
            'accepted',
            88,
            'Ditetapkan langsung tanpa ranking batch.',
        );

        $this->assertSame('announcement', $registration->fresh()->current_stage);
        $this->assertSame('accepted', $registration->selection()->value('decision'));
        $this->assertSame('draft', $registration->announcement()->value('status'));
        $this->assertNull($registration->selection()->value('selection_batch_id'));
    }

    public function test_batch_selection_mode_rejects_direct_result_bypass(): void
    {
        Queue::fake();

        [$unit, $opening, $staff] = $this->openingFixture();

        $batchConfiguration = UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => 2,
            'status' => 'published',
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => false,
            'selection_mode' => 'batch',
            'post_announcement_enabled' => true,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [],
            're_registration_requirements' => [],
            'published_at' => now(),
        ]);

        $registration = $this->selectionRegistration($opening, '3273010101010022', 88);
        $registration->update(['unit_configuration_id' => $batchConfiguration->id]);

        $this->expectException(ValidationException::class);

        app(RegistrationWorkflowService::class)->decide(
            $registration->fresh(),
            $staff,
            'accepted',
            88,
            'Mencoba bypass batch.',
        );
    }

    public function test_completed_scored_tests_feed_ranking_and_failed_required_test_forces_rejection(): void
    {
        Queue::fake();

        [$unit, $opening, $staff] = $this->openingFixture();

        $scoreTestA = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik A',
            'result_type' => 'score',
            'is_required' => true,
            'is_active' => true,
        ]);
        $scoreTestB = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik B',
            'result_type' => 'score',
            'is_required' => true,
            'is_active' => true,
        ]);
        $passFailTest = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Wawancara',
            'result_type' => 'pass_fail',
            'is_required' => true,
            'is_active' => true,
        ]);

        $configuration = UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => 2,
            'status' => 'published',
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => true,
            'post_announcement_enabled' => true,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [
                ['id' => $scoreTestA->id, 'name' => $scoreTestA->name, 'is_required' => true, 'result_type' => 'score', 'passing_score' => null],
                ['id' => $scoreTestB->id, 'name' => $scoreTestB->name, 'is_required' => true, 'result_type' => 'score', 'passing_score' => null],
                ['id' => $passFailTest->id, 'name' => $passFailTest->name, 'is_required' => true, 'result_type' => 'pass_fail', 'passing_score' => null],
            ],
            're_registration_requirements' => [],
            'published_at' => now(),
        ]);

        $registration = $this->registration($opening, '3273010101010010');
        $registration->update([
            'unit_configuration_id' => $configuration->id,
            'current_stage' => 'tests',
            'data_validation_status' => 'valid',
        ]);

        $resultA = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $scoreTestA->id,
            'status' => 'scheduled',
            'result' => 'pending',
        ]);
        $resultB = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $scoreTestB->id,
            'status' => 'scheduled',
            'result' => 'pending',
        ]);
        $interview = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $passFailTest->id,
            'status' => 'scheduled',
            'result' => 'pending',
        ]);

        $workflow = app(RegistrationWorkflowService::class);
        $workflow->recordTestResult($resultA, $staff, ['status' => 'completed', 'score' => 80, 'result' => 'pass']);
        $workflow->recordTestResult($resultB, $staff, ['status' => 'completed', 'score' => 90, 'result' => 'pass']);
        $workflow->recordTestResult($interview, $staff, ['status' => 'completed', 'score' => null, 'result' => 'fail']);

        $registration->refresh();

        $this->assertSame('selection', $registration->current_stage);
        $this->assertSame(85.0, (float) $registration->selection()->value('final_score'));

        AdmissionQuota::create([
            'registration_opening_id' => $opening->id,
            'capacity' => 1,
        ]);
        $batch = SelectionBatch::create([
            'registration_opening_id' => $opening->id,
            'name' => 'Ranking Tes',
            'waitlist_limit' => 0,
        ]);

        app(AdmissionDecisionService::class)->rank($batch, $staff);

        $this->assertSame('rejected', $registration->selection()->value('system_recommendation'));
    }

    public function test_reviewed_batch_decisions_remain_in_selection_until_batch_is_finalized(): void
    {
        Queue::fake();
        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $first = $this->selectionRegistration($opening, '3273010101010011', 90);
        $second = $this->selectionRegistration($opening, '3273010101010012', 80);
        $batch = SelectionBatch::create([
            'registration_opening_id' => $opening->id,
            'name' => 'Review Gelombang 1',
            'waitlist_limit' => 0,
        ]);

        $admission = app(AdmissionDecisionService::class);
        $workflow = app(RegistrationWorkflowService::class);

        $admission->rank($batch, $staff);

        $workflow->reviewDecision(
            $first,
            $staff,
            'rejected',
            90,
            'Override setelah review panitia.',
        );
        $workflow->reviewDecision(
            $second,
            $staff,
            'accepted',
            80,
            'Dialihkan sebagai kandidat diterima setelah review panitia.',
        );

        $this->assertSame('selection', $first->fresh()->current_stage);
        $this->assertSame('selection', $second->fresh()->current_stage);
        $this->assertNull($first->announcement()->first());
        $this->assertNull($second->announcement()->first());

        $admission->finalize($batch->fresh(), $staff);

        $this->assertSame('rejected', $first->selection()->value('decision'));
        $this->assertSame('accepted', $second->selection()->value('decision'));
        $this->assertSame('announcement', $first->fresh()->current_stage);
        $this->assertSame('announcement', $second->fresh()->current_stage);
        $this->assertSame('draft', $first->announcement()->value('status'));
        $this->assertSame('draft', $second->announcement()->value('status'));
    }

    public function test_declined_offer_releases_seat_and_promotes_next_waiting_list_candidate(): void
    {
        Queue::fake();
        [, $opening, $staff] = $this->openingFixture();
        $quota = AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);
        $accepted = $this->selectionRegistration($opening, '3273010101010001', 90);
        $waiting = $this->selectionRegistration($opening, '3273010101010002', 80);
        $workflow = app(RegistrationWorkflowService::class);

        $workflow->decide($accepted, $staff, 'accepted', 90, 'Keputusan final');
        $workflow->publish($accepted, $staff);
        $waiting->selection()->update(['waitlist_rank' => 1]);
        $workflow->decide($waiting, $staff, 'waiting_list', 80, 'Keputusan final');
        $workflow->publish($waiting, $staff);

        $offer = $accepted->fresh()->admissionOffer;
        app(AdmissionDecisionService::class)->declineOffer($offer, $accepted->user, 'Memilih sekolah lain');

        $this->assertSame('declined', $offer->fresh()->status);
        $this->assertSame('admission_offer', $waiting->fresh()->current_stage);
        $this->assertSame('accepted', $waiting->selection()->value('decision'));
        $this->assertSame($quota->id, $waiting->fresh()->admissionOffer->admission_quota_id);
    }

    public function test_offer_acceptance_is_idempotent_and_invalid_stage_transition_is_rejected(): void
    {
        Queue::fake();
        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);
        $registration = $this->selectionRegistration($opening, '3273010101010001', 90);
        app(RegistrationWorkflowService::class)->decide($registration, $staff, 'accepted', 90, 'Keputusan final');
        app(RegistrationWorkflowService::class)->publish($registration, $staff);
        $offer = $registration->fresh()->admissionOffer;

        app(AdmissionDecisionService::class)->acceptOffer($offer, $registration->user);
        app(AdmissionDecisionService::class)->acceptOffer($offer->fresh(), $registration->user);

        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame('confirmed', $registration->fresh()->status);
        $this->assertSame('enrollment', $registration->fresh()->current_stage);

        $this->expectException(ValidationException::class);
        $registration->fresh()->transitionTo('selection');
    }

    public function test_expired_offer_releases_the_seat_and_completes_the_registration(): void
    {
        Queue::fake();
        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);
        $registration = $this->selectionRegistration($opening, '3273010101010001', 90);
        app(RegistrationWorkflowService::class)->decide($registration, $staff, 'accepted', 90, 'Keputusan final');
        app(RegistrationWorkflowService::class)->publish($registration, $staff);
        $offer = $registration->fresh()->admissionOffer;
        $offer->update(['expires_at' => now()->subMinute()]);

        $this->assertTrue(app(AdmissionDecisionService::class)->expireOffer($offer));

        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertSame('completed', $registration->fresh()->current_stage);
    }

    public function test_versioned_re_registration_requirements_must_be_verified_before_enrollment(): void
    {
        Queue::fake();
        [$unit, $opening, $staff] = $this->openingFixture();
        UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => 2,
            'status' => 'published',
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => false,
            'post_announcement_enabled' => true,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [],
            're_registration_requirements' => [[
                'key' => 'parent_consent',
                'label' => 'Persetujuan Orang Tua',
                'type' => 'checklist',
                'active' => true,
                'required' => true,
            ]],
            'published_at' => now(),
        ]);
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);
        $registration = $this->selectionRegistration($opening, '3273010101010001', 90);
        app(RegistrationWorkflowService::class)->decide($registration, $staff, 'accepted', 90, 'Keputusan final');
        app(RegistrationWorkflowService::class)->publish($registration, $staff);
        app(AdmissionDecisionService::class)->acceptOffer($registration->fresh()->admissionOffer, $registration->user);

        $item = $registration->fresh()->reRegistrationItems()->firstOrFail();
        $this->assertSame('re_registration', $registration->fresh()->current_stage);
        app(ReRegistrationService::class)->verify($item, $staff, true);
        $this->assertSame('enrollment', $registration->fresh()->current_stage);
        app(ReRegistrationService::class)->enroll($registration->fresh(), $staff);
        $this->assertSame('enrolled', $registration->fresh()->status);
    }

    public function test_finalized_draft_decision_can_be_corrected_with_audit_trail(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 2]);

        $registration = $this->selectionRegistration($opening, '3273010101010031', 88);
        $workflow = app(RegistrationWorkflowService::class);

        $workflow->decide($registration, $staff, 'accepted', 88, 'Keputusan awal.');

        $corrected = $workflow->correctDraftDecision(
            $registration->fresh()->selection,
            $staff,
            'rejected',
            88,
            'Salah input pada penetapan awal.',
        );

        $this->assertSame('rejected', $corrected->decision);
        $this->assertSame('announcement', $registration->fresh()->current_stage);
        $this->assertSame('draft', $registration->fresh()->announcement()->value('status'));
        $this->assertDatabaseHas('audit_logs', [
            'registration_id' => $registration->id,
            'event' => 'selection.decision_corrected',
        ]);
    }

    public function test_bulk_manual_decision_is_atomic_when_acceptance_exceeds_capacity(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $first = $this->selectionRegistration($opening, '3273010101010032', 90);
        $second = $this->selectionRegistration($opening, '3273010101010033', 80);

        try {
            app(RegistrationWorkflowService::class)->bulkSetDecisions(
                Selection::query()
                    ->whereIn('registration_id', [$first->id, $second->id])
                    ->get(),
                $staff,
                'accepted',
                'Penetapan massal panitia.',
            );

            $this->fail('Bulk acceptance seharusnya ditolak karena melebihi daya tampung.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame('selection', $first->fresh()->current_stage);
        $this->assertSame('selection', $second->fresh()->current_stage);
        $this->assertSame('pending', $first->selection()->value('decision'));
        $this->assertSame('pending', $second->selection()->value('decision'));
        $this->assertNull($first->announcement()->first());
        $this->assertNull($second->announcement()->first());
    }

    public function test_bulk_publish_publishes_all_valid_selected_drafts(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        $first = $this->selectionRegistration($opening, '3273010101010034', 75);
        $second = $this->selectionRegistration($opening, '3273010101010035', 70);
        $workflow = app(RegistrationWorkflowService::class);

        $workflow->decide($first, $staff, 'rejected', 75, 'Tidak memenuhi kriteria.');
        $workflow->decide($second, $staff, 'rejected', 70, 'Tidak memenuhi kriteria.');

        $count = $workflow->bulkPublishAnnouncements(
            Announcement::query()
                ->whereIn('registration_id', [$first->id, $second->id])
                ->get(),
            $staff,
        );

        $this->assertSame(2, $count);
        $this->assertSame('published', $first->fresh()->announcement()->value('status'));
        $this->assertSame('published', $second->fresh()->announcement()->value('status'));
        $this->assertSame('completed', $first->fresh()->current_stage);
        $this->assertSame('completed', $second->fresh()->current_stage);
    }

    public function test_published_result_can_be_corrected_before_offer_acceptance(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $registration = $this->selectionRegistration($opening, '3273010101010036', 90);
        $workflow = app(RegistrationWorkflowService::class);

        $workflow->decide($registration, $staff, 'accepted', 90, 'Keputusan awal.');
        $workflow->publish($registration, $staff);

        $offer = $registration->fresh()->admissionOffer;
        $this->assertSame('offered', $offer->status);

        $workflow->correctPublishedDecision(
            $registration->fresh()->announcement,
            $staff,
            'rejected',
            'Ditemukan kesalahan administrasi sebelum offer diterima.',
        );

        $this->assertSame('rejected', $registration->selection()->value('decision'));
        $this->assertSame('completed', $registration->fresh()->current_stage);
        $this->assertSame('rejected', $registration->fresh()->status);
        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'registration_id' => $registration->id,
            'event' => 'selection.published_decision_corrected',
        ]);
    }

    public function test_published_result_correction_is_blocked_after_offer_is_accepted(): void
    {
        Queue::fake();

        [, $opening, $staff] = $this->openingFixture();
        AdmissionQuota::create(['registration_opening_id' => $opening->id, 'capacity' => 1]);

        $registration = $this->selectionRegistration($opening, '3273010101010037', 90);
        $workflow = app(RegistrationWorkflowService::class);

        $workflow->decide($registration, $staff, 'accepted', 90, 'Keputusan awal.');
        $workflow->publish($registration, $staff);
        app(AdmissionDecisionService::class)->acceptOffer(
            $registration->fresh()->admissionOffer,
            $registration->user,
        );

        $this->expectException(ValidationException::class);

        $workflow->correctPublishedDecision(
            $registration->fresh()->announcement,
            $staff,
            'rejected',
            'Mencoba koreksi setelah offer diterima.',
        );
    }

    private function openingFixture(): array
    {
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);

        UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => false,
            'post_announcement_enabled' => true,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [],
            're_registration_requirements' => [],
            'published_at' => now(),
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);
        $staff = User::factory()->create(['role' => 'admin', 'is_active' => true, 'unit_id' => $unit->id]);

        return [$unit, $opening, $staff];
    }

    private function registration(RegistrationOpening $opening, string $nik): Registration
    {
        return Registration::create([
            'user_id' => User::factory()->create(['role' => 'user', 'is_active' => true])->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => 'Calon '.$nik,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);
    }

    private function selectionRegistration(RegistrationOpening $opening, string $nik, int $score): Registration
    {
        $registration = $this->registration($opening, $nik);
        $registration->update(['current_stage' => 'selection', 'data_validation_status' => 'valid']);
        Selection::create(['registration_id' => $registration->id, 'decision' => 'pending', 'final_score' => $score]);

        return $registration->fresh('user');
    }
}
