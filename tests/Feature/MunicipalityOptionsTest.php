<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Organization\Models\Municipality;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Database\Seeders\MunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La question de residence ne propose que des communes selectionnables.
 *
 * Abidjan figure dans le referentiel a cote de ses propres communes -- Abobo,
 * Adjame, Yopougon. La proposer ferait deux reponses pour le meme lieu, qui ne
 * s'agregeraient jamais : une femme d'Abobo repondrait tantot l'une, tantot
 * l'autre, et la repartition geographique se scinderait entre un parent et ses
 * enfants.
 */
class MunicipalityOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            MunicipalitySeeder::class,
            QuestionGroupSeeder::class,
            ThematicBlocSeeder::class,
            DynamicQuestionTemplateSeeder::class,
        ]);
    }

    /** @return string[] */
    private function optionValues(): array
    {
        $question = DynamicQuestionTemplate::where('key', 'municipality')->firstOrFail();

        return array_column($question->options ?? [], 'value');
    }

    public function test_abidjan_is_not_offered_as_an_answer(): void
    {
        $this->assertNotContains('Abidjan', $this->optionValues());
    }

    public function test_its_own_municipalities_are_offered(): void
    {
        $values = $this->optionValues();

        foreach (['Abobo', 'Adjamé', 'Yopougon'] as $name) {
            $this->assertContains($name, $values, "{$name} devrait rester proposable.");
        }
    }

    public function test_a_standalone_municipality_is_offered(): void
    {
        // San-Pedro n'est la commune d'aucune agglomeration du referentiel :
        // rien ne justifierait de l'ecarter.
        $this->assertContains('San-Pédro', $this->optionValues());
    }

    public function test_abidjan_stays_in_the_reference_list(): void
    {
        // Ecartee des choix, pas supprimee : la ligne porte les reponses deja
        // donnees et resterait le parent naturel d'un niveau agglomeration.
        $abidjan = Municipality::where('name', 'Abidjan')->first();

        $this->assertNotNull($abidjan);
        $this->assertFalse($abidjan->is_selectable);
    }
}
