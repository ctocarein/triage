<?php

namespace Database\Seeders;

use App\Domains\Organization\Models\Country;
use Illuminate\Database\Seeder;

class CountryCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => 'Algeria',
                'iso2' => 'DZ',
                'iso3' => 'DZA',
                'cities' => ['Algiers', 'Oran', 'Constantine', 'Annaba'],
            ],
            [
                'name' => 'Benin',
                'iso2' => 'BJ',
                'iso3' => 'BEN',
                'cities' => ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey'],
            ],
            [
                'name' => 'Burkina Faso',
                'iso2' => 'BF',
                'iso3' => 'BFA',
                'cities' => ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Ouahigouya'],
            ],
            [
                'name' => 'Burundi',
                'iso2' => 'BI',
                'iso3' => 'BDI',
                'cities' => ['Bujumbura', 'Gitega', 'Ngozi', 'Rumonge'],
            ],
            [
                'name' => 'Cameroon',
                'iso2' => 'CM',
                'iso3' => 'CMR',
                'cities' => ['Yaounde', 'Douala', 'Garoua', 'Bafoussam'],
            ],
            [
                'name' => 'Central African Republic',
                'iso2' => 'CF',
                'iso3' => 'CAF',
                'cities' => ['Bangui', 'Bimbo', 'Berberati', 'Kaga-Bandoro'],
            ],
            [
                'name' => 'Chad',
                'iso2' => 'TD',
                'iso3' => 'TCD',
                'cities' => ["N'Djamena", 'Moundou', 'Sarh', 'Abeche'],
            ],
            [
                'name' => 'Comoros',
                'iso2' => 'KM',
                'iso3' => 'COM',
                'cities' => ['Moroni', 'Mutsamudu', 'Fomboni', 'Domoni'],
            ],
            [
                'name' => 'Democratic Republic of the Congo',
                'iso2' => 'CD',
                'iso3' => 'COD',
                'cities' => ['Kinshasa', 'Lubumbashi', 'Goma', 'Kisangani'],
            ],
            [
                'name' => 'Republic of the Congo',
                'iso2' => 'CG',
                'iso3' => 'COG',
                'cities' => ['Brazzaville', 'Pointe-Noire', 'Dolisie', 'Owando'],
            ],
            [
                'name' => 'Djibouti',
                'iso2' => 'DJ',
                'iso3' => 'DJI',
                'cities' => ['Djibouti City', 'Ali Sabieh', 'Dikhil', 'Tadjourah'],
            ],
            [
                'name' => 'Equatorial Guinea',
                'iso2' => 'GQ',
                'iso3' => 'GNQ',
                'cities' => ['Malabo', 'Bata', 'Ebebiyin', 'Aconibe'],
            ],
            [
                'name' => 'Gabon',
                'iso2' => 'GA',
                'iso3' => 'GAB',
                'cities' => ['Libreville', 'Port-Gentil', 'Franceville', 'Oyem'],
            ],
            [
                'name' => 'Guinea',
                'iso2' => 'GN',
                'iso3' => 'GIN',
                'cities' => ['Conakry', 'Kankan', 'Labe', 'Kindia'],
            ],
            [
                'name' => "Cote d'Ivoire",
                'iso2' => 'CI',
                'iso3' => 'CIV',
                'cities' => ['Abidjan', 'Yamoussoukro', 'Bouake', 'Daloa'],
            ],
            [
                'name' => 'Madagascar',
                'iso2' => 'MG',
                'iso3' => 'MDG',
                'cities' => ['Antananarivo', 'Toamasina', 'Antsirabe', 'Mahajanga'],
            ],
            [
                'name' => 'Mali',
                'iso2' => 'ML',
                'iso3' => 'MLI',
                'cities' => ['Bamako', 'Sikasso', 'Segou', 'Mopti'],
            ],
            [
                'name' => 'Mauritania',
                'iso2' => 'MR',
                'iso3' => 'MRT',
                'cities' => ['Nouakchott', 'Nouadhibou', 'Rosso', 'Kiffa'],
            ],
            [
                'name' => 'Mauritius',
                'iso2' => 'MU',
                'iso3' => 'MUS',
                'cities' => ['Port Louis', 'Curepipe', 'Vacoas', 'Quatre Bornes'],
            ],
            [
                'name' => 'Morocco',
                'iso2' => 'MA',
                'iso3' => 'MAR',
                'cities' => ['Rabat', 'Casablanca', 'Marrakech', 'Fes'],
            ],
            [
                'name' => 'Niger',
                'iso2' => 'NE',
                'iso3' => 'NER',
                'cities' => ['Niamey', 'Zinder', 'Maradi', 'Agadez'],
            ],
            [
                'name' => 'Rwanda',
                'iso2' => 'RW',
                'iso3' => 'RWA',
                'cities' => ['Kigali', 'Butare', 'Gisenyi', 'Ruhengeri'],
            ],
            [
                'name' => 'Senegal',
                'iso2' => 'SN',
                'iso3' => 'SEN',
                'cities' => ['Dakar', 'Thies', 'Saint-Louis', 'Ziguinchor'],
            ],
            [
                'name' => 'Seychelles',
                'iso2' => 'SC',
                'iso3' => 'SYC',
                'cities' => ['Victoria', 'Anse Boileau', 'Beau Vallon', 'Anse Royale'],
            ],
            [
                'name' => 'Togo',
                'iso2' => 'TG',
                'iso3' => 'TGO',
                'cities' => ['Lome', 'Sokode', 'Kara', 'Kpalime'],
            ],
            [
                'name' => 'Tunisia',
                'iso2' => 'TN',
                'iso3' => 'TUN',
                'cities' => ['Tunis', 'Sfax', 'Sousse', 'Kairouan'],
            ],
        ];

        foreach ($countries as $countryData) {
            $country = Country::query()->updateOrCreate(
                ['iso2' => $countryData['iso2']],
                [
                    'name' => $countryData['name'],
                    'iso3' => $countryData['iso3'],
                ]
            );

            $cityNames = [];

            foreach ($countryData['cities'] as $cityName) {
                $country->cities()->updateOrCreate(
                    ['name' => $cityName],
                    []
                );

                $cityNames[] = $cityName;
            }

            $country->cities()
                ->whereNotIn('name', $cityNames)
                ->delete();
        }
    }
}
