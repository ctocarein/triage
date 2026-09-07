<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Some municipalities exist but must not be offered as an answer.
 *
 * Abidjan is the case that forced this: the reference list holds Abidjan
 * alongside its own municipalities -- Abobo, Adjame, Yopougon, Port-Bouet. A
 * woman living in Abobo could answer either one, and the two answers never
 * aggregate. The city breakdown then splits between a parent and its children,
 * which is exactly how the previous provider ended up with an unreadable map.
 *
 * The row stays: it carries history (answers already given), and it is the
 * natural parent should an agglomeration level ever be needed. Only its
 * availability as a choice is withdrawn -- which is why this is a flag and not
 * a delete.
 */
return new class extends Migration
{
    /** Municipalities kept for reference but withdrawn from the answer list. */
    private const NOT_SELECTABLE = ['Abidjan'];

    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->boolean('is_selectable')->default(true)->after('name');
        });

        DB::table('municipalities')
            ->whereIn('name', self::NOT_SELECTABLE)
            ->update(['is_selectable' => false]);

        $this->refreshQuestionOptions();
    }

    /**
     * The question carries its own copy of the option list, frozen at seed
     * time. Filtering the reference table is not enough: an environment whose
     * catalogue is already seeded would keep offering Abidjan until someone
     * re-seeded it. The denormalised copy has to follow its source.
     */
    private function refreshQuestionOptions(): void
    {
        $question = DB::table('dynamic_question_templates')
            ->where('key', 'municipality')
            ->first(['id', 'options']);

        if (! $question) {
            return;
        }

        $options = json_decode($question->options ?? '[]', true);

        if (! is_array($options)) {
            return;
        }

        $kept = array_values(array_filter(
            $options,
            fn ($option) => ! in_array($option['value'] ?? null, self::NOT_SELECTABLE, true),
        ));

        if (count($kept) === count($options)) {
            return;
        }

        DB::table('dynamic_question_templates')
            ->where('id', $question->id)
            ->update(['options' => json_encode($kept, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropColumn('is_selectable');
        });
    }
};
