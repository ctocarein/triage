<?php

namespace App\Http\Controllers\Reminder;

use Illuminate\Http\Request;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\UserReminder;

class ReminderController extends Controller
{
    // Lister les reminders “actifs” pour un utilisateur
    public function index(Request $request)
    {
        $account = $request->user();

        // Récupérer les reminders dynamiques de type "reminder"
        $reminders = DynamicQuestionTemplate::where('question_type', 'reminder')->get();

        $result = [];
        foreach ($reminders as $reminder) {
            if ($this->shouldShowReminder($reminder, $account)) {
                $alreadyDone = $account->reminders()
                    ->where('dynamic_question_template_id', $reminder->id)
                    ->whereNotNull('completed_at')
                    ->whereDate('completed_at', '>=', now()->sub($reminder->period ?? '1 month'))
                    ->exists();

                if (!$alreadyDone) {
                    $result[] = $reminder;
                }
            }
        }
        return response()->json($result);
    }

    // Valider un reminder (ex: bouton “J’ai fait mon auto-palpation”)
    public function validateReminder(Request $request)
    {
        $account = $request->user();
        $reminderId = $request->input('reminder_id');

        $reminder = DynamicQuestionTemplate::findOrFail($reminderId);

        // Update medical profile (optionnel, si pertinent)
        if ($reminder->key == 'self_exam_monthly') {
            $account->medicalProfile()->update(['last_self_exam_date' => now()]);
        }

        // Log/Met à jour user_reminders
        UserReminder::updateOrCreate(
            [
                'user_id' => $account->id,
                'dynamic_question_template_id' => $reminder->id,
                'reminded_at' => now(),
            ],
            [
                'completed_at' => now(),
                'status' => 'done',
            ]
        );

        return response()->json(['success' => true, 'reminder' => $reminder->key]);
    }

    // Logique d’activation (exemple simple à compléter)
    protected function shouldShowReminder($reminder, $account)
    {
        $cond = $reminder->activation_condition ?? [];
        if (isset($cond['age_min'])) {
            $age = $account->personalProfile?->age ?? null;
            if (!$age || $age < $cond['age_min']) return false;
        }
        // Rajoute ici la logique menstruelle/jour fixe selon medicalProfile si reminder = self_exam_monthly
        return true;
    }
}