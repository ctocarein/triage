# Flow d'evaluation et moteur de triage (doc unifie)

Ce document unifie le flux technique actuel (DecisionPath, routing_rules) et la vision vNext (facts, risk, flags, confidence, gouvernance). Il sert de reference unique.

**Resume executif (1 page)**
- Le moteur actuel orchestre un `DecisionPath` (arbre de decision) et des `routing_rules` pour naviguer entre questions. La navigation est geree par `DecisionPathEngine` (`app/Services/Decisions/DecisionPathEngine.php`) et l'orchestration par `EvaluationFlowService` (`app/Domains/Evaluation/Services/EvaluationFlowService.php`).
- Les questions sont des `DynamicQuestionTemplate` (`app/Domains/Evaluation/Models/DynamicQuestionTemplate.php`) avec `visible_if` et `routing_rules`, evalues par `QuestionFlowService` (`app/Domains/Evaluation/Services/QuestionFlowService.php`).
- Les sessions d'evaluation sont stockees via `EvaluationSession` (`app/Domains/Evaluation/Models/EvaluationSession.php`) avec `current_question_id` et `current_node_key`.
- Le score et la qualite existent deja via `DecisionInsightService` (`app/Domains/Decision/Services/DecisionInsightService.php`), `RiskModelService` (`app/Domains/Evaluation/Services/RiskModelService.php`) et `DataQualityService` (`app/Domains/Evaluation/Services/DataQualityService.php`), puis un rapport final via `ReportGenerationService` (`app/Domains/Report/Services/ReportGenerationService.php`).
- La vision vNext introduit des moteurs explicites (`FactsEngine`, `RiskEngine`, `FlagEngine`, `DataQualityEngine`) pour separer clairement la navigation, le risque, les flags d'urgence et la confiance, tout en gardant une execution incrementale et audit-ready.
- Le document aligne les noms de classes/services et propose un tableau "etat actuel vs cible" pour guider l'implementation sans casser le runtime existant.

**1. Objectif**
- Piloter une session d'evaluation medicale deterministe, explicable, versionnee et audit-ready.
- Separer clairement navigation, risque clinique, qualite de donnees et orchestration.

**2. Composants (etat actuel)**
- `DecisionPath` (`app/Domains/Decision/Models/DecisionPath.php`) et `DecisionNode` (`app/Domains/Decision/Models/DecisionNode.php`): arbre de decision, nodes de questions ou terminaux (`end`/`recommendation`).
- `DynamicQuestionTemplate` (`app/Domains/Evaluation/Models/DynamicQuestionTemplate.php`): questions, `visible_if`, `routing_rules`, `bloc_key`, `answer_target`.
- `EvaluationSession` (`app/Domains/Evaluation/Models/EvaluationSession.php`): etat courant (path, node, question).
- Services:
- `DecisionPathEngine` (`app/Services/Decisions/DecisionPathEngine.php`)
- `EvaluationFlowService` (`app/Domains/Evaluation/Services/EvaluationFlowService.php`)
- `QuestionFlowService` (`app/Domains/Evaluation/Services/QuestionFlowService.php`)
- `DecisionInsightService` (`app/Domains/Decision/Services/DecisionInsightService.php`)
- `RiskModelService` (`app/Domains/Evaluation/Services/RiskModelService.php`)
- `DataQualityService` (`app/Domains/Evaluation/Services/DataQualityService.php`)
- `ReportGenerationService` (`app/Domains/Report/Services/ReportGenerationService.php`)

**3. Moteurs vNext (cibles)**
- `FactsEngine`: reponses -> facts cliniques.
- `RiskEngine`: facts -> score incremental (remplace/encapsule la logique de `RiskModelService`).
- `FlagEngine`: facts -> urgences (override).
- `DataQualityEngine`: completude -> confidence (remplace/encapsule la logique de `DataQualityService`).
- `DecisionInsightService`: synthese explicable (reste l'interface de sortie).

**3.1 Tableau etat actuel vs cible**
| Domaine | Etat actuel (code) | Cible vNext | Impact principal |
| --- | --- | --- | --- |
| Navigation | `DecisionPathEngine` + `QuestionFlowService` | Identique | Stable, aucune rupture |
| Orchestration | `EvaluationFlowService::start/advance` | Identique + snapshot v2 | Ajout snapshot versionne |
| Score | `RiskModelService` | `RiskEngine` | Extraction logique en moteur explicite |
| Qualite | `DataQualityService` | `DataQualityEngine` | Extraction logique en moteur explicite |
| Flags urgents | disperses dans `routing_rules` | `FlagEngine` + flags explicites | Separation urgences vs navigation |
| Insight | `DecisionInsightService` | identique | Extension champs si besoin |
| Audit | implicit via DB | snapshot versionne + hash | Reproductibilite forte |
| Backoffice | non detaille ici | endpoints gouvernance | Ajout API gouvernance |

**4. Flux d'evaluation (etat actuel)**
**Demarrage**
1. `EvaluationFlowService::start` valide le type (`complete`/`thematic`).
1. Selection du `DecisionPath` (`dp_full_assessment` ou bloc thematique actif).
1. Creation de session.
1. `DecisionPathEngine::getFirstNode` -> premiere question.

**Avancement (une reponse)**
1. Sauvegarde reponse (+ sync profils si besoin).
1. Application des `routing_rules`.
1. Si routing:
- `jump_question` par `question_key`.
- `jump_bloc` vers premier template visible du bloc.
- `end_session` termine.
1. Sinon: `DecisionPathEngine::getNextNode`.
1. Node terminal -> completion + rapport.

**DecisionPathEngine (regles)**
- Compare la reponse aux `branches` du node.
- Si pas de match, `default` sinon prochain par `order`.

**visible_if**
- Evalue par `QuestionFlowService::shouldBeVisible`.
- Scope: `answers`, `profile.medical`, `profile.personal`, `session`, `question`, `answer`.
- Conditions: `any`, `all`, `not`, comparaisons (`=`, `!=`, `>`, `<`, `in`, etc.).

**Priorite**
`routing_rules` > `DecisionPath` (branches/default/order).

**Insight & Report**
- `DecisionInsightService` (risk + data quality) sur `start` et `advance`.
- Rapport final via `ReportGenerationService`.

**5. Execution server vNext (cible)**
1. Store Answer (append-only).
1. FactsEngine -> snapshot.
1. RiskEngine -> snapshot.
1. FlagEngine -> overrides.
1. QuestionFlowService -> `routing_rules`.
1. DecisionPathEngine -> next node.
1. DataQualityEngine -> confidence.
1. DecisionInsightService -> synthese.
1. Persist snapshot.
1. Return next question or report.

**6. Modele clinique vNext**
**Facts**
- Signaux cliniques derives des reponses (ex: `has_mass`, `nipple_bleeding`).
- Les poids sont attaches aux facts, pas aux questions.

**Score**
- `S_raw = sum(weights)`.
- `S_norm = 100 * (S_raw / S_max)`.
- Classification stable (ex: low/moderate/high).

**Flags (override clinique)**
- Priorite absolue: `emergency` > `urgent` > score.
- Un flag ne peut pas etre annule par le score.

**Confidence**
- Basee sur completude, manquants critiques, incoherences.
- Ne modifie pas le risque, mais peut exiger validation humaine.

**7. Snapshot runtime vNext**
Chaque session conserve un snapshot versionne et reproductible:
```json
{
  "facts": {},
  "risk": { "raw": 0, "norm": 0, "level": "low" },
  "flags": {},
  "confidence": 1,
  "versions": {
    "protocol": "dp_full_assessment@1.0",
    "risk_model": "breast_v1",
    "engine": "v2"
  }
}
```

**8. DecisionPaths seedes (etat actuel)**
- `dp_full_assessment`: parcours complet par blocs.
- `dp_breast_pain`: douleur sein.
- `dp_breast_mass`: masse mammaire.

**9. Endpoints (cible)**
- Triage: start, answer, complete.
- Backoffice: decision-paths, questions, facts, risk-models, risk-signals, risk-flags, publish.

**10. Versioning & securite (cible)**
- Versions: protocol, risk_model, engine, snapshot_hash.
- Securite: idempotency, session locking, append-only answers, anti-loop routing, logs structures.

**11. Contraintes & defis (anticipation)**
**11.1 Complexite de la toile (graph integrity)**
- Risque: cycles (A -> bloc B -> retourne a A), nodes orphelins, routing incoherent.
- Garde-fou: ajouter un test de coherence qui parcourt les chemins possibles et detecte les cycles et cibles invalides.
- Action: etendre `CheckEvaluationCatalog` ou ajouter une commande dediee pour valider routing_rules + DecisionPath.

**11.2 Conflit Path vs Rules**
- Problematique: un `DecisionPath` lineaire peut etre court-circuite par une `routing_rule` (jump bloc/question).
- Impact: le calcul de `DataQualityService` peut etre fausse si on considere les questions sautees comme manquantes.
- Garde-fou: marquer explicitement les questions sautees comme `non_applicable` ou exclure du total.
- Action: introduire une logique de comptage "questions attendues" selon le chemin reel.

**11.3 Re-entry (retour en arriere)**
- Risque: changer une reponse qui avait declenche un jump laisse des reponses obsoletes.
- Impact: `RiskModelService` et vNext peuvent compter des reponses d'une branche invalide.
- Garde-fou: invalider les reponses en aval lors d'un changement de reponse "pivot".
- Action: ajouter une strategie de purge/invalidation des answers a partir du node courant.

**11.4 Performance DB**
- Risque: recalculs complets (visible_if, risk, quality) a chaque question.
- Optimisation: cache par bloc des `DynamicQuestionTemplate` + preload des answers.
- Action: ajouter cache applicatif (memoization/Redis) et limiter les requetes N+1.

**11.5 Tableau de bord de risque systeme (resume)**
- `DecisionPathEngine`: tres rapide, contrainte sur la stabilite des `order` a travers versions.
- `visible_if`: cout modere, attention a la complexite des conditions imbriquees.
- `RiskModel`: instantane, risque de double comptage si boucles non gerees.

**Recommandation**
Finaliser les seeders en isolant strictement les blocs symptomatiques (Cervix, Breast, Pain) et n'autoriser les sauts (`routing_rules`) qu'entre les entry points de ces blocs.

**12. Architecture des blocs (scalabilite)**
Le triage est divise en blocs logiques independants. Chaque bloc peut etre mis a jour sans affecter le reste du `DecisionPath`.

| ID Bloc | Nom du Bloc | Role strategique |
| --- | --- | --- |
| B0_TRIAGE | Aiguillage | Identifie le motif principal et redirige vers le bon bloc. |
| B1_PROFIL | Contexte patient | Age, menopause, antecedents, statut vaccinal (HPV). |
| B2_CERVIX | Signes col | Saignements, pertes anormales, douleurs post-coitales. |
| B3_MASSE | Compression | Masses palpables, troubles urinaires/digestifs. |
| B4_SYSTEM | Etat general | Fatigue, perte de poids, ganglions. |

**Mapping propose (conceptuel -> `bloc_key` actuel)**
| Bloc conceptuel | `bloc_key` actuel |
| --- | --- |
| B0_TRIAGE | `medical_profile` (ex: `primary_symptom_type`) |
| B1_PROFIL | `profile_personal`, `profil_identite`, `profil_socio` |
| B2_CERVIX | `symptom_cervix` |
| B3_MASSE | `symptom_breast` |
| B4_SYSTEM | `lifestyle` |

**13. Logique de routage "smart-path"**
**13.1 Pivot de triage (entree)**
```json
{
  "key": "primary_symptom_type",
  "text": "Quel signe vous inquiete aujourd'hui ?",
  "type": "select_one",
  "routing_rules": [
    { "if": { "source": "answer", "value": "bleeding", "operator": "=" }, "next": "bloc:symptom_cervix" },
    { "if": { "source": "answer", "value": "mass", "operator": "=" }, "next": "bloc:symptom_breast" },
    { "if": { "source": "answer", "value": "none", "operator": "=" }, "next": "bloc:profile_personal" }
  ]
}
```

**13.2 Detection de red flags (urgences)**
```json
{
  "key": "pelvic_mass_hard",
  "text": "La masse ressentie est-elle dure et fixe ?",
  "risk_weight": 5,
  "routing_rules": [
    { "if": { "source": "answer", "value": true, "operator": "=" }, "next": "end_session:urgent_oncology_referral" }
  ]
}
```

**14. Innovation data quality**
- Indice de completude: si < 70% des questions du bloc critique sont repondues, le `confidence_score` diminue.
- Detection d'incoherence: ex. patiente "menopausee" mais choisit "saignements durant les regles".
- Action: le systeme genere un `DecisionInsight` avec `requires_human_validation`.

**15. Contraintes de scalabilite (securite logicielle)**
- Acyclic graph check: avant chaque deploiement de seeder, un test verifie qu'aucune `routing_rule` ne cree de boucle infinie.
- Versionnage des paths: chaque `EvaluationSession` est liee a une version precise du `DecisionPath` pour eviter les erreurs si une question est supprimee en cours d'utilisation.

**Schema**
```text
Start -> DecisionPath (first node)
   Answer -> routing_rules ?
       yes -> jump_question/jump_bloc/end_session
       no  -> DecisionPathEngine (branches -> default -> next order)
```
