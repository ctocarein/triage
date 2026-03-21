<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Bloc `emergency_screen` — détection rapide des signaux d'alarme immédiats.
 *
 * Ce bloc pose 3 questions booléennes sur les signes qui nécessitent
 * une consultation urgente. Une réponse positive déclenche une action
 * `end_session:urgent_*` et interrompt immédiatement le parcours.
 *
 * Les questions n'ont pas de `answer_target` de profil : elles servent
 * uniquement au routage d'urgence et ne sont pas persistées dans le profil.
 *
 * Placé en position 3 (après patient_context, avant dispatch_intention)
 * pour filtrer les urgences avant d'entrer dans le parcours symptomatique.
 */
class EmergencyScreenSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $group = QuestionGroup::where('key', 'profile_personal')->firstOrFail();

        $questions = [
            [
                'key'            => 'emergency_breast_bleeding',
                'text'           => 'Avez-vous un écoulement de sang par le mamelon (en dehors de l\'allaitement) ?',
                'type'           => 'boolean',
                'bloc_key'       => 'emergency_screen',
                'answer_target'  => null,
                'order'          => 1,
                'is_entry_point' => true,
                'routing_rules'  => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'end_session:urgent_breast_bleeding'],
                ],
            ],
            [
                'key'           => 'emergency_skin_changes',
                'text'          => 'Avez-vous remarqué une bosse qui grossit très vite ou une peau d\'orange sur un sein ?',
                'type'          => 'boolean',
                'bloc_key'      => 'emergency_screen',
                'answer_target' => null,
                'order'         => 2,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'end_session:urgent_breast_mass'],
                ],
            ],
            [
                'key'           => 'emergency_vaginal_bleeding',
                'text'          => 'Avez-vous des saignements vaginaux importants et inattendus (en dehors de vos règles habituelles) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'emergency_screen',
                'answer_target' => null,
                'order'         => 3,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'end_session:urgent_vaginal_bleeding'],
                ],
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $group->id])
            );
        }
    }
}
