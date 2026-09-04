<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BeneficiaryEnrollmentTest extends TestCase
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

    private function evaluator(?Organization $organization, string $role = 'FieldAgent'): Identity
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

    /** Seule question du parcours : le numero, pour qu'elle soit servie en premier. */
    private function seedPhoneQuestion(): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::create([
            'key'           => 'phone',
            'text'          => 'Quel est votre numero ?',
            'type'          => 'text',
            'bloc_key'      => 'patient_context',
            'answer_target' => 'credential',
            'order'         => 1,
            'is_active'     => true,
        ]);
    }

    private function defaultOrganizationId(): string
    {
        return config('identity.default_organization.id');
    }

    /** @return array{session: string, question: int} */
    private function startForNewSubject(Identity $evaluator): array
    {
        $response = $this->actingAs($evaluator, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'        => 'complete',
                'new_subject' => true,
            ])
            ->assertStatus(201);

        return [
            'session'  => $response->json('session.public_id'),
            'question' => $response->json('question.id'),
            'subject'  => $response->json('subject.identity_id'),
        ];
    }

    public function test_starting_for_a_new_subject_creates_a_beneficiary_without_credentials(): void
    {
        $this->seedPhoneQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);

        $started = $this->startForNewSubject($agent);

        $this->assertDatabaseHas('memberships', [
            'identity_id'     => $started['subject'],
            'organization_id' => $organization->id,
            'role'            => 'Beneficiary',
            'status'          => 'active',
        ]);

        // Le numero n'est pas demande au demarrage : aucune credential encore.
        $this->assertDatabaseMissing('credentials', ['identity_id' => $started['subject']]);
        $this->assertDatabaseHas('evaluation_sessions', ['identity_id' => $started['subject']]);
    }

    public function test_answering_the_phone_question_issues_the_default_pin(): void
    {
        $this->seedPhoneQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);
        $started      = $this->startForNewSubject($agent);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/evaluations/{$started['session']}/advance", [
                'dynamic_question_template_id' => $started['question'],
                'value'                        => '0701020304',
            ])
            ->assertStatus(200);

        $credential = Credential::where('identity_id', $started['subject'])
            ->where('type', 'phone')
            ->firstOrFail();

        $this->assertSame('0701020304', $credential->identifier);
        $this->assertTrue($credential->must_change_password);
        $this->assertTrue(
            Hash::check(config('identity.default_pin'), $credential->secret_hash),
            'Le PIN par defaut doit ouvrir le compte.'
        );
    }

    public function test_the_credentials_are_returned_once_the_evaluation_is_complete(): void
    {
        $this->seedPhoneQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);
        $started      = $this->startForNewSubject($agent);

        // Seule question du parcours : y repondre termine la session.
        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/evaluations/{$started['session']}/advance", [
                'dynamic_question_template_id' => $started['question'],
                'value'                        => '0701020304',
            ])
            ->assertStatus(200)
            ->assertJsonPath('account.phone', '0701020304')
            ->assertJsonPath('account.pin', config('identity.default_pin'))
            ->assertJsonPath('account.must_change_password', true);
    }

    public function test_a_known_phone_reconciles_the_session_onto_the_existing_person(): void
    {
        $this->seedPhoneQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);

        // Beneficiaire deja enrolee lors d'une visite precedente.
        $known = $this->evaluator($organization, 'Beneficiary');
        Credential::create([
            'identity_id' => $known->id,
            'type'        => 'phone',
            'identifier'  => '0701020304',
            'verified_at' => now(),
        ]);

        $started   = $this->startForNewSubject($agent);
        $duplicate = $started['subject'];

        $this->assertNotSame($known->id, $duplicate);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/evaluations/{$started['session']}/advance", [
                'dynamic_question_template_id' => $started['question'],
                'value'                        => '0701020304',
            ])
            ->assertStatus(200);

        $session = EvaluationSession::where('public_id', $started['session'])->firstOrFail();

        $this->assertSame(
            (string) $known->id,
            (string) $session->identity_id,
            'La session doit basculer sur la personne deja connue.'
        );

        $this->assertSame($duplicate, $session->context['reconciled_from']);

        // Le doublon cree au demarrage est neutralise, pas supprime.
        $this->assertDatabaseHas('identities', [
            'id'     => $duplicate,
            'status' => 'anonymized',
        ]);

        // Aucun second compte sur ce numero.
        $this->assertSame(1, Credential::where('identifier', '0701020304')->count());
    }

    public function test_subject_id_and_new_subject_are_mutually_exclusive(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);
        $subject      = $this->evaluator($organization, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'        => 'complete',
                'subject_id'  => $subject->id,
                'new_subject' => true,
            ])
            ->assertStatus(422);
    }

    public function test_a_non_evaluator_cannot_create_a_subject(): void
    {
        $organization = $this->organization('Org A');
        $actor        = $this->evaluator($organization, 'Beneficiary');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'        => 'complete',
                'new_subject' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'EVALUATOR_REQUIRED');
    }

    public function test_an_ambassador_can_enroll_through_the_dedicated_endpoint(): void
    {
        $organization = $this->organization('Org A');
        $ambassador   = $this->evaluator($organization, 'Ambassador');

        $this->actingAs($ambassador, 'sanctum')
            ->postJson('/api/agent/beneficiaries', ['phone' => '+22544556677'])
            ->assertStatus(201)
            ->assertJsonPath('is_new', true);

        $this->assertDatabaseHas('credentials', ['identifier' => '+22544556677']);
    }

    public function test_an_evaluator_without_an_organization_is_no_longer_refused_enrollment(): void
    {
        $agent = $this->evaluator(null, 'FieldAgent');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/agent/beneficiaries', ['phone' => '+22533445566'])
            ->assertStatus(201);

        $this->assertDatabaseHas('memberships', [
            'identity_id'     => $agent->id,
            'organization_id' => $this->defaultOrganizationId(),
            'role'            => 'FieldAgent',
        ]);
    }
}
