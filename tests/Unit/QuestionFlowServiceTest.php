<?php

namespace Tests\Unit;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\Navigation\AdaptiveSequencer;
use App\Domains\Evaluation\Services\Navigation\BlocRulesService;
use App\Domains\Evaluation\Services\Navigation\ConditionEvaluator;
use App\Domains\Evaluation\Services\Navigation\QuestionSequencer;
use App\Domains\Evaluation\Services\Navigation\RoutingEngine;
use App\Domains\Evaluation\Services\Scoring\RiskModelService;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class QuestionFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeService(): QuestionSequencer
    {
        $blocRules = Mockery::mock(BlocRulesService::class);
        $blocRules->shouldReceive('shouldSkip')->andReturn(false)->byDefault();

        $adaptive = Mockery::mock(AdaptiveSequencer::class);

        $riskModel = Mockery::mock(RiskModelService::class);

        // Ces deux dépendances ont été extraites de QuestionSequencer lors du
        // découpage Navigation/ : on utilise les implémentations réelles, elles
        // sont pures et n'accèdent pas à la base.
        return new QuestionSequencer(
            $blocRules,
            $adaptive,
            $riskModel,
            app(ConditionEvaluator::class),
            app(RoutingEngine::class),
        );
    }

    private function makeIdentity(): string
    {
        $id = (string) Str::uuid();
        DB::table('identities')->insert([
            'id'         => $id,
            'kind'       => 'person',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    private function makeSession(string $identityId, string $type = 'complete', ?string $currentBloc = null): EvaluationSession
    {
        $path = DecisionPath::create([
            'key'   => 'dp_qflow_test_' . uniqid(),
            'title' => 'Test Path',
        ]);

        return EvaluationSession::create([
            'identity_id'      => $identityId,
            'decision_path_id' => $path->id,
            'status'           => 'in_progress',
            'type'             => $type,
            'score'            => 0,
            'current_bloc'     => $currentBloc,
        ]);
    }

    private function makeQuestion(
        string $key,
        string $answerTarget,
        string $blocKey,
        int $order = 1
    ): DynamicQuestionTemplate {
        return DynamicQuestionTemplate::create([
            'key'           => $key,
            'text'          => $key,
            'type'          => 'text',
            'answer_target' => $answerTarget,
            'bloc_key'      => $blocKey,
            'order'         => $order,
            'is_active'     => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 1 : fix du bug hasValueInProfile — mapping 'personal_profile' → 'personal'
    // -------------------------------------------------------------------------

    /**
     * Bug fix — la clé du profil était cherchée via $question->answer_target
     * ('personal_profile') au lieu de 'personal'. La question devait donc toujours
     * s'afficher même quand le profil était rempli.
     */
    public function test_has_value_in_profile_correct_mapping(): void
    {
        $service = $this->makeService();

        $question = new DynamicQuestionTemplate();
        $question->answer_target = 'personal_profile';
        $question->key           = 'birth_date';
        $question->visible_if    = null;

        $profile = [
            'personal' => ['birth_date' => '1990-01-01'],
            'medical'  => [],
        ];

        // Le profil contient birth_date → la question doit être masquée
        $visible = $service->shouldBeVisible($question, [], $profile);

        $this->assertFalse($visible, 'La question de profil déjà renseignée doit être masquée.');
    }

    // -------------------------------------------------------------------------
    // Test 2 : injection du profil dans answersByKey
    // -------------------------------------------------------------------------

    /**
     * getSessionContext() doit injecter les champs du profil persisté dans `answers`
     * pour que les conditions visible_if (source:question) puissent les résoudre
     * même quand la question de profil a été répondue lors d'une session précédente.
     */
    public function test_profile_fields_injected_into_answers_context(): void
    {
        $service    = $this->makeService();
        $identityId = $this->makeIdentity();

        // Créer un profil personnel avec birth_date
        PersonalProfile::create([
            'identity_id' => $identityId,
            'birth_date'  => '1997-03-15',
        ]);

        $session = $this->makeSession($identityId, 'thematic');

        $context = $service->getSessionContext($session);

        $this->assertArrayHasKey('birth_date', $context['answers'],
            'birth_date du PersonalProfile doit être injecté dans answers.');
        $this->assertSame('1997-03-15', $context['answers']['birth_date']);
    }

    // -------------------------------------------------------------------------
    // Test 3 : session thématique → aucune question de profil proposée
    // -------------------------------------------------------------------------

    /**
     * Pour une session de type 'thematic', le profil a déjà été capturé lors de la
     * session complete initiale. getNextQuestions() ne doit proposer aucune question
     * avec answer_target = personal_profile ou medical_profile.
     */
    public function test_thematic_session_does_not_propose_profile_questions(): void
    {
        $service    = $this->makeService();
        $identityId = $this->makeIdentity();

        // Seule question du bloc : cible le profil personnel
        $this->makeQuestion(
            key: 'first_name',
            answerTarget: 'personal_profile',
            blocKey: 'profil_bloc',
        );

        $session = $this->makeSession($identityId, 'thematic', 'profil_bloc');

        $questions = $service->getNextQuestions($session);

        $this->assertEmpty($questions,
            'Une session thématique ne doit jamais proposer de questions de profil.');
    }

    // -------------------------------------------------------------------------
    // Test 4 : session complete → questions de profil proposées si profil vide
    // -------------------------------------------------------------------------

    /**
     * Lors de la session complete initiale (type=complete) et sans profil existant,
     * les questions de profil doivent être proposées normalement.
     */
    public function test_complete_session_proposes_profile_questions_when_not_yet_filled(): void
    {
        $service    = $this->makeService();
        $identityId = $this->makeIdentity();

        // Pas de PersonalProfile existant pour cette identité

        $this->makeQuestion(
            key: 'first_name',
            answerTarget: 'personal_profile',
            blocKey: 'profil_bloc',
        );

        $session = $this->makeSession($identityId, 'complete', 'profil_bloc');

        $questions = $service->getNextQuestions($session);

        $this->assertNotEmpty($questions,
            'La session complete doit proposer les questions de profil quand le profil est vide.');
        $this->assertSame('personal_profile', $questions[0]->answer_target);
    }

    // -------------------------------------------------------------------------
    // Test 5 : session complete + profil rempli → question masquée
    // -------------------------------------------------------------------------

    /**
     * Lors d'une session complete, si le champ demandé existe déjà dans le profil,
     * la question est masquée par shouldBeVisible() (bug fix hasValueInProfile).
     */
    public function test_complete_session_hides_profile_question_already_in_profile(): void
    {
        $service = $this->makeService();

        $question = new DynamicQuestionTemplate();
        $question->answer_target = 'medical_profile';
        $question->key           = 'is_pregnant';
        $question->visible_if    = null;

        $profile = [
            'personal' => [],
            'medical'  => ['is_pregnant' => true],
        ];

        $visible = $service->shouldBeVisible($question, [], $profile);

        $this->assertFalse($visible,
            'La question medical_profile déjà renseignée doit être masquée même en session complete.');
    }
}
