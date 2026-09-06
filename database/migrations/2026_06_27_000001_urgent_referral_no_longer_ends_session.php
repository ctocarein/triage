<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un signe d'alerte n'interrompt plus l'evaluation.
 *
 * L'action `end_session:urgent_*` annulait la session : pas d'antecedents, pas
 * de bilan, et un score calcule sur un questionnaire tronque. Elle devient
 * `urgent:*`, qui court-circuite le questionnaire symptomatique -- il n'a plus
 * d'objet une fois l'urgence etablie -- mais reprend aux antecedents et laisse
 * l'evaluation aller a son terme.
 *
 * Le remplacement est fait sur la chaine brute plutot que sur le JSON decode :
 * la colonne est tantot un tableau encode, tantot une chaine JSON encodee une
 * seconde fois, et le remplacement textuel traverse les deux.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite('end_session:urgent_', 'urgent:');
    }

    public function down(): void
    {
        $this->rewrite('urgent:', 'end_session:urgent_');
    }

    private function rewrite(string $from, string $to): void
    {
        DB::table('dynamic_question_templates')
            ->whereNotNull('routing_rules')
            ->where('routing_rules', 'like', '%'.$from.'%')
            ->update([
                'routing_rules' => DB::raw(
                    "REPLACE(routing_rules, ".$this->quote($from).", ".$this->quote($to).")"
                ),
            ]);
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
};
