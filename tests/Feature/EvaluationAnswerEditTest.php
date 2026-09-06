<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corriger une reponse deja validee.
 *
 * L'evaluateur transcrit une conversation : il se reprend constamment. Le guard
 * doit donc distinguer corriger -- recevable -- de repondre en avance a une
 * question jamais servie -- refuse. Et une correction doit reellement reposer
 * les questions qu'elle rend caduques, ce qui demande a la fois d'ecarter les
 * reponses invalidees du contexte et de liberer les blocs concernes.
 */
class EvaluationAnswerEditTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $key, string $bloc, int $order): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::create([
            'key'           => $key,
            'text'          => 'Question '.$key,
            'type'          => 'boolean',
            'answer_target' => 'evaluation',
            'bloc_key'      => $bloc,
            'order'         => $order,
            'is_active'     => true,
        ]);
    }

    private function makeSession(Identity $identity, DynamicQuestionTemplate $current): EvaluationSession
    {
        return EvaluationSession::create([
            'identity_id'         => $identity->id,
            'current_question_id' => $current->id,
            'current_bloc'        => $current->bloc_key,
            'status'              => 'in_progress',
            'type'                => 'complete',
            'score'               => 0,
            'started_at'          => now(),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function answer(
        Identity $identity,
        EvaluationSession $session,
        DynamicQuestionTemplate $question,
        bool $value,
    ): ?array {
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$session->public_id}/advance", [
                'dynamic_question_template_id' => $question->id,
                'value' => $value,
            ]);

        $res->assertOk();

        return $res->json('question');
    }

    public function test_correcting_an_already_answered_question_is_accepted(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
        $q1 = $this->question('edit_q1', 'edit_bloc', 1);
        $q2 = $this->question('edit_q2', 'edit_bloc', 2);
        $session = $this->makeSession($identity, $q1);

        $next = $this->answer($identity, $session, $q1, true);
        $this->assertSame('edit_q2', $next['key']);

        // q1 n'est plus la question courante, mais elle a ete servie : la
        // corriger est recevable.
        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$session->public_id}/advance", [
                'dynamic_question_template_id' => $q1->id,
                'value' => false,
            ])
            ->assertOk();

        $corrected = EvaluationAnswer::where('evaluation_session_id', $session->id)
            ->where('dynamic_question_template_id', $q1->id)
            ->firstOrFail();

        $this->assertFalse($corrected->value, 'La correction doit avoir remplace la valeur.');

        // Une seule reponse : la correction met a jour en place plutot que de
        // creer un doublon, ce que l'index unique (session, question) garantit.
        $this->assertSame(
            1,
            EvaluationAnswer::where('evaluation_session_id', $session->id)->count()
        );
        unset($q2);
    }

    public function test_a_question_never_served_is_still_refused(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
        $q1 = $this->question('edit_q1', 'edit_bloc', 1);
        $q2 = $this->question('edit_q2', 'edit_bloc', 2);
        $session = $this->makeSession($identity, $q1);

        // Sauter en avant reste ferme : c'est le trou que le guard existe pour
        // combler, et l'assouplissement ne doit pas le rouvrir.
        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$session->public_id}/advance", [
                'dynamic_question_template_id' => $q2->id,
                'value' => true,
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('evaluation_answers', [
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $q2->id,
        ]);
        unset($q1);
    }

    public function test_a_correction_reopens_the_blocs_it_invalidates(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        // Deux blocs : le premier est epuise -- donc marque visite -- avant que
        // la correction n'intervienne.
        $q1 = $this->question('edit_q1', 'bloc_un', 1);
        $q2 = $this->question('edit_q2', 'bloc_un', 2);
        $q3 = $this->question('edit_q3', 'bloc_deux', 3);

        $session = $this->makeSession($identity, $q1);

        $next = $this->answer($identity, $session, $q1, true);
        $this->assertSame('edit_q2', $next['key']);

        $next = $this->answer($identity, $session, $q2, true);
        $this->assertSame('edit_q3', $next['key'], 'bloc_un doit etre epuise et bloc_deux servi.');

        $session->refresh();
        $this->assertContains('bloc_un', $session->visited_blocs ?? [], 'bloc_un doit etre marque visite.');

        // Correction de la premiere reponse.
        $next = $this->answer($identity, $session, $q1, false);

        $session->refresh();

        // A2 : les reponses en aval sont invalidees...
        $downstream = EvaluationAnswer::where('evaluation_session_id', $session->id)
            ->where('dynamic_question_template_id', $q2->id)
            ->firstOrFail();

        $this->assertSame(
            AnswerStatus::Invalidated,
            $downstream->status,
            'La reponse en aval doit etre invalidee.'
        );

        // A3 : ... et leur bloc redevient a parcourir.
        $this->assertNotContains(
            'bloc_un',
            $session->visited_blocs ?? [],
            'bloc_un doit avoir ete libere, sinon la question ne sera jamais reposee.'
        );

        // Consequence des deux : la question invalidee est effectivement reposee.
        $this->assertNotNull($next, "L'evaluation ne doit pas se terminer apres une correction.");
        $this->assertSame(
            'edit_q2',
            $next['key'],
            'La question rendue caduque par la correction doit etre reposee.'
        );
        unset($q3);
    }
}
