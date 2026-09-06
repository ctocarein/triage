<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visites de suivi : une evaluation deja terminee ne bloque plus la suivante.
 *
 * Figer le dossier au jour de la premiere evaluation reviendrait a interdire
 * tout suivi longitudinal, qui est precisement ce que l'evaluation assistee
 * rend possible.
 */
class FollowUpEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function organization(): Organization
    {
        return Organization::create(['type' => 'clinic', 'name' => 'Org A', 'status' => 'active']);
    }

    private function member(Organization $organization, string $role): Identity
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        Membership::create([
            'identity_id'     => $identity->id,
            'organization_id' => $organization->id,
            'role'            => $role,
            'status'          => 'active',
        ]);

        return $identity;
    }

    private function seedQuestion(): void
    {
        DynamicQuestionTemplate::create([
            'key'           => 'followup_first_question',
            'text'          => 'Premiere question',
            'type'          => 'boolean',
            'answer_target' => 'evaluation',
            'bloc_key'      => 'followup_bloc',
            'order'         => 1,
            'is_active'     => true,
        ]);
    }

    private function completedSession(Identity $subject, int $monthsAgo): EvaluationSession
    {
        return EvaluationSession::create([
            'identity_id'  => $subject->id,
            'status'       => 'completed',
            'type'         => 'complete',
            'score'        => 4,
            'risk_level'   => 'low',
            'started_at'   => now()->subMonths($monthsAgo),
            'completed_at' => now()->subMonths($monthsAgo),
        ]);
    }

    public function test_a_beneficiary_already_evaluated_can_be_evaluated_again(): void
    {
        $this->seedQuestion();

        $organization = $this->organization();
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $previous = $this->completedSession($subject, 8);

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('previous_evaluation.session_id', $previous->public_id)
            ->assertJsonPath('previous_evaluation.is_due', true);

        $this->assertSame(
            2,
            EvaluationSession::where('identity_id', $subject->id)->count(),
            "L'ancienne session doit etre conservee a cote de la nouvelle."
        );
    }

    public function test_an_early_follow_up_is_allowed_but_flagged_as_not_due(): void
    {
        $this->seedQuestion();

        $organization = $this->organization();
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $this->completedSession($subject, 2);

        // Signal, pas verrou : la visite anticipee passe, mais le client sait
        // qu'elle l'est.
        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('previous_evaluation.is_due', false);
    }

    public function test_a_first_evaluation_carries_no_previous(): void
    {
        $this->seedQuestion();

        $organization = $this->organization();
        $agent        = $this->member($organization, 'FieldAgent');
        $subject      = $this->member($organization, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201)
            ->assertJsonMissingPath('previous_evaluation');
    }

    public function test_the_cadence_helper_counts_in_the_right_direction(): void
    {
        $organization = $this->organization();
        $subject      = $this->member($organization, 'Beneficiary');

        // Sous Carbon 3 les diffIn* sont signes : compter a l'envers renvoyait
        // une valeur negative, donc jamais superieure a l'echeance.
        $this->completedSession($subject, 8);
        $this->assertTrue(IdentityEvaluationPolicy::shouldSuggestFullEvaluation($subject->fresh()));

        EvaluationSession::where('identity_id', $subject->id)->delete();
        $this->completedSession($subject, 2);
        $this->assertFalse(IdentityEvaluationPolicy::shouldSuggestFullEvaluation($subject->fresh()));
    }

    public function test_a_never_evaluated_beneficiary_is_always_due(): void
    {
        $organization = $this->organization();
        $subject      = $this->member($organization, 'Beneficiary');

        $this->assertTrue(IdentityEvaluationPolicy::shouldSuggestFullEvaluation($subject));
    }
}
