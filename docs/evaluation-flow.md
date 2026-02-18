# Flow d'evaluation et DecisionPath

Ce document decrit le fonctionnement du flow d'evaluation cote API, avec le role des DecisionPath, des DecisionNode et des routing_rules.

**Objectif**
Le parcours d'evaluation est pilote par un DecisionPath (arbre de decision). A chaque reponse, le moteur choisit la prochaine question (DecisionNode) ou termine la session. Certaines questions peuvent surcharger ce parcours via des routing_rules (sauts explicites).

**Composants clefs**
- `DecisionPath`: definition d'un parcours (cle, version, actif) et collection de nodes. `app/Domains/Decision/Models/DecisionPath.php`
- `DecisionNode`: etape du parcours. Peut referencer une question ou etre un node terminal (`end` ou `recommendation`). `app/Domains/Decision/Models/DecisionNode.php`
- `DynamicQuestionTemplate`: template de question, inclut `visible_if`, `routing_rules`, `bloc_key`, `answer_target`. `app/Domains/Evaluation/Models/DynamicQuestionTemplate.php`
- `EvaluationSession`: session en cours (decision_path_id, current_question_id, current_node_key, etc.). `app/Domains/Evaluation/Models/EvaluationSession.php`
- `DecisionPathEngine`: moteur de navigation dans un DecisionPath. `app/Services/Decisions/DecisionPathEngine.php`
- `EvaluationFlowService`: orchestration start/advance/state/complete. `app/Domains/Evaluation/Services/EvaluationFlowService.php`
- `QuestionFlowService`: gestion de `visible_if` et `routing_rules` cote questions. `app/Domains/Evaluation/Services/QuestionFlowService.php`

**Demarrage d'une evaluation**
L'entree se fait via `EvaluationFlowService::start`.
1. Validation du type `complete` ou `thematic`.
1. Selection du `DecisionPath`. Regles:
`complete` -> charge `dp_full_assessment`.
`thematic` -> charge un DecisionPath parmi `bloc_keys` (doit etre actif).
1. Creation de la session avec `decision_path_id`, `decision_path_key`, `decision_path_version`.
1. Recuperation du premier node via `DecisionPathEngine::getFirstNode`.
1. La session stocke `current_question_id` et `current_node_key` si le node porte une question.

**Avancement (une reponse)**
Le flux principal est dans `EvaluationFlowService::advance`.
1. Sauvegarde de la reponse (`AnswerService`).
1. Si la question cible un profil (`personal_profile` ou `medical_profile`), synchronisation des profils.
1. Application des `routing_rules` de la question via `QuestionFlowService::applyRoutingRules`.
1. Si un routing est retourne, tentative de resolution vers un node du DecisionPath:
   - `jump_question` -> resolve node par `question_key`.
   - `jump_bloc` -> resolve node par premier template visible du bloc.
1. Sinon, fallback sur `DecisionPathEngine::getNextNode`.
1. Si node terminal (`end` ou `recommendation`), la session est completee et un rapport est genere.

**DecisionPathEngine (navigation dans l'arbre)**
- Le node courant est resolu via `current_question_id`.
- Les branches du node (`branches`) sont comparees a la reponse normalisee.
- Si aucune branche ne match et qu'un `default` existe, il est utilise.
- Sinon, le moteur tombe sur le prochain node par `order`.

**visible_if (visibilite conditionnelle des questions)**
- La visibilite est evaluee par `QuestionFlowService::shouldBeVisible`.
- Les conditions sont interpretees par `evaluateCondition` avec un scope contenant:
`answers`, `profile.medical`, `profile.personal`, `session`, `question`, et la `answer` courante.
- Une liste de conditions est traitee comme un AND. Les formes supportees:
`any`, `all`, `not`, comparaisons (`=`, `!=`, `>`, `<`, `in`, etc.).
- Exemple: `visible_if` sur `breast_pain_cycle` qui depend de la question `breast_pain`.
Fichier: `database/seeders/Evaluation/SymptomBreastQuestionSeeder.php`

**Priorite des routing_rules**
`routing_rules` est execute AVANT la navigation DecisionPath.
Exemples concrets dans les seeders:
- `greeting.explicit_consent`: `yes` -> `bloc:profile_personal`, `no` -> `bloc:consent_declined`. Fichier: `database/seeders/GreetingSeeder.php`
- `medical_profile.primary_symptom_type`: `none` -> `bloc:lifestyle`, `pain_pelvis` -> `bloc:symptom_cervix`, `pain_mammaries` -> `bloc:symptom_breast`, `bleeding|both|lump` -> `bloc:symptom_triage`. Fichier: `database/seeders/Evaluation/MedicalProfileQuestionSeeder.php`
- `symptom_breast.breast_mass`: `true` -> `bloc:medical_profile` (reprise du profil medical). Fichier: `database/seeders/Evaluation/SymptomBreastQuestionSeeder.php`
- `symptom_breast.breast_nipple_bleeding`: `true` -> `end_session:urgent_breast_nipple_bleeding`. Fichier: `database/seeders/Evaluation/SymptomBreastQuestionSeeder.php`
- `symptom_breast.breast_skin_orange`: `true` -> `end_session:urgent_breast_skin_orange`. Fichier: `database/seeders/Evaluation/SymptomBreastQuestionSeeder.php`
- `symptom_cervix.pelvic_mass`: `true` -> `end_session:urgent_pelvic_mass`. Fichier: `database/seeders/Evaluation/SymptomCervicalQuestionSeeder.php`
- `profile_personal.cmu`: `true` -> `question:cmu_number`, `false` -> `question:last_screening_date`. Fichier: `database/seeders/Evaluation/PersonalProfileQuestionSeeder.php`
- `consent_declined`: progression par `question:` et fin via `end_session:consent_declined`. Fichier: `database/seeders/Evaluation/ConsentDeclinedQuestionSeeder.php`

**DecisionInsight (score, confiance, qualite)**
- Genere a chaque `start` et `advance` via `DecisionInsightService::generate`.
- S'appuie sur:
`RiskModelService` (score/risk_level) et `DataQualityService` (qualite de donnees).
- Champs principaux:
`risk_score`, `risk_level`, `score_total`, `confidence_score`,
`confidence_interval_low/high`, `data_quality`, `explanations`,
`suggested_action`, `requires_human_validation`, `human_validation_reason`.
- Fichiers:
`app/Domains/Decision/Services/DecisionInsightService.php`,
`app/Domains/Decision/Models/DecisionInsight.php`

**Risk Model & Data Quality**
- `RiskModelService`: additionne les `risk_weight` des reponses prises en compte.
`risk_level` est derive de `config('risk-level.levels')`.
Fichier: `app/Domains/Evaluation/Services/RiskModelService.php`
- `DataQualityService`: calcule un score de completude (`data_quality_score`),
et marque `critical_missing` si plus de 30% manquent.
Fichier: `app/Domains/Evaluation/Services/DataQualityService.php`

**Report**
- Un rapport est genere a la fin du parcours (`markCompleted`) via
`ReportGenerationService`.
Fichier: `app/Domains/Report/Services/ReportGenerationService.php`

**DecisionPaths seedes (actuels)**
- `dp_full_assessment` (parcours complet). Blocs en sequence: `greeting`, `profile_personal`, `profil_identite`, `profil_socio`, `consent_declined`, `medical_profile`, `symptom_breast`, `symptom_cervix`, `lifestyle`. Chaque question devient un node lie au suivant par `branches.default`. Termine sur `node_end_normal`. Fichier: `database/seeders/DecisionPathFullAssessmentSeeder.php`
- `dp_breast_pain` (douleur au sein). Inclut les questions `symptom_breast` avec `key like breast_pain%`. Termine sur `node_end_breast_pain`. Fichier: `database/seeders/DecisionPathBreastPainSeeder.php`
- `dp_breast_mass` (masse mammaire). Inclut plusieurs patterns `breast_mass%`, `breast_nipple_bleeding%`, `breast_skin_change%`, `breast_asymmetry%`, `axillary_ganglion%`. Termine sur `node_end_breast_mass`. Fichier: `database/seeders/DecisionPathBreastMassSeeder.php`

**Interaction DecisionPath vs bloc routing**
- Le DecisionPath definit l'ordre logique de base (suite de nodes). 
- Les `routing_rules` peuvent forcer un saut vers un bloc (`bloc:*`), forcer un saut vers une question (`question:*`), ou arreter la session (`end_session:*`).
- En cas de `jump_bloc`, la prochaine question est le premier template visible et non repondu du bloc cible.

**Schema simple (ordre de priorite)**
```text
Start -> DecisionPath (first node)
   Answer -> routing_rules ?
       yes -> jump_question/jump_bloc/end_session
       no  -> DecisionPathEngine (branches -> default -> next order)
```
