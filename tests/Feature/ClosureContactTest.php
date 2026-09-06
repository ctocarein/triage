<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le numero laisse volontairement a la cloture doit etre recupere.
 *
 * Il l'etait deja stocke comme une reponse parmi d'autres, donc introuvable
 * sans connaitre la cle de la question : personne ne pouvait reellement
 * joindre la personne. Il ne doit pas pour autant ouvrir un compte -- ni a une
 * mineure, ni a une femme qui vient de refuser de participer.
 */
class ClosureContactTest extends TestCase
{
    use RefreshDatabase;

    private function newIdentity(): Identity
    {
        return Identity::create(['kind' => 'person', 'status' => 'active']);
    }

    /** @return array{0: Identity, 1: string, 2: array<string, mixed>} */
    private function reachDeclinedBloc(string $birthDate): array
    {
        $this->seed();

        $identity = $this->newIdentity();

        $res = $this->actingAs($identity, 'sanctum')
            ->postJson('/api/evaluations/start', ['type' => 'complete']);
        $res->assertCreated();

        $sessionId = (string) $res->json('session.public_id');
        $q = $res->json('question');

        foreach (['skip', 'skip'] as $_) {
            $q = $this->advance($identity, $sessionId, $q, null, true);
        }

        $q = $this->advance($identity, $sessionId, $q, 'yes');       // explicit_consent
        $q = $this->advance($identity, $sessionId, $q, ['sein']);     // raison_visite
        $q = $this->advance($identity, $sessionId, $q, $birthDate);   // birth_date -> mineure

        $this->assertSame('consent_declined_thank_you', $q['key']);

        $q = $this->advance($identity, $sessionId, $q, null, true);
        $this->assertSame('consent_declined_whatsapp', $q['key']);

        return [$identity, $sessionId, $q];
    }

    /** @return array<string, mixed>|null */
    private function advance(
        Identity $identity,
        string $sessionId,
        array $question,
        mixed $value,
        bool $skip = false,
    ): ?array {
        $payload = ['dynamic_question_template_id' => (int) $question['id']];
        if ($skip) {
            $payload['is_skipped'] = true;
        } else {
            $payload['value'] = $value;
        }

        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", $payload);
        $res->assertOk();

        return $res->json('question');
    }

    public function test_a_minor_who_leaves_a_number_becomes_reachable(): void
    {
        [$identity, $sessionId, $q] = $this->reachDeclinedBloc('2009-01-01');

        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $q['id'],
                'value' => '+22507080910',
            ]);

        $res->assertOk()
            ->assertJsonPath('closure_contact.channel', 'whatsapp')
            ->assertJsonPath('closure_contact.value', '+22507080910');

        $session = EvaluationSession::where('public_id', $sessionId)->firstOrFail();

        $this->assertSame(
            '+22507080910',
            data_get($session->context, 'closure_contact.value'),
            'Le contact doit etre porte par la session, donc retrouvable.'
        );

        // Mais aucun compte : ni identifiant de connexion, ni PIN.
        $this->assertDatabaseMissing('credentials', ['identity_id' => $identity->id]);
    }

    public function test_skipping_the_number_records_nothing(): void
    {
        [$identity, $sessionId, $q] = $this->reachDeclinedBloc('2009-01-01');

        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $q['id'],
                'is_skipped' => true,
            ])
            ->assertOk()
            ->assertJsonMissingPath('closure_contact');

        $session = EvaluationSession::where('public_id', $sessionId)->firstOrFail();
        $this->assertNull(data_get($session->context, 'closure_contact'));
        $this->assertDatabaseMissing('credentials', ['identity_id' => $identity->id]);
    }

    public function test_the_contact_survives_until_the_end_of_the_session(): void
    {
        [$identity, $sessionId, $q] = $this->reachDeclinedBloc('2009-01-01');

        $q = $this->advance($identity, $sessionId, $q, '+22507080911');
        $this->assertSame('consent_declined_end', $q['key']);

        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $q['id'],
                'value' => 'done',
            ])
            ->assertOk()
            ->assertJsonPath('session.termination_type', 'consent_declined')
            ->assertJsonPath('closure_contact.value', '+22507080911');
    }
}
