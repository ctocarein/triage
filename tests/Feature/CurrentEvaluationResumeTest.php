<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /evaluations/current doit permettre a un evaluateur de reprendre une
 * visite interrompue, et pas seulement de retrouver sa propre evaluation.
 */
class CurrentEvaluationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $name): Organization
    {
        return Organization::create([
            'type'   => 'clinic',
            'name'   => $name,
            'status' => 'active',
        ]);
    }

    private function member(?Organization $organization, string $role): Identity
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        Membership::create([
            'identity_id'     => $identity->id,
            'organization_id' => $organization?->id,
            'role'            => $role,
            'status'          => 'active',
        ]);

        return $identity;
    }

    private function makeSession(Identity $subject, ?Identity $conductor, string $status = 'in_progress'): EvaluationSession
    {
        return EvaluationSession::create([
            'identity_id' => $subject->id,
            'status'      => $status,
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
            'context'     => $conductor ? ['agent_id' => (string) $conductor->id] : [],
        ]);
    }

    public function test_an_evaluator_recovers_the_visit_they_were_conducting(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $session = $this->makeSession($subject, $agent);

        // Sans aucun parametre : l'agent n'a pas eu a memoriser pour qui il
        // menait la visite, ce qui est le cas apres une coupure sur une fiche
        // creee par le demarrage lui-meme.
        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertOk()
            ->assertJsonPath('session.public_id', $session->public_id);
    }

    public function test_an_ambassador_recovers_their_visit_too(): void
    {
        $organization = $this->organization('Org A');
        $ambassador   = $this->member($organization, 'Ambassador');
        $subject      = $this->member($organization, 'Beneficiary');

        $session = $this->makeSession($subject, $ambassador);

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertOk()
            ->assertJsonPath('session.public_id', $session->public_id);
    }

    public function test_a_visit_can_be_requested_by_subject(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $session = $this->makeSession($subject, $agent);

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/evaluations/current?subject_id={$subject->id}")
            ->assertOk()
            ->assertJsonPath('session.public_id', $session->public_id);
    }

    public function test_an_evaluator_does_not_recover_a_visit_conducted_by_someone_else(): void
    {
        $organization = $this->organization('Org A');
        $conductor    = $this->member($organization, 'FieldAgent');
        $colleague    = $this->member($organization, 'Ambassador');
        $subject      = $this->member($organization, 'Beneficiary');

        $this->makeSession($subject, $conductor);

        // Ni implicitement...
        $this->actingAs($colleague, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertStatus(404);

        // ...ni en désignant la bénéficiaire : la policy tranche, et repond 404
        // plutot que de reveler l'existence de la session.
        $this->actingAs($colleague, 'sanctum')
            ->getJson("/api/evaluations/current?subject_id={$subject->id}")
            ->assertStatus(404);
    }

    public function test_a_beneficiary_still_recovers_their_own_session(): void
    {
        $organization = $this->organization('Org A');
        $subject      = $this->member($organization, 'Beneficiary');

        $session = $this->makeSession($subject, null);

        $this->actingAs($subject, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertOk()
            ->assertJsonPath('session.public_id', $session->public_id);
    }

    public function test_an_evaluator_own_session_comes_before_the_one_they_conduct(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $conducted = $this->makeSession($subject, $agent);
        $own       = $this->makeSession($agent, null);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertOk()
            ->assertJsonPath('session.public_id', $own->public_id);

        $this->assertNotSame($own->public_id, $conducted->public_id);
    }

    public function test_a_completed_visit_is_not_recovered(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $this->makeSession($subject, $agent, 'completed');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/evaluations/current')
            ->assertStatus(404);
    }

    public function test_an_unknown_subject_is_rejected_by_validation(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/evaluations/current?subject_id=3f2b6c1e-0000-4000-8000-000000000000')
            ->assertStatus(422);
    }
}
