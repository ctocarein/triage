<?php

namespace App\Domains\Notification\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Notification\Models\UserReminder;

class ReminderController extends Controller
{
    // Lister les reminders â€œactifsâ€ pour un utilisateur
    public function index(Request $request)
    {
        $identity = $request->user();

        // RÃ©cupÃ©rer les reminders dynamiques de type "reminder"
        $reminders = DynamicQuestionTemplate::where('question_type', 'reminder')->get();

        $result = [];
        foreach ($reminders as $reminder) {
            if ($this->shouldShowReminder($reminder, $identity)) {
                $alreadyDone = $identity->reminders()
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

    // Valider un reminder (ex: bouton â€œJâ€™ai fait mon auto-palpationâ€)
    public function validateReminder(Request $request)
    {
        $identity = $request->user();
        $reminderId = $request->input('reminder_id');

        $reminder = DynamicQuestionTemplate::findOrFail($reminderId);

        // Update medical profile (optionnel, si pertinent)
        if ($reminder->key == 'self_exam_monthly') {
            $identity->medicalProfile()->update(['last_self_exam_date' => now()]);
        }

        // Log/Met Ã  jour user_reminders
        UserReminder::updateOrCreate(
            [
                'identity_id' => $identity->id,
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

    // Logique dâ€™activation (exemple simple Ã  complÃ©ter)
    protected function shouldShowReminder($reminder, $identity)
    {
        $cond = $reminder->activation_condition ?? [];
        if (isset($cond['age_min'])) {
            $age = $identity->personalProfile?->age ?? null;
            if (!$age || $age < $cond['age_min']) return false;
        }
        // Rajoute ici la logique menstruelle/jour fixe selon medicalProfile si reminder = self_exam_monthly
        return true;
    }
}



