<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistedEvaluationAuthorizationTest extends TestCase
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

    private function identityWithRole(?Organization $organization, string $role): Identity
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

    private function seedFirstQuestion(): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::create([
            'key'           => 'scope_first_question',
            'text'          => 'Premiere question',
            'type'          => 'boolean',
            'answer_target' => 'evaluation',
            'order'         => 1,
            'bloc_key'      => 'scope_bloc',
            'is_active'     => true,
        ]);
    }

    public function test_an_actor_without_an_evaluator_role_cannot_start_for_a_subject(): void
    {
        $organization = $this->organization('Org A');
        $actor        = $this->identityWithRole($organization, 'Beneficiary');
        $subject      = $this->identityWithRole($organization, 'Beneficiary');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'EVALUATOR_REQUIRED');

        $this->assertDatabaseMissing('evaluation_sessions', ['identity_id' => $subject->id]);
    }

    public function test_an_evaluator_cannot_start_for_a_subject_outside_their_organization(): void
    {
        $agentOrg   = $this->organization('Org A');
        $strangeOrg = $this->organization('Org B');

        $agent   = $this->identityWithRole($agentOrg, 'FieldAgent');
        $subject = $this->identityWithRole($strangeOrg, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBJECT_OUT_OF_SCOPE');

        $this->assertDatabaseMissing('evaluation_sessions', ['identity_id' => $subject->id]);
    }

    public function test_an_evaluator_role_held_elsewhere_does_not_grant_access(): void
    {
        // L'acteur est FieldAgent dans A, simple membre dans B ou vit le sujet.
        $orgA = $this->organization('Org A');
        $orgB = $this->organization('Org B');

        $agent = $this->identityWithRole($orgA, 'FieldAgent');
        Membership::create([
            'identity_id'     => $agent->id,
            'organization_id' => $orgB->id,
            'role'            => 'Beneficiary',
            'status'          => 'active',
        ]);

        $subject = $this->identityWithRole($orgB, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBJECT_OUT_OF_SCOPE');
    }

    public function test_a_field_agent_can_start_for_a_beneficiary_of_their_organization(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->identityWithRole($organization, 'FieldAgent');
        $subject      = $this->identityWithRole($organization, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('evaluation_sessions', ['identity_id' => $subject->id]);
    }

    public function test_an_ambassador_can_start_for_a_beneficiary_of_their_organization(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $ambassador   = $this->identityWithRole($organization, 'Ambassador');
        $subject      = $this->identityWithRole($organization, 'Beneficiary');

        $this->actingAs($ambassador, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201);

        $session = EvaluationSession::where('identity_id', $subject->id)->firstOrFail();
        $this->assertSame((string) $ambassador->id, (string) $session->context['agent_id']);
    }

    public function test_an_unknown_subject_is_rejected_by_validation(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->identityWithRole($organization, 'FieldAgent');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => '3f2b6c1e-0000-4000-8000-000000000000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject_id');
    }

    public function test_the_session_is_bound_to_the_evaluator_token_not_the_subject(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->identityWithRole($organization, 'FieldAgent');
        $subject      = $this->identityWithRole($organization, 'Beneficiary');

        $token = $agent->createToken('field-visit');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201);

        $session = EvaluationSession::where('identity_id', $subject->id)->firstOrFail();

        $this->assertSame(
            (int) $token->accessToken->id,
            (int) $session->context['token_id'],
            "La session doit etre liee au token de l'evaluateur, le sujet n'en a pas."
        );
    }

    public function test_an_evaluator_loses_access_once_their_membership_is_revoked(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->identityWithRole($organization, 'FieldAgent');
        $subject      = $this->identityWithRole($organization, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'       => 'complete',
                'subject_id' => $subject->id,
            ])
            ->assertStatus(201);

        $session = EvaluationSession::where('identity_id', $subject->id)->firstOrFail();

        // Tant que le membership est actif, l'agent lit la session.
        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/evaluations/{$session->public_id}/state")
            ->assertStatus(200);

        // Le contexte porte toujours agent_id, mais le role ne suffit plus.
        Membership::where('identity_id', $agent->id)
            ->where('role', 'FieldAgent')
            ->update(['status' => 'inactive']);

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/evaluations/{$session->public_id}/state")
            ->assertStatus(403);
    }
}
