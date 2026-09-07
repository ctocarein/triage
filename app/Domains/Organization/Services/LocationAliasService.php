<?php

namespace App\Domains\Organization\Services;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\LocationAlias;

/**
 * Builds and reads the place -> health district directory.
 *
 * The directory is harvested from completed evaluations rather than loaded up
 * front: each one yields a place named by a woman and a district chosen by a
 * health professional, which is exactly the pair nobody has a table for.
 */
class LocationAliasService
{
    /**
     * Questions whose answer names a place, in the order they should be
     * trusted. A free-text locality, once collected, is more precise than the
     * municipality and belongs first.
     */
    private const PLACE_QUESTIONS = ['locality', 'municipality'];

    /**
     * Reduces a spelling to what two spellings of the same place have in
     * common: lowercase, no accents, punctuation and spacing collapsed.
     *
     * Without this the directory fragments -- "Zimbabwé" and "Zimbabwe" would
     * be two places, and the count that ranks candidates would be split
     * between them.
     */
    public function normalize(string $label): string
    {
        $label = mb_strtolower(trim($label), 'UTF-8');

        $label = strtr($label, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
            'œ' => 'oe', 'æ' => 'ae',
        ]);

        // Apostrophes, hyphens and runs of spaces all become one separator, so
        // that "M'Batto", "M Batto" and "m-batto" meet.
        $label = preg_replace('/[^a-z0-9]+/', '-', $label) ?? '';

        return trim($label, '-');
    }

    /**
     * Records what a completed evaluation taught us.
     *
     * Harvesting at completion rather than at each answer means a correction
     * made during the interview never registers: only the pair the evaluator
     * settled on counts.
     */
    public function harvest(EvaluationSession $session): ?LocationAlias
    {
        $district = $session->identity?->personalProfile?->health_district_id;

        if (! $district) {
            return null;
        }

        $place = $this->placeAnsweredIn($session);

        if ($place === null) {
            return null;
        }

        [$rawLabel, $source] = $place;
        $key = $this->normalize($rawLabel);

        if ($key === '') {
            return null;
        }

        $alias = LocationAlias::firstOrNew([
            'normalized_key'     => $key,
            'health_district_id' => $district,
        ]);

        $alias->raw_label   = $rawLabel;
        $alias->source      = $source;
        $alias->occurrences = ($alias->occurrences ?? 0) + 1;
        $alias->save();

        return $alias;
    }

    /**
     * The district code to propose for a place, or null when the directory
     * cannot answer without guessing.
     *
     * A name attached to several districts produces nothing: two places share
     * that name, and picking the more frequent one would send the women of the
     * other to the wrong facility. A pair vouched for by a human wins over
     * frequency -- someone who knows the terrain outranks a count.
     */
    public function suggestDistrictCode(string $label): ?string
    {
        $key = $this->normalize($label);

        if ($key === '') {
            return null;
        }

        $candidates = LocationAlias::query()
            ->where('normalized_key', $key)
            ->with('healthDistrict')
            ->orderByDesc('occurrences')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $confirmed = $candidates->filter(fn (LocationAlias $a) => $a->isConfirmed());

        if ($confirmed->count() === 1) {
            return $confirmed->first()->healthDistrict?->code;
        }

        // Plusieurs districts confirmes pour un meme nom : le referentiel dit
        // lui-meme que le nom est ambigu.
        if ($confirmed->count() > 1) {
            return null;
        }

        return $candidates->count() === 1
            ? $candidates->first()->healthDistrict?->code
            : null;
    }

    /**
     * Marks a mapping as vouched for by a human.
     *
     * This is how the twenty places that carry most of the volume get fixed
     * once, by someone who knows the terrain, instead of waiting for frequency
     * to settle them.
     */
    public function confirm(string $label, string $districtCode): ?LocationAlias
    {
        $key      = $this->normalize($label);
        $district = HealthDistrict::where('code', $districtCode)->first();

        if ($key === '' || ! $district) {
            return null;
        }

        $alias = LocationAlias::firstOrNew([
            'normalized_key'     => $key,
            'health_district_id' => $district->id,
        ]);

        $alias->raw_label    = $alias->raw_label ?? trim($label);
        $alias->source       = $alias->source ?? 'manual';
        $alias->occurrences  = $alias->occurrences ?? 0;
        $alias->confirmed_at = now();
        $alias->save();

        return $alias;
    }

    /**
     * The place named during this session, with the question it came from.
     *
     * @return array{0:string,1:string}|null
     */
    public function placeAnsweredIn(EvaluationSession $session): ?array
    {
        $answers = $session->answers()
            ->where('status', AnswerStatus::Answered->value)
            ->with('question')
            ->get();

        foreach (self::PLACE_QUESTIONS as $key) {
            $answer = $answers->firstWhere(fn ($a) => $a->question?->key === $key);
            $value  = $answer?->value;

            if (is_string($value) && trim($value) !== '') {
                return [trim($value), $key];
            }
        }

        return null;
    }
}
