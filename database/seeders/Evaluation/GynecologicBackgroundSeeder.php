<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Bloc `gynecologic_background` — antécédents gynécologiques et obstétricaux.
 *
 * Regroupe les questions sur la grossesse, l'allaitement, la ménopause
 * et les antécédents obstétricaux. Placé en position 10, après le bloc
 * `medical_background` (9) et avant `family_history` (11).
 */
class GynecologicBackgroundSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'profil_repro')->firstOrFail();

        $questions = [
            [
                'key'            => 'is_pregnant',
                'text'           => 'Êtes-vous actuellement enceinte ?',
                'type'           => 'boolean',
                'bloc_key'       => 'gynecologic_background',
                'answer_target'  => 'medical_profile',
                'order'          => 1,
                'is_entry_point' => true,
            ],
            [
                'key'           => 'breastfeeding',
                'text'          => 'Allaitez-vous en ce moment ?',
                'type'          => 'boolean',
                'bloc_key'      => 'gynecologic_background',
                'answer_target' => 'medical_profile',
                'order'         => 2,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
                ]),
            ],
            [
                'key'           => 'menopause_status',
                'text'          => 'Êtes-vous ménopausée ?',
                'type'          => 'select_one',
                'bloc_key'      => 'gynecologic_background',
                'answer_target' => 'medical_profile',
                'order'         => 3,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
                ]),
                'options'       => json_encode([
                    ['value' => 'unknown',      'label' => 'Je ne sais pas'],
                    ['value' => 'pre',          'label' => 'Non'],
                    ['value' => 'post_lt_5y',   'label' => 'Oui, depuis moins de 5 ans'],
                    ['value' => 'post_gte_5y',  'label' => 'Oui, depuis plus de 5 ans'],
                ]),
            ],
            [
                'key'           => 'number_of_pregnancies',
                'text'          => 'Combien de grossesses avez-vous eues (y compris fausses couches / IVG) ?',
                'type'          => 'number',
                'bloc_key'      => 'gynecologic_background',
                'answer_target' => 'medical_profile',
                'min'           => 0,
                'max'           => 20,
                'order'         => 4,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
                ]),
            ],
            [
                'key'           => 'number_of_abortions',
                'text'          => 'Combien d\'avortements / fausses couches avez-vous eus ?',
                'type'          => 'number',
                'bloc_key'      => 'gynecologic_background',
                'answer_target' => 'medical_profile',
                'min'           => 0,
                'max'           => 10,
                'order'         => 5,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'number_of_pregnancies', 'operator' => '>', 'value' => 0],
                ]),
            ],
            [
                'key'           => 'number_of_children',
                'text'          => 'Combien d\'enfants avez-vous eus ?',
                'type'          => 'number',
                'bloc_key'      => 'gynecologic_background',
                'answer_target' => 'medical_profile',
                'min'           => 0,
                'max'           => 15,
                'order'         => 6,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'number_of_pregnancies', 'operator' => '>', 'value' => 0],
                ]),
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
