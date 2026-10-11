<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'impact d'un evaluateur se deduit des evaluations qu'il a conduites.
 */
class EvaluatorImpactTest extends TestCase
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

    private function beneficiary(Organization $organization, ?string $municipality): Identity
    {
        $identity = $this->member($organization, 'Beneficiary');

        if ($municipality !== null) {
            $identity->personalProfile()->create(['municipality' => $municipality]);
        }

        return $identity;
    }

    private function conducted(
        Identity $subject,
        Identity $conductor,
        string $status = 'completed',
        array $extraContext = [],
    ): EvaluationSession {
        return EvaluationSession::create([
            'identity_id'  => $subject->id,
            'status'       => $status,
            'type'         => 'complete',
            'score'        => 0,
            'started_at'   => now()->subDays(3),
            'completed_at' => $status === 'completed' ? now()->subDays(3) : null,
            'context'      => array_merge(['agent_id' => (string) $conductor->id], $extraContext),
        ]);
    }

    public function test_the_impact_reflects_only_what_the_evaluator_conducted(): void
    {
        $organization = $this->organization();
        $ambassador   = $this->member($organization, 'Ambassador');
        $colleague    = $this->member($organization, 'FieldAgent');

        $awa    = $this->beneficiary($organization, 'Cocody');
        $fatou  = $this->beneficiary($organization, 'Yopougon');
        $others = $this->beneficiary($organization, 'Abobo');

        $this->conducted($awa, $ambassador);
        $this->conducted($fatou, $ambassador, 'in_progress');
        // Conduite par un collegue : ne doit pas compter dans son impact.
        $this->conducted($others, $colleague);

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertOk()
            ->assertJsonPath('women_reached', 2)
            // Fatou n'a pas fini sa visite : approchee, pas encore evaluee.
            ->assertJsonPath('women_evaluated', 1)
            ->assertJsonPath('evaluations_completed', 1)
            ->assertJsonPath('evaluations_in_progress', 1)
            ->assertJsonPath('municipalities_covered', 2)
            ->assertJsonPath('municipalities', ['Cocody', 'Yopougon']);
    }

    public function test_a_follow_up_visit_does_not_count_a_second_woman(): void
    {
        $organization = $this->organization();
        $ambassador   = $this->member($organization, 'Ambassador');
        $awa          = $this->beneficiary($organization, 'Cocody');

        $this->conducted($awa, $ambassador);
        $this->conducted($awa, $ambassador);

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertOk()
            ->assertJsonPath('women_reached', 1)
            ->assertJsonPath('evaluations_completed', 2);
    }

    public function test_urgent_referrals_are_counted(): void
    {
        $organization = $this->organization();
        $ambassador   = $this->member($organization, 'Ambassador');

        $this->conducted($this->beneficiary($organization, 'Cocody'), $ambassador);
        $this->conducted(
            $this->beneficiary($organization, 'Cocody'),
            $ambassador,
            'completed',
            ['urgent_referral' => ['reason' => 'pelvic_mass', 'raised_at' => now()->toISOString()]],
        );

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertOk()
            ->assertJsonPath('urgent_referrals', 1)
            ->assertJsonPath('women_reached', 2);
    }

    public function test_women_to_follow_are_those_whose_last_evaluation_concludes_to_a_risk(): void
    {
        $organization = $this->organization();
        $ambassador   = $this->member($organization, 'Ambassador');
        $colleague    = $this->member($organization, 'FieldAgent');

        $high = $this->beneficiary($organization, null);
        $this->conducted($high, $ambassador)->update(['risk_level' => 'high']);

        $moderate = $this->beneficiary($organization, null);
        $this->conducted($moderate, $ambassador)->update(['risk_level' => 'moderate']);

        $low = $this->beneficiary($organization, null);
        $this->conducted($low, $ambassador)->update(['risk_level' => 'low']);

        // Une visite de suivi plus recente l'a rassuree : elle sort de la liste.
        $reassured = $this->beneficiary($organization, null);
        $this->conducted($reassured, $ambassador)->update(['risk_level' => 'high']);
        $this->conducted($reassured, $ambassador)
            ->update(['risk_level' => 'low', 'completed_at' => now()]);

        // Un suivi encore en cours n'a rien conclu : elle reste a suivre.
        $pending = $this->beneficiary($organization, null);
        $this->conducted($pending, $ambassador)->update(['risk_level' => 'high']);
        $this->conducted($pending, $ambassador, 'in_progress');

        // Evaluee par un collegue : ce n'est pas a elle de la suivre.
        $other = $this->beneficiary($organization, null);
        $this->conducted($other, $colleague)->update(['risk_level' => 'high']);

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertOk()
            ->assertJsonPath('women_to_follow', 3);
    }

    public function test_a_new_evaluator_starts_at_zero(): void
    {
        $organization = $this->organization();
        $ambassador   = $this->member($organization, 'Ambassador');

        $this->actingAs($ambassador, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertOk()
            ->assertJsonPath('women_reached', 0)
            ->assertJsonPath('municipalities_covered', 0)
            ->assertJsonPath('active_since', null);
    }

    public function test_a_non_evaluator_is_refused(): void
    {
        $organization = $this->organization();
        $beneficiary  = $this->member($organization, 'Beneficiary');

        $this->actingAs($beneficiary, 'sanctum')
            ->getJson('/api/agent/impact')
            ->assertStatus(403);
    }
}
