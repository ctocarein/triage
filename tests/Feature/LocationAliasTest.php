<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\LocationAlias;
use App\Domains\Organization\Services\LocationAliasService;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Database\Seeders\HealthMapSeeder;
use Database\Seeders\MunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'annuaire lieu -> district sanitaire n'existe nulle part et le construire
 * d'avance prendrait des mois. Mais chaque evaluation assistee produit une
 * paire vraie : le lieu que la femme nomme, et le district qu'une
 * professionnelle retient. L'annuaire est donc un sous-produit du travail.
 */
class LocationAliasTest extends TestCase
{
    use RefreshDatabase;

    private LocationAliasService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            MunicipalitySeeder::class,
            HealthMapSeeder::class,
            QuestionGroupSeeder::class,
            ThematicBlocSeeder::class,
            DynamicQuestionTemplateSeeder::class,
        ]);

        $this->service = app(LocationAliasService::class);
    }

    private function district(string $code): HealthDistrict
    {
        return HealthDistrict::where('code', $code)->firstOrFail();
    }

    private function completedSession(string $municipality, string $districtCode): EvaluationSession
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        $identity->personalProfile()->create([
            'municipality'       => $municipality,
            'health_district_id' => $this->district($districtCode)->id,
        ]);

        $session = EvaluationSession::create([
            'identity_id'  => $identity->id,
            'status'       => 'completed',
            'type'         => 'complete',
            'score'        => 0,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);

        $session->answers()->create([
            'dynamic_question_template_id' => DynamicQuestionTemplate::where('key', 'municipality')->firstOrFail()->id,
            'value'                        => $municipality,
            'status'                       => AnswerStatus::Answered->value,
        ]);

        return $session->fresh();
    }

    // -----------------------------------------------------------------------
    // Normalisation
    // -----------------------------------------------------------------------

    public function test_spellings_of_the_same_place_share_one_key(): void
    {
        // Sans cela l'annuaire se fragmente, et le compte qui departage les
        // candidats se repartit entre les orthographes.
        $expected = $this->service->normalize('Zimbabwé');

        foreach (['Zimbabwe', 'ZIMBABWE', '  zimbabwé  ', 'Zimbabwé'] as $variant) {
            $this->assertSame($expected, $this->service->normalize($variant), $variant);
        }
    }

    public function test_apostrophes_hyphens_and_spaces_collapse(): void
    {
        $expected = $this->service->normalize("M'Batto");

        $this->assertSame($expected, $this->service->normalize('M Batto'));
        $this->assertSame($expected, $this->service->normalize('m-batto'));
    }

    public function test_a_label_with_nothing_usable_yields_an_empty_key(): void
    {
        $this->assertSame('', $this->service->normalize('   ---   '));
    }

    // -----------------------------------------------------------------------
    // Recolte
    // -----------------------------------------------------------------------

    public function test_a_completed_evaluation_teaches_the_directory(): void
    {
        $this->service->harvest($this->completedSession('San-Pédro', 'DS-SAN-PEDRO'));

        $alias = LocationAlias::firstOrFail();

        $this->assertSame($this->service->normalize('San-Pédro'), $alias->normalized_key);
        $this->assertSame('San-Pédro', $alias->raw_label);
        $this->assertSame('municipality', $alias->source);
        $this->assertSame(1, $alias->occurrences);
        $this->assertSame('DS-SAN-PEDRO', $alias->healthDistrict->code);
    }

    public function test_repeated_evaluations_strengthen_the_same_pair(): void
    {
        $this->service->harvest($this->completedSession('San-Pédro', 'DS-SAN-PEDRO'));
        $this->service->harvest($this->completedSession('SAN-PEDRO', 'DS-SAN-PEDRO'));

        $this->assertSame(1, LocationAlias::count());
        $this->assertSame(2, LocationAlias::firstOrFail()->occurrences);
    }

    public function test_nothing_is_learnt_without_a_district(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
        $identity->personalProfile()->create(['municipality' => 'Tabou']);

        $session = EvaluationSession::create([
            'identity_id'  => $identity->id,
            'status'       => 'completed',
            'type'         => 'complete',
            'score'        => 0,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);

        $this->assertNull($this->service->harvest($session));
        $this->assertSame(0, LocationAlias::count());
    }

    // -----------------------------------------------------------------------
    // Lecture
    // -----------------------------------------------------------------------

    public function test_a_learnt_place_is_suggested(): void
    {
        $this->service->harvest($this->completedSession('San-Pédro', 'DS-SAN-PEDRO'));

        // Ecrit autrement : l'annuaire doit quand meme repondre.
        $this->assertSame('DS-SAN-PEDRO', $this->service->suggestDistrictCode('san pedro'));
    }

    public function test_an_unknown_place_suggests_nothing(): void
    {
        $this->assertNull($this->service->suggestDistrictCode('Lieu jamais vu'));
    }

    public function test_an_ambiguous_place_suggests_nothing(): void
    {
        // Deux districts pour un meme nom : deux lieux le portent. Choisir le
        // plus frequent enverrait les femmes de l'autre vers la mauvaise
        // structure -- mieux vaut ne rien proposer.
        $this->service->harvest($this->completedSession('Kouassikro', 'DS-SAN-PEDRO'));
        $this->service->harvest($this->completedSession('Kouassikro', 'DS-SAN-PEDRO'));
        $this->service->harvest($this->completedSession('Kouassikro', 'DS-TABOU'));

        $this->assertSame(2, LocationAlias::where('normalized_key', 'kouassikro')->count());
        $this->assertNull($this->service->suggestDistrictCode('Kouassikro'));
    }

    public function test_a_human_confirmation_settles_an_ambiguity(): void
    {
        $this->service->harvest($this->completedSession('Kouassikro', 'DS-SAN-PEDRO'));
        $this->service->harvest($this->completedSession('Kouassikro', 'DS-TABOU'));

        // Quelqu'un qui connait le terrain tranche : sa parole passe devant le
        // compte.
        $this->service->confirm('Kouassikro', 'DS-TABOU');

        $this->assertSame('DS-TABOU', $this->service->suggestDistrictCode('Kouassikro'));
    }

    public function test_confirming_an_unseen_place_creates_the_mapping(): void
    {
        // Les vingt lieux qui portent l'essentiel du volume peuvent etre
        // rattaches a la main, une fois, sans attendre la frequence.
        $alias = $this->service->confirm('Zimbabwé', 'DS-SAN-PEDRO');

        $this->assertNotNull($alias);
        $this->assertTrue($alias->isConfirmed());
        $this->assertSame(0, $alias->occurrences);
        $this->assertSame('DS-SAN-PEDRO', $this->service->suggestDistrictCode('zimbabwe'));
    }

    public function test_confirming_against_an_unknown_district_does_nothing(): void
    {
        $this->assertNull($this->service->confirm('Zimbabwé', 'DS-NEXISTEPAS'));
        $this->assertSame(0, LocationAlias::count());
    }
}
