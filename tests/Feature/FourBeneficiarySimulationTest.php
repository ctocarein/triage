<?php

namespace Tests\Feature;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simulation complète — 4 bénéficiaires (nouveau parcours optimisé).
 *
 * Ordre du parcours :
 *   greeting → patient_context → emergency_screen → dispatch_intention
 *   → symptômes → medical_background → gynecologic_background
 *   → family_history → lifestyle → screening_history → patient_demographics
 *
 *   B1 — 17 ans                         → rejet mineur via birth_date (patient_context)
 *   B2 — 20 ans, dépistage              → sein allégé complet, col allégé ignoré (âge < 21),
 *                                          puis antécédents → fin naturelle
 *   B3 — 45 ans, boule au sein          → symptom_breast + symptom_cervix → antécédents → fin
 *   B4 — 56 ans, saignement post-méno   → symptom_cervix → sortie urgente pelvic_mass
 */
class FourBeneficiarySimulationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // B1 — 17 ans : rejet mineur
    // -----------------------------------------------------------------------

    public function test_b1_minor_age_17_is_blocked_via_birth_date_to_consent_declined(): void
    {
        $this->seed();

        $identity = $this->newIdentity();
        $q        = $this->beginSession($identity, $sessionId);

        // Greeting
        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('greeting_confidential', $q, 'B1');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('explicit_consent', $q, 'B1');

        $q = $this->answer($identity, $sessionId, $q, 'yes');
        $this->assertKey('raison_visite', $q, 'B1');

        $q = $this->answer($identity, $sessionId, $q, ['sein']);
        $this->assertKey('birth_date', $q, 'B1');

        // 2009-01-01 → âge 17 → routing age_lt 18 → question:consent_declined_thank_you
        $q = $this->answer($identity, $sessionId, $q, '2009-01-01');
        $this->assertKey(
            'consent_declined_thank_you', $q,
            'B1 : un mineur de 17 ans doit être redirigé vers consent_declined_thank_you après birth_date.'
        );

        // Parcours consent_declined
        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('consent_declined_whatsapp', $q, 'B1');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('consent_declined_end', $q, 'B1');

        // Terminaison
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $q['id'],
                'value' => 'done',
            ]);

        $res->assertOk();
        $this->assertNull($res->json('question'), 'B1 : la session doit être terminée.');
        $this->assertSame(
            'consent_declined',
            $res->json('session.termination_type'),
            'B1 : termination_type doit être consent_declined.'
        );
    }

    // -----------------------------------------------------------------------
    // B2 — 20 ans, dépistage : sein allégé OK, col allégé ignoré (âge < 21)
    // -----------------------------------------------------------------------

    public function test_b2_depistage_age_20_completes_breast_light_and_skips_cervical_light(): void
    {
        $this->seed();

        $identity = $this->newIdentity();
        $q        = $this->beginSession($identity, $sessionId);

        // Greeting → patient_context
        $q = $this->doGreeting($identity, $sessionId, $q, 'yes', ['depistage']);
        $this->assertKey('birth_date', $q, 'B2');

        // patient_context : 20 ans → commune
        $q = $this->answer($identity, $sessionId, $q, '2006-01-01');
        $this->assertKey('commune', $q, 'B2');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('phone', $q, 'B2');

        $q = $this->answer($identity, $sessionId, $q, $this->phoneFor($identity));
        $this->assertKey('emergency_breast_bleeding', $q, 'B2');

        // emergency_screen : tout négatif → dispatch_intention
        $q = $this->doEmergencyScreen($identity, $sessionId, $q);
        $this->assertKey('dispatch_intention', $q, 'B2');

        // dispatch → symptom_breast_light (dépistage)
        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey(
            'breast_light_autopalpation', $q,
            'B2 : dépistage doit commencer dans symptom_breast_light.'
        );

        // Parcours sein allégé — signaux tous négatifs
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey('breast_light_last_mammo', $q, 'B2');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('breast_light_mass', $q, 'B2');

        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey('breast_light_nipple_discharge', $q, 'B2');

        $q = $this->answer($identity, $sessionId, $q, false);
        // nipple_bleeding est masquée (visible_if: discharge=true) → directement skin_orange
        $this->assertKey(
            'breast_light_skin_orange', $q,
            'B2 : breast_light_nipple_bleeding ne doit pas apparaître si discharge=false.'
        );

        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey('breast_light_pain', $q, 'B2');

        // breast_light_pain → routing if:true → bloc:symptom_cervical_light
        // Mais âge 20 < 21 → symptom_cervical_light invisible → skippé
        // → flux naturel continue vers medical_background (order 9)
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey(
            'other_diseases', $q,
            'B2 : symptom_cervical_light doit être ignoré (âge 20 < 21), antécédents commencent.'
        );

        // Traverser tous les antécédents jusqu'à la fin de session
        $this->doAntecedents($identity, $sessionId, $q);
    }

    // -----------------------------------------------------------------------
    // B3 — 45 ans, boule au sein : parcours sein complet puis cervix
    // -----------------------------------------------------------------------

    public function test_b3_sein_age_45_with_breast_mass_follows_complete_flow(): void
    {
        $this->seed();

        $identity = $this->newIdentity();
        $q        = $this->beginSession($identity, $sessionId);

        $q = $this->doGreeting($identity, $sessionId, $q, 'yes', ['sein']);
        $this->assertKey('birth_date', $q, 'B3');

        $q = $this->doPatientContext($identity, $sessionId, $q, '1981-01-01'); // 45 ans
        $q = $this->doEmergencyScreen($identity, $sessionId, $q);
        $this->assertKey('dispatch_intention', $q, 'B3');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey(
            'breast_palpation', $q,
            'B3 : parcours sein doit démarrer par breast_palpation.'
        );

        // Autopalpation négative → masque breast_palpation_experience
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey('breast_pain', $q, 'B3');

        // Pas de douleur → masque toutes les questions breast_pain_*
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey(
            'breast_mass', $q,
            'B3 : sans douleur, breast_mass doit être la prochaine question visible.'
        );

        // Boule détectée
        $q = $this->answer($identity, $sessionId, $q, true);
        $this->assertKey('breast_mass_change', $q, 'B3');

        // Changement rapide → routing: question:breast_mass_duration
        $q = $this->answer($identity, $sessionId, $q, true);
        $this->assertKey('breast_mass_duration', $q, 'B3');

        $q = $this->answer($identity, $sessionId, $q, '1_3m');

        // Avancer jusqu'à axillary_ganglion
        $q = $this->skipUntil($identity, $sessionId, $q, 'axillary_ganglion');

        // axillary_ganglion → routing if:true → bloc:symptom_cervix
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey(
            'vaginal_bleeding_abnormal', $q,
            'B3 : axillary_ganglion doit enchaîner sur symptom_cervix.'
        );

        // Cervix — tout skip jusqu'à general_weightloss (dernière question)
        $q = $this->skipUntil($identity, $sessionId, $q, 'general_weightloss');

        // Après general_weightloss → antécédents (medical_background)
        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertNotNull($q, 'B3 : les antécédents doivent démarrer après les symptômes.');

        // Traverser tous les antécédents jusqu'à la fin de session
        $this->doAntecedents($identity, $sessionId, $q);
    }

    // -----------------------------------------------------------------------
    // B4 — 56 ans, saignement post-ménopause : sortie urgente pelvic_mass
    // -----------------------------------------------------------------------

    public function test_b4_cervical_age_56_postmenopause_bleeding_triggers_urgent_pelvic_mass(): void
    {
        $this->seed();

        $identity = $this->newIdentity();
        $q        = $this->beginSession($identity, $sessionId);

        $q = $this->doGreeting($identity, $sessionId, $q, 'yes', ['cervical']);
        $this->assertKey('birth_date', $q, 'B4');

        $q = $this->doPatientContext($identity, $sessionId, $q, '1970-01-01'); // 56 ans
        $q = $this->doEmergencyScreen($identity, $sessionId, $q);
        $this->assertKey('dispatch_intention', $q, 'B4');

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey(
            'vaginal_bleeding_abnormal', $q,
            'B4 : parcours cervical doit démarrer par vaginal_bleeding_abnormal.'
        );

        // Saignement post-ménopause
        $q = $this->answer($identity, $sessionId, $q, true);
        $this->assertKey('bleeding_when', $q, 'B4');

        $q = $this->answer($identity, $sessionId, $q, ['after_menopause']);
        $this->assertKey('bleeding_amount', $q, 'B4');

        $q = $this->answer($identity, $sessionId, $q, true);
        $this->assertKey('bleeding_duration', $q, 'B4');

        $q = $this->answer($identity, $sessionId, $q, 'more_3m');
        $this->assertKey('bleeding_pain', $q, 'B4');

        $q = $this->answer($identity, $sessionId, $q, true);

        // Avancer jusqu'à pelvic_mass
        $q = $this->skipUntil($identity, $sessionId, $q, 'pelvic_mass');

        // Masse pelvienne détectée → routing: question:pelvic_mass_since
        $q = $this->answer($identity, $sessionId, $q, true);
        $this->assertKey(
            'pelvic_mass_since', $q,
            'B4 : pelvic_mass=true doit déclencher pelvic_mass_since.'
        );

        // pelvic_mass_since → routing if:true → end_session:urgent_pelvic_mass
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $q['id'],
                'value' => 'less_1w',
            ]);

        $res->assertOk();
        $this->assertNull(
            $res->json('question'),
            'B4 : la session doit se terminer de façon urgente après pelvic_mass_since.'
        );
        $this->assertSame(
            'urgent_referral',
            $res->json('session.termination_type'),
            'B4 : termination_type doit être urgent_referral.'
        );
        $this->assertSame(
            'urgent_pelvic_mass',
            $res->json('session.termination_reason'),
            'B4 : termination_reason doit être urgent_pelvic_mass.'
        );
    }

    // -----------------------------------------------------------------------
    // Helpers communs
    // -----------------------------------------------------------------------

    /**
     * Démarre une session et retourne la première question (greeting_welcome).
     *
     * @param-out string $sessionId  UUID public de la session.
     */
    private function beginSession(Identity $identity, string|null &$sessionId): array
    {
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson('/api/evaluations/start', ['type' => 'complete']);

        $res->assertCreated();
        $res->assertJsonPath('question.key', 'greeting_welcome');

        $sessionId = (string) $res->json('session.public_id');

        return $res->json('question');
    }

    /**
     * Parcourt le bloc greeting jusqu'à la première question de patient_context (birth_date).
     *
     * @param  string[] $reasons  Valeurs de raison_visite (select_multiple).
     */
    private function doGreeting(
        Identity $identity,
        string $sessionId,
        array $greetingWelcome,
        string $consent,
        array $reasons,
    ): array {
        $q = $this->skip($identity, $sessionId, $greetingWelcome);
        $this->assertKey('greeting_confidential', $q);

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('explicit_consent', $q);

        $q = $this->answer($identity, $sessionId, $q, $consent);
        $this->assertKey('raison_visite', $q);

        $q = $this->answer($identity, $sessionId, $q, $reasons);
        $this->assertKey('birth_date', $q);

        return $q;
    }

    /**
     * Répond à birth_date, skipe commune, puis renseigne le téléphone.
     * Retourne la première question d'emergency_screen.
     */
    private function doPatientContext(
        Identity $identity,
        string $sessionId,
        array $birthDateQuestion,
        string $birthDate,
    ): array {
        $q = $this->answer($identity, $sessionId, $birthDateQuestion, $birthDate);
        $this->assertKey('commune', $q);

        $q = $this->skip($identity, $sessionId, $q);
        $this->assertKey('phone', $q);

        // Le numéro est requis et sert de clé de dédoublonnage : un numéro par
        // bénéficiaire, sinon la session basculerait sur l'identité précédente.
        $q = $this->answer($identity, $sessionId, $q, $this->phoneFor($identity));
        $this->assertKey('emergency_breast_bleeding', $q);

        return $q;
    }

    /** Numéro déterministe et distinct pour chaque bénéficiaire simulé. */
    private function phoneFor(Identity $identity): string
    {
        return '07'.substr(str_replace('-', '', (string) $identity->id), 0, 8);
    }

    /**
     * Répond false aux 3 questions d'emergency_screen.
     * Retourne dispatch_intention.
     */
    private function doEmergencyScreen(
        Identity $identity,
        string $sessionId,
        array $firstEmergencyQuestion,
    ): array {
        $this->assertKey('emergency_breast_bleeding', $firstEmergencyQuestion);

        $q = $this->answer($identity, $sessionId, $firstEmergencyQuestion, false);
        $this->assertKey('emergency_skin_changes', $q);

        $q = $this->answer($identity, $sessionId, $q, false);
        $this->assertKey('emergency_vaginal_bleeding', $q);

        $q = $this->answer($identity, $sessionId, $q, false);
        return $q;
    }

    /**
     * Skipe toutes les questions des blocs antécédents jusqu'à la fin de session.
     *
     * Blocs couverts (dans l'ordre) :
     *   medical_background → gynecologic_background → family_history
     *   → lifestyle → screening_history → patient_demographics
     *
     * Vérifie que la session se termine avec status='completed'.
     */
    private function doAntecedents(
        Identity $identity,
        string $sessionId,
        array $firstAntecedentQuestion,
    ): void {
        $q       = $firstAntecedentQuestion;
        $maxSteps = 100;
        $steps    = 0;
        $res      = null;

        // Skip toutes les questions restantes (ordre adaptatif ou fixe) jusqu'à la fin de session.
        do {
            $this->assertLessThan(
                $maxSteps,
                $steps,
                "doAntecedents : session non terminée après {$maxSteps} étapes. Dernière clé : '{$q['key']}'."
            );

            $res = $this->actingAs($identity, 'sanctum')
                ->postJson("/api/evaluations/{$sessionId}/advance", [
                    'dynamic_question_template_id' => (int) $q['id'],
                    'is_skipped' => true,
                ]);

            $res->assertOk();
            $q = $res->json('question');
            $steps++;
        } while ($q !== null);

        $this->assertSame('completed', $res->json('session.status'), 'Le statut final doit être completed.');
    }

    /**
     * Avance par des skips successifs jusqu'à atteindre la clé cible.
     */
    private function skipUntil(
        Identity $identity,
        string $sessionId,
        array $currentQuestion,
        string $targetKey,
        int $maxSteps = 80,
    ): array {
        $steps = 0;

        while ($currentQuestion['key'] !== $targetKey) {
            $this->assertLessThan(
                $maxSteps,
                $steps,
                "skipUntil : '{$targetKey}' non atteint après {$maxSteps} étapes. "
                . "Dernière clé : '{$currentQuestion['key']}'."
            );

            $currentQuestion = $this->skip($identity, $sessionId, $currentQuestion);
            $this->assertNotNull(
                $currentQuestion,
                "skipUntil : session terminée avant d'atteindre '{$targetKey}'."
            );
            $steps++;
        }

        return $currentQuestion;
    }

    private function assertKey(string $expected, ?array $question, string $label = ''): void
    {
        $prefix = $label ? "[{$label}] " : '';
        $this->assertNotNull(
            $question,
            "{$prefix}Question attendue '{$expected}' mais la session est terminée (question=null)."
        );
        $this->assertSame($expected, $question['key'], "{$prefix}Clé attendue : {$expected}.");
    }

    private function skip(Identity $identity, string $sessionId, array $question): ?array
    {
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $question['id'],
                'is_skipped' => true,
            ]);

        $res->assertOk();
        return $res->json('question');
    }

    private function answer(Identity $identity, string $sessionId, array $question, mixed $value): ?array
    {
        $res = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => (int) $question['id'],
                'value' => $value,
            ]);

        $res->assertOk();
        return $res->json('question');
    }

    private function newIdentity(): Identity
    {
        return Identity::create(['kind' => 'person', 'status' => 'active']);
    }
}
