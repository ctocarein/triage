# TRIAGE API

API Laravel pour la plateforme TRIAGE.

## Prerequis

- PHP 8.2+
- Composer
- MySQL ou MariaDB

## Installation

1. `composer install`
2. Copier `.env.example` vers `.env` puis definir `APP_NAME`, `APP_VERSION`, `SWAGGER_SERVER_URL` et les acces base de donnees.
3. `php artisan key:generate`
4. `php artisan migrate --seed`

## Documentation API (Swagger)

- Generer la documentation : `php artisan l5-swagger:generate` (ajoutez `--force` pour ecraser les fichiers existants).
- Les annotations OpenAPI se trouvent dans `app/Swagger` et dans les controlleurs (`app/Http/Controllers`).
- L'interface est disponible sur `http://localhost/api/docs` (redirection rapide via `http://localhost/docs`).
- Le JSON brut est expose sur `http://localhost/api/docs.json` et stocke dans `storage/api-docs/api-docs.json`.
- Sanctum est configure comme schema `bearer` (`Authorization: Bearer {token}`). Ajoutez `security={{}}` sur un endpoint pour le rendre public.

## Tests

Executer la suite : `php artisan test`

## Decision thresholds

Les seuils utilises par `DecisionInsightService` sont centralises dans `config/decision-thresholds.php`.

Format attendu (extrait) :

```php
return [
    'confidence' => [
        'min' => 0.7,          // Confiance minimale pour eviter une revue humaine
        'high_bonus' => 0.05,  // Bonus si niveau de risque = high
        'min_margin' => 0.05,  // Marge minimale pour l'intervalle de confiance
        'margin_factor' => 0.2 // Facteur applique a (1 - confidence)
    ],
    'quality' => [
        'min' => 0.6,
        'critical_missing_requires_review' => true
    ],
    'review' => [
        'high_risk_requires_review' => true
    ],
    'suggested_action' => [
        'high' => 'refer_to_specialist',
        'moderate' => 'medical_check',
        'low' => 'self_care',
        'default' => 'self_care'
    ]
];
```

## Licence

Le projet est distribue sous licence [MIT](https://opensource.org/licenses/MIT).
