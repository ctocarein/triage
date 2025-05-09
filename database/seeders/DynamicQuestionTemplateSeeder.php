<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Evaluation\QuestionGroup;
use Illuminate\Support\Carbon;

class DynamicQuestionTemplateSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // Récupérer les IDs des groupes par clé
        $groupMap = QuestionGroup::pluck('id', 'key');

        DB::table('dynamic_question_templates')->insert([
            // 🌸 Symptômes du sein
            [
                'key' => 'breast_lump_present',
                'text' => 'Avez-vous senti une masse dans votre sein ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'question_group_id' => $groupMap['symptomes_sein'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => null,
                'visible_if' => null,
                'is_required' => true,
                'is_entry_point' => true,
                'risk_weight' => 5,
                'order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'breast_pain',
                'text' => 'Ressentez-vous des douleurs dans un ou deux seins ?',
                'type' => 'select_one',
                'answer_target' => 'evaluation',
                'question_group_id' => $groupMap['symptomes_sein'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => json_encode(['Non', 'Douleur légère', 'Douleur forte']),
                'visible_if' => null,
                'is_required' => false,
                'is_entry_point' => false,
                'risk_weight' => 3,
                'order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 🤰 Maternité
            [
                'key' => 'is_pregnant',
                'text' => 'Êtes-vous actuellement enceinte ?',
                'type' => 'boolean',
                'answer_target' => 'medical_profile',
                'question_group_id' => $groupMap['maternite'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => null,
                'visible_if' => null,
                'is_required' => true,
                'is_entry_point' => true,
                'risk_weight' => 0,
                'order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'menopause_status',
                'text' => 'Êtes-vous ménopausée ?',
                'type' => 'boolean',
                'answer_target' => 'medical_profile',
                'question_group_id' => $groupMap['maternite'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => null,
                'visible_if' => null,
                'is_required' => true,
                'is_entry_point' => false,
                'risk_weight' => 0,
                'order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 🩸 Symptômes du col
            [
                'key' => 'abnormal_bleeding',
                'text' => 'Avez-vous des saignements vaginaux anormaux ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'question_group_id' => $groupMap['symptomes_col'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => null,
                'visible_if' => null,
                'is_required' => true,
                'is_entry_point' => true,
                'risk_weight' => 6,
                'order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'pelvic_pain',
                'text' => 'Ressentez-vous des douleurs pelviennes fréquentes ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'question_group_id' => $groupMap['symptomes_col'] ?? null,
                'media_type' => null,
                'media_url' => null,
                'options' => null,
                'visible_if' => null,
                'is_required' => false,
                'is_entry_point' => false,
                'risk_weight' => 4,
                'order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
