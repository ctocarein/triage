<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryDirectoryTest extends TestCase
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

    private function beneficiary(
        Organization $organization,
        string $phone,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Identity {
        $identity = $this->member($organization, 'Beneficiary');

        Credential::create([
            'identity_id' => $identity->id,
            'type'        => 'phone',
            'identifier'  => $phone,
            'verified_at' => now(),
        ]);

        if ($firstName || $lastName) {
            $identity->personalProfile()->create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
        }

        return $identity;
    }

    public function test_an_evaluator_lists_the_beneficiaries_of_their_organization(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');

        $this->beneficiary($organization, '0700000001', 'Aminata', 'Diallo');
        $this->beneficiary($organization, '0700000002', 'Awa', 'Traore');

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $names = collect($response->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Aminata Diallo', 'Awa Traore'], $names);
    }

    public function test_beneficiaries_of_another_organization_are_not_listed(): void
    {
        $mine      = $this->organization('Org A');
        $elsewhere = $this->organization('Org B');

        $agent = $this->member($mine, 'FieldAgent');
        $this->beneficiary($mine, '0700000001', 'Aminata');
        $this->beneficiary($elsewhere, '0700000009', 'Inconnue');

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame('Aminata', $response->json('data.0.name'));
    }

    public function test_an_ambassador_sees_the_same_directory(): void
    {
        $organization = $this->organization('Org A');
        $ambassador   = $this->member($organization, 'Ambassador');
        $this->beneficiary($organization, '0700000001', 'Aminata');

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_non_evaluator_is_refused(): void
    {
        $organization = $this->organization('Org A');
        $beneficiary  = $this->member($organization, 'Beneficiary');

        $this->actingAs($beneficiary, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertStatus(403);
    }

    public function test_the_directory_can_be_searched_by_phone_or_name(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');

        $this->beneficiary($organization, '0700000001', 'Aminata', 'Diallo');
        $this->beneficiary($organization, '0755555555', 'Awa', 'Traore');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries?search=5555')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Awa Traore');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries?search=Diallo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Aminata Diallo');
    }

    public function test_the_directory_can_be_filtered_on_those_never_evaluated(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');

        $evaluated = $this->beneficiary($organization, '0700000001', 'Aminata');
        $this->beneficiary($organization, '0700000002', 'Jamais');

        EvaluationSession::create([
            'identity_id' => $evaluated->id,
            'status'      => 'completed',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now()->subDay(),
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries?status=never')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Jamais');
    }

    public function test_the_risk_level_is_hidden_from_an_evaluator_who_did_not_conduct_the_session(): void
    {
        $organization = $this->organization('Org A');
        $conductor    = $this->member($organization, 'FieldAgent');
        $colleague    = $this->member($organization, 'Ambassador');
        $subject      = $this->beneficiary($organization, '0700000001', 'Aminata');

        EvaluationSession::create([
            'identity_id' => $subject->id,
            'status'      => 'completed',
            'type'        => 'complete',
            'score'       => 12,
            'risk_level'  => 'high',
            'started_at'  => now()->subDay(),
            'context'     => ['agent_id' => $conductor->id],
        ]);

        // Celui qui a conduit la visite voit le resultat.
        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('data.0.last_evaluation.risk_level', 'high');

        // Un collegue de la meme organisation sait qu'une evaluation existe,
        // mais pas ce qu'elle a conclu.
        $response = $this->actingAs($colleague, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('data.0.last_evaluation.status', 'completed');

        $this->assertArrayNotHasKey('risk_level', $response->json('data.0.last_evaluation'));
    }

    public function test_the_directory_can_be_filtered_on_those_to_follow(): void
    {
        $organization = $this->organization('Org A');
        $agent        = $this->member($organization, 'FieldAgent');
        $colleague    = $this->member($organization, 'Ambassador');

        $evaluate = function (Identity $subject, Identity $conductor, string $risk): void {
            EvaluationSession::create([
                'identity_id'  => $subject->id,
                'status'       => 'completed',
                'type'         => 'complete',
                'score'        => 0,
                'risk_level'   => $risk,
                'started_at'   => now()->subDay(),
                'completed_at' => now()->subDay(),
                'context'      => ['agent_id' => (string) $conductor->id],
            ]);
        };

        $evaluate($this->beneficiary($organization, '0700000001', 'Aminata'), $agent, 'high');
        $evaluate($this->beneficiary($organization, '0700000002', 'Rassuree'), $agent, 'low');
        $this->beneficiary($organization, '0700000003', 'Jamais');

        // Le risque conclu par un collegue ne doit pas fuiter par le filtre.
        $evaluate($this->beneficiary($organization, '0700000004', 'Collegue'), $colleague, 'high');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries?status=to_follow')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Aminata');
    }

    public function test_an_evaluator_without_an_organization_gets_an_empty_directory(): void
    {
        $agent = $this->member(null, 'FieldAgent');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/agent/beneficiaries')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }
}
