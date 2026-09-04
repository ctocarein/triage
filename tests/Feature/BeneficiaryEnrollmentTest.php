<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function seedFirstQuestion(): void
    {
        DynamicQuestionTemplate::create([
            'key'           => 'enrollment_first_question',
            'text'          => 'Premiere question',
            'type'          => 'boolean',
            'answer_target' => 'evaluation',
            'order'         => 1,
            'bloc_key'      => 'enrollment_bloc',
            'is_active'     => true,
        ]);
    }

    private function defaultOrganizationId(): string
    {
        return config('identity.default_organization.id');
    }

    public function test_starting_a_session_by_phone_enrolls_the_beneficiary(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_phone' => '+22501020304',
            ])
            ->assertStatus(201)
            ->assertJsonPath('subject.is_new', true);

        $subjectId = $response->json('subject.identity_id');

        $this->assertDatabaseHas('credentials', [
            'identity_id' => $subjectId,
            'type'        => 'phone',
            'identifier'  => '+22501020304',
        ]);

        $this->assertDatabaseHas('memberships', [
            'identity_id'     => $subjectId,
            'organization_id' => $organization->id,
            'role'            => 'Beneficiary',
            'status'          => 'active',
        ]);

        $this->assertDatabaseHas('evaluation_sessions', ['identity_id' => $subjectId]);
    }

    public function test_a_known_phone_is_reused_instead_of_creating_a_duplicate(): void
    {
        $this->seedFirstQuestion();

        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);

        $first = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_phone' => '+22501020304',
            ])
            ->assertStatus(201);

        $subjectId     = $first->json('subject.identity_id');
        $identityCount = Identity::count();

        // Meme numero : l enrolement doit retomber sur la meme personne. Ici la
        // session precedente est encore ouverte, donc le demarrage est refuse --
        // ce qui compte est qu aucune identite en double n ait ete creee au passage.
        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_phone' => '+22501020304',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SESSION_IN_PROGRESS');

        $this->assertSame(
            1,
            Credential::where('identifier', '+22501020304')->count(),
            'Le numero ne doit exister qu une seule fois.'
        );

        $this->assertSame(
            $identityCount,
            Identity::count(),
            'Un numero deja connu ne doit pas creer une seconde identite.'
        );

        $this->assertSame(
            1,
            \App\Domains\Evaluation\Models\EvaluationSession::where('identity_id', $subjectId)->count()
        );
    }

    public function test_an_evaluator_without_an_organization_falls_back_to_the_default_one(): void
    {
        $this->seedFirstQuestion();

        $ambassador = $this->evaluator(null, 'Ambassador');

        $response = $this->actingAs($ambassador, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_phone' => '+22599887766',
            ])
            ->assertStatus(201);

        $subjectId  = $response->json('subject.identity_id');
        $defaultOrg = $this->defaultOrganizationId();

        $this->assertDatabaseHas('organizations', ['id' => $defaultOrg]);

        $this->assertDatabaseHas('memberships', [
            'identity_id'     => $subjectId,
            'organization_id' => $defaultOrg,
            'role'            => 'Beneficiary',
        ]);

        // L'ambassadeur y est rattache aussi, sinon il ne pourrait pas evaluer
        // la personne qu il vient d enroler.
        $this->assertDatabaseHas('memberships', [
            'identity_id'     => $ambassador->id,
            'organization_id' => $defaultOrg,
            'role'            => 'Ambassador',
        ]);
    }

    public function test_subject_id_and_subject_phone_are_mutually_exclusive(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->evaluator($organization);
        $subject      = $this->evaluator($organization, 'Beneficiary');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_id'    => $subject->id,
                'subject_phone' => '+22500000000',
            ])
            ->assertStatus(422);
    }

    public function test_a_non_evaluator_cannot_enroll_by_phone(): void
    {
        $organization = $this->organization('Org A');
        $actor        = $this->evaluator($organization, 'Beneficiary');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/evaluations/start', [
                'type'          => 'complete',
                'subject_phone' => '+22511223344',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'EVALUATOR_REQUIRED');

        $this->assertDatabaseMissing('credentials', ['identifier' => '+22511223344']);
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
