<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\EvaluationFlowService;
use App\Domains\Evaluation\Services\ProfileUpdaterService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\Organization;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Database\Seeders\HealthMapSeeder;
use Database\Seeders\MunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le district sanitaire est la cle d'orientation : c'est lui qui dira vers
 * quelle structure une femme peut etre envoyee. Il est declare par
 * l'evaluatrice, pas deduit du quartier -- une femme ne connait pas son
 * district, une sage-femme si.
 */
class HealthDistrictQuestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            MunicipalitySeeder::class,
            HealthMapSeeder::class,
            QuestionGroupSeeder::class,
            ThematicBlocSeeder::class,
            DynamicQuestionTemplateSeeder::class,
        ]);
    }

    private function question(): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::where('key', 'health_district_id')->firstOrFail();
    }

    public function test_the_options_carry_the_district_code_not_its_id(): void
    {
        // Les identifiants auto-incrementes different d'un environnement a
        // l'autre : un catalogue exporte rattacherait alors les beneficiaires
        // a des districts au hasard. Le code, lui, vient de la carte sanitaire.
        $values = array_column($this->question()->options, 'value');

        $this->assertContains('DS-SAN-PEDRO', $values);
        $this->assertSame(HealthDistrict::count(), count($values));

        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^DS-/', (string) $value);
        }
    }

    public function test_it_is_asked_after_the_municipality_and_before_the_phone(): void
    {
        $orders = DynamicQuestionTemplate::where('bloc_key', 'patient_context')
            ->pluck('order', 'key');

        $this->assertTrue($orders['municipality'] < $orders['health_district_id']);
        $this->assertTrue($orders['health_district_id'] < $orders['phone']);
    }

    public function test_answering_with_a_code_stores_the_foreign_key(): void
    {
        $district = HealthDistrict::where('code', 'DS-SAN-PEDRO')->firstOrFail();
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        $session = EvaluationSession::create([
            'identity_id' => $identity->id,
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
        ]);

        $session->answers()->create([
            'dynamic_question_template_id' => $this->question()->id,
            'value'                        => 'DS-SAN-PEDRO',
        ]);

        app(ProfileUpdaterService::class)->syncFromAnswers($session->fresh());

        $profile = PersonalProfile::where('identity_id', $identity->id)->firstOrFail();

        $this->assertSame($district->id, $profile->health_district_id);
        $this->assertSame('San-Pédro', $profile->healthDistrict->name);
    }

    public function test_an_unknown_code_leaves_the_district_empty_rather_than_breaking(): void
    {
        // Mieux vaut un district manquant, visible dans les donnees, qu'une
        // violation de contrainte au milieu d'un entretien.
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        $session = EvaluationSession::create([
            'identity_id' => $identity->id,
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
        ]);

        $session->answers()->create([
            'dynamic_question_template_id' => $this->question()->id,
            'value'                        => 'DS-NEXISTEPAS',
        ]);

        app(ProfileUpdaterService::class)->syncFromAnswers($session->fresh());

        $profile = PersonalProfile::where('identity_id', $identity->id)->first();

        $this->assertNull($profile?->health_district_id);
    }

    public function test_the_payload_suggests_the_evaluator_own_district(): void
    {
        $district = HealthDistrict::where('code', 'DS-SAN-PEDRO')->firstOrFail();

        $organization = Organization::create([
            'type'               => 'clinic',
            'name'               => 'CHR San-Pédro',
            'status'             => 'active',
            'health_district_id' => $district->id,
        ]);

        $ambassador = Identity::create(['kind' => 'person', 'status' => 'active']);
        Membership::create([
            'identity_id'     => $ambassador->id,
            'organization_id' => $organization->id,
            'role'            => 'Ambassador',
            'status'          => 'active',
        ]);

        $session = EvaluationSession::create([
            'identity_id' => Identity::create(['kind' => 'person', 'status' => 'active'])->id,
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
            'context'     => ['agent_id' => (string) $ambassador->id],
        ]);

        $payload = $this->payloadFor($this->question(), $session);

        $this->assertSame('DS-SAN-PEDRO', $payload['suggested_value']);
    }

    public function test_nothing_is_suggested_when_the_evaluator_has_no_district(): void
    {
        $organization = Organization::create([
            'type'   => 'clinic',
            'name'   => 'Structure sans district',
            'status' => 'active',
        ]);

        $ambassador = Identity::create(['kind' => 'person', 'status' => 'active']);
        Membership::create([
            'identity_id'     => $ambassador->id,
            'organization_id' => $organization->id,
            'role'            => 'Ambassador',
            'status'          => 'active',
        ]);

        $session = EvaluationSession::create([
            'identity_id' => Identity::create(['kind' => 'person', 'status' => 'active'])->id,
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
            'context'     => ['agent_id' => (string) $ambassador->id],
        ]);

        $this->assertNull($this->payloadFor($this->question(), $session)['suggested_value']);
    }

    public function test_other_questions_carry_no_suggestion(): void
    {
        $session = EvaluationSession::create([
            'identity_id' => Identity::create(['kind' => 'person', 'status' => 'active'])->id,
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
        ]);

        $municipality = DynamicQuestionTemplate::where('key', 'municipality')->firstOrFail();

        $this->assertNull($this->payloadFor($municipality, $session)['suggested_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(DynamicQuestionTemplate $question, EvaluationSession $session): array
    {
        $service = app(EvaluationFlowService::class);

        $method = new \ReflectionMethod($service, 'buildQuestionPayloadFromTemplate');

        return $method->invoke($service, $question, $session);
    }
}
