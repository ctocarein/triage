<?php

namespace Tests\Feature;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Services\Navigation\RoutingEngine;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvaluationRoutingCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // On passe par l'orchestrateur canonique : le catalogue a été redécoupé
        // (PatientContext, PatientDemographics, GynecologicBackground, …) et la
        // liste figée précédemment ici ne produisait plus la moitié des clés
        // attendues — PersonalProfileQuestionSeeder est même devenu un no-op.
        $this->seed([
            CommuneSeeder::class,
            QuestionGroupSeeder::class,
            ThematicBlocSeeder::class,
            DynamicQuestionTemplateSeeder::class,
        ]);
    }

    private function makeSession(): EvaluationSession
    {
        $identityId = (string) Str::uuid();

        DB::table('identities')->insert([
            'id' => $identityId,
            'kind' => 'person',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = DecisionPath::create([
            'key' => 'dp_catalog_test_' . uniqid(),
            'title' => 'Catalog test path',
        ]);

        return EvaluationSession::create([
            'identity_id' => $identityId,
            'decision_path_id' => $path->id,
            'status' => 'in_progress',
            'type' => 'complete',
            'score' => 0,
            'started_at' => now(),
        ]);
    }

    public function test_cmu_yes_asks_for_cmu_number_before_continuing_profile(): void
    {
        $session = $this->makeSession();
        $question = DynamicQuestionTemplate::where('key', 'cmu')->firstOrFail();

        $routing = app(RoutingEngine::class)->applyRoutingRules(
            $session,
            $question,
            true,
            [
                'answers' => [],
                'profile' => ['personal' => [], 'medical' => []],
            ],
        );

        $this->assertSame('jump_question', $routing['action']);
        $this->assertSame('cmu_number', $routing['question_key']);
    }

    /**
     * Remplace test_occupation_queues_lifestyle_questions_in_logical_order().
     *
     * La question `occupation` a été retirée du catalogue, et avec elle le seul
     * usage du routage `queue:` : les questions de mode de vie ne sont plus
     * empilées dynamiquement mais séquencées par leur `order` dans le bloc
     * `lifestyle`. On vérifie donc désormais cet ordre structurel.
     */
    public function test_lifestyle_questions_are_ordered_logically_within_their_bloc(): void
    {
        $keys = DynamicQuestionTemplate::where('bloc_key', 'lifestyle')
            ->orderBy('order')
            ->pluck('key')
            ->all();

        $this->assertSame(
            ['tabac', 'tabac_paquets_annee', 'alcool', 'activite_physique', 'stress_chronique', 'poids_kg', 'taille_cm'],
            $keys,
        );
    }

    public function test_breast_mass_yes_does_not_jump_out_of_breast_bloc(): void
    {
        $session = $this->makeSession();
        $question = DynamicQuestionTemplate::where('key', 'breast_mass')->firstOrFail();

        $routing = app(RoutingEngine::class)->applyRoutingRules(
            $session,
            $question,
            true,
            [
                'answers' => [],
                'profile' => ['personal' => [], 'medical' => []],
            ],
        );

        $this->assertNull($routing);
    }

    public function test_breast_mass_follow_up_questions_stay_visible_after_positive_answer(): void
    {
        $changeQuestion = DynamicQuestionTemplate::where('key', 'breast_mass_change')->firstOrFail();
        $durationQuestion = DynamicQuestionTemplate::where('key', 'breast_mass_duration')->firstOrFail();

        $answers = ['breast_mass' => true];
        $profile = ['personal' => [], 'medical' => []];

        $this->assertTrue(
            app(\App\Domains\Evaluation\Services\Navigation\ConditionEvaluator::class)
                ->shouldBeVisible($changeQuestion, $answers, $profile)
        );

        $this->assertTrue(
            app(\App\Domains\Evaluation\Services\Navigation\ConditionEvaluator::class)
                ->shouldBeVisible($durationQuestion, $answers, $profile)
        );
    }

    /**
     * `profil_identite` n'est plus un bloc mais un *groupe* : le découpage
     * thématique a introduit le bloc `patient_demographics`, rattaché à ce
     * groupe. Le numéro de CMU reste donc bien dans la section « profil », et il
     * est persisté dans le profil personnel (donnée administrative, pas médicale)
     * — cohérent avec la question `cmu` qui le précède.
     */
    public function test_cmu_number_stays_in_the_identity_group_and_persists_to_personal_profile(): void
    {
        $question = DynamicQuestionTemplate::where('key', 'cmu_number')->firstOrFail();

        $this->assertSame('patient_demographics', $question->bloc_key);
        $this->assertSame('personal_profile', $question->answer_target);

        $bloc = ThematicBloc::with('questionGroup')->where('key', $question->bloc_key)->firstOrFail();

        $this->assertSame('profil_identite', $bloc->questionGroup?->key);
    }

    public function test_pelvic_mass_yes_routes_to_follow_up_question_before_urgent_end(): void
    {
        $session = $this->makeSession();
        $question = DynamicQuestionTemplate::where('key', 'pelvic_mass')->firstOrFail();

        $routing = app(RoutingEngine::class)->applyRoutingRules(
            $session,
            $question,
            true,
            [
                'answers' => [],
                'profile' => ['personal' => [], 'medical' => []],
            ],
        );

        $this->assertSame('jump_question', $routing['action']);
        $this->assertSame('pelvic_mass_since', $routing['question_key']);
    }

    public function test_pelvic_mass_since_ends_session_as_urgent_referral(): void
    {
        $session = $this->makeSession();
        $question = DynamicQuestionTemplate::where('key', 'pelvic_mass_since')->firstOrFail();

        $routing = app(RoutingEngine::class)->applyRoutingRules(
            $session,
            $question,
            '1_4w',
            [
                'answers' => ['pelvic_mass' => true],
                'profile' => ['personal' => [], 'medical' => []],
            ],
        );

        $session->refresh();

        $this->assertSame('end_session', $routing['action']);
        $this->assertSame('cancelled', $session->status);
        $this->assertSame('urgent_referral', data_get($session->context, 'termination_type'));
        $this->assertSame('urgent_pelvic_mass', data_get($session->context, 'termination_reason'));
    }

    public function test_breast_palpation_experience_is_shown_only_when_autopalpation_is_not_practiced(): void
    {
        $question = DynamicQuestionTemplate::where('key', 'breast_palpation_experience')->firstOrFail();
        $profile = ['personal' => [], 'medical' => []];
        $evaluator = app(\App\Domains\Evaluation\Services\Navigation\ConditionEvaluator::class);

        $this->assertTrue($evaluator->shouldBeVisible($question, ['breast_palpation' => false], $profile));
        $this->assertFalse($evaluator->shouldBeVisible($question, ['breast_palpation' => true], $profile));
    }

    public function test_screening_follow_ups_are_hidden_when_last_screening_is_never(): void
    {
        $screeningType = DynamicQuestionTemplate::where('key', 'screening_type')->firstOrFail();
        $screeningResult = DynamicQuestionTemplate::where('key', 'last_screening_result')->firstOrFail();
        $profile = ['personal' => [], 'medical' => []];
        $answers = ['last_screening_date' => 'never'];
        $evaluator = app(\App\Domains\Evaluation\Services\Navigation\ConditionEvaluator::class);

        $this->assertFalse($evaluator->shouldBeVisible($screeningType, $answers, $profile));
        $this->assertFalse($evaluator->shouldBeVisible($screeningResult, $answers, $profile));
    }

    public function test_pregnancy_follow_ups_are_hidden_when_no_prior_pregnancies(): void
    {
        $abortions = DynamicQuestionTemplate::where('key', 'number_of_abortions')->firstOrFail();
        $children = DynamicQuestionTemplate::where('key', 'number_of_children')->firstOrFail();
        $profile = ['personal' => [], 'medical' => []];
        $answers = ['number_of_pregnancies' => 0];
        $evaluator = app(\App\Domains\Evaluation\Services\Navigation\ConditionEvaluator::class);

        $this->assertFalse($evaluator->shouldBeVisible($abortions, $answers, $profile));
        $this->assertFalse($evaluator->shouldBeVisible($children, $answers, $profile));
    }
}
