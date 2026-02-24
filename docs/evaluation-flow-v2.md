# CHEREH - Specification Officielle du Moteur de Triage vNext

Version: 1.0  
Statut: Ready for Implementation  
Scope: API Triage + Backoffice Clinique

---

# 1. Objectif

Mettre en place un moteur de triage medical:

- Deterministe
- Incremental (O(1) par reponse)
- Explicable
- Versionne
- Audit-ready
- Safe-by-design
- Gouvernable via Backoffice

Le systeme separe strictement:

1. Navigation (DecisionPath)
2. Modele Clinique (Facts + Risk + Flags)
3. Qualite des Donnees (Confidence)
4. Orchestration d'Evaluation

---

# 2. Architecture Logique

## 2.1 Moteurs Internes

| Moteur | Responsabilite |
| --- | --- |
| DecisionPathEngine | Navigation pure dans l'arbre |
| QuestionFlowService | visible_if + routing_rules |
| FactsEngine | Reponse -> Faits |
| RiskEngine | Faits -> Score incremental |
| FlagEngine | Faits -> Urgence |
| DataQualityEngine | Completude -> Confiance |
| DecisionInsightService | Synthese explicable |

Navigation != Risque != Confiance

---

# 3. Modele Clinique

## 3.1 Facts

Les facts sont des signaux cliniques derives des reponses.

Exemples:

- `has_mass`
- `nipple_bleeding`
- `pelvic_mass`
- `age_over_45`

Les poids sont associes aux facts, jamais aux questions.

---

## 3.2 Score

### Etape 1 - Score Brut

`S_raw = sum(w_i)`

Somme des poids associes aux facts actives.

Calcul incremental a chaque reponse.

---

### Etape 2 - Normalisation

`S_norm = 100 * (S_raw / S_max)`

`S_max` est defini et versionne dans le RiskModel.

---

### Etape 3 - Classification

- 0-30 -> Faible
- 30-60 -> Modere
- > 60 -> Eleve

Seuils stables independamment du nombre de questions.

---

## 3.3 Flags (Override Clinique)

Priorite absolue:

1. `emergency`
2. `urgent`
3. score-based decision

Un flag ne peut jamais etre annule par le score.

---

## 3.4 Confiance (Confidence Score)

La confiance depend de:

- Completude des reponses
- Donnees critiques manquantes
- Incoherences detectees

La confiance n'altere pas le niveau de risque.

Elle determine uniquement:

- `requires_human_validation`
- `human_validation_reason`

---

# 4. Snapshot Runtime

Chaque `EvaluationSession` contient un snapshot incremental:

```json
{
  "facts": {},
  "risk": {
    "raw": 0,
    "norm": 0,
    "level": "low"
  },
  "flags": {},
  "confidence": 1,
  "versions": {
    "protocol": "dp_full_assessment@1.0",
    "risk_model": "breast_v1",
    "engine": "v2"
  }
}
```

Le snapshot est:

- Persiste
- Versionne
- Reproductible
- Audit-ready

---

# 5. Flow Triage Runtime

## 5.1 Demarrage d'Evaluation

`POST /api/triage/evaluations`

### Payload

```json
{
  "type": "complete",
  "bloc_keys": []
}
```

### Response

```json
{
  "session_id": "uuid",
  "decision_path_key": "dp_full_assessment",
  "decision_path_version": "1.0",
  "next_question": {},
  "decision_insight_preview": {},
  "progress": 0
}
```

---

## 5.2 Soumission d'une Reponse

`POST /api/triage/evaluations/{session_id}/answers`

### Payload

```json
{
  "question_key": "breast_mass",
  "answer": true,
  "idempotency_key": "uuid"
}
```

---

## 5.3 Ordre d'Execution Serveur

1. Store Answer (append-only)
2. FactsEngine -> update snapshot
3. RiskEngine -> update snapshot
4. FlagEngine -> evaluate overrides
5. QuestionFlowService -> routing_rules
6. DecisionPathEngine -> next node
7. DataQualityEngine -> confidence
8. DecisionInsightService -> synthese
9. Persist snapshot
10. Return next question or report

---

## 5.4 Reponse API Intermediaire

```json
{
  "next_question": {},
  "decision_insight": {
    "risk_score_raw": 12,
    "risk_score_norm": 68,
    "risk_level": "high",
    "flags": [],
    "confidence_score": 0.74,
    "requires_human_validation": false
  },
  "progress": 42
}
```

---

## 5.5 Completion

`POST /api/triage/evaluations/{session_id}/complete`

### Response

```json
{
  "report": {},
  "decision_insight": {},
  "audit_trace_id": "hash"
}
```

---

# 6. Backoffice - Gouvernance Clinique

Le backoffice permet:

- Gerer DecisionPaths
- Gerer Questions
- Gerer Facts
- Gerer RiskModels
- Gerer RiskSignals (poids)
- Gerer RiskFlags
- Publier versions
- Verrouiller modeles

---

# 7. Endpoints Backoffice

## 7.1 DecisionPaths

- `GET /api/backoffice/decision-paths`
- `POST /api/backoffice/decision-paths`
- `PUT /api/backoffice/decision-paths/{id}`
- `POST /api/backoffice/decision-paths/{id}/publish`

---

## 7.2 Questions

- `GET /api/backoffice/questions`
- `POST /api/backoffice/questions`
- `PUT /api/backoffice/questions/{id}`

---

## 7.3 Facts

- `GET /api/backoffice/facts`
- `POST /api/backoffice/facts`
- `PUT /api/backoffice/facts/{id}`

---

## 7.4 Fact Mapping

`POST /api/backoffice/fact-mappings`

```json
{
  "question_key": "breast_mass",
  "answer_value": true,
  "fact_key": "has_mass"
}
```

---

## 7.5 Risk Models

- `GET /api/backoffice/risk-models`
- `POST /api/backoffice/risk-models`
- `PUT /api/backoffice/risk-models/{id}`
- `POST /api/backoffice/risk-models/{id}/publish`

---

## 7.6 Risk Signals

`POST /api/backoffice/risk-signals`

```json
{
  "risk_model_key": "breast_v1",
  "fact_key": "has_mass",
  "weight": 6
}
```

---

## 7.7 Risk Flags

`POST /api/backoffice/risk-flags`

```json
{
  "risk_model_key": "breast_v1",
  "fact_key": "nipple_bleeding",
  "type": "urgent",
  "priority": 100
}
```

---

# 8. Versioning

Chaque session stocke:

- `protocol_version`
- `risk_model_version`
- `engine_version`
- `snapshot_hash`

Permet:

- Reproduction exacte
- Audit medical
- Conformite reglementaire

---

# 9. Securite

- Idempotency obligatoire
- Session locking
- Append-only answers
- Anti-loop routing
- Logs structures

---

# 10. Priorite Decisionnelle

Ordre strict:

1. Flags
2. Score normalise
3. Confiance
4. Navigation

Navigation ne modifie jamais le risque.

---

# 11. Resultat

Le moteur est:

- Scalable
- Explicable
- Audit-ready
- Multi-pays
- Multi-protocoles
- Compatible terrain a faible connectivite

CHEREH devient un moteur de triage clinique industriel.

---

# 12. Contraintes et Defis (Anticipation Systeme)

Cette section formalise les risques structurels du moteur et les garde-fous obligatoires.

---

## 12.1 La Complexite de la Toile (Graph Integrity)

### Probleme

L'augmentation des `routing_rules` (`jump_bloc`, `jump_question`) peut:

- Creer des boucles infinies
- Generer des nodes orphelins
- Produire des chemins impossibles

Exemple critique:  
A -> Bloc B -> regle -> retour vers A

### Risque

- Boucle infinie runtime
- Blocage session
- Double comptage risque
- UX incoherente

### Solution Obligatoire

Implementer un **Graph Validation Script** execute:

- A chaque publish de DecisionPath
- A chaque publish de RiskModel

Le script doit:

1. Construire le graphe complet (nodes + routing edges)
2. Detecter les cycles (DFS ou algorithme de Tarjan)
3. Verifier qu'il existe au moins un chemin terminal
4. Identifier les nodes inaccessibles
5. Refuser la publication si incoherence detectee

Publication impossible si le graphe n'est pas valide.

---

## 12.2 Conflit "DecisionPath vs Routing Rules"

### Probleme

Le DecisionPath definit un ordre lineaire:  
1 -> 2 -> 3 -> 4

Mais une `routing_rule` peut forcer:  
2 -> 10

Les nodes 3-9 deviennent implicitement ignores.

### Impact

- Faussage de la completude
- Mauvais calcul du DataQualityScore
- Score clinique incoherent

### Regle Architecturale

Les questions court-circuitees doivent etre marquees:

- `status = "skipped"`
- `skip_reason = "routing_rule"`

Le DataQualityEngine doit:

- Calculer la completude uniquement sur le chemin reellement execute
- Exclure les nodes marques `skipped`

La completude devient relative au chemin effectif.

---

## 12.3 Gestion des Re-entry (Retour arriere)

### Probleme

Si une reponse change apres avoir declenche un `jump_bloc`:

- Le chemin parcouru apres le saut devient invalide
- Des points de risque peuvent rester actifs

### Risque

- Double comptage
- Faux positifs
- Incoherence snapshot

### Solution

Implementer un mecanisme de **Branch Invalidation**.

Lorsqu'une reponse modifiee impacte:

- Un fact cle
- Un routing declenche
- Un flag

Le moteur doit:

1. Identifier le node pivot
2. Supprimer toutes les reponses apres ce node
3. Recalculer snapshot depuis le pivot
4. Nettoyer les facts/risk lies a la branche supprimee

Les reponses supprimees doivent rester en base (append-only) mais marquees:

- `invalidated = true`
- `invalidation_reason`

Le RiskEngine ne doit jamais compter des facts invalides.

---

## 12.4 Performance Base de Donnees

### Probleme

A chaque question:

- Chargement DecisionPath
- Evaluation `visible_if`
- Calcul score

Sous forte charge (campagne nationale):

- Explosion requetes SQL
- Latence croissante

### Optimisations Obligatoires

1. Cache en memoire des `DynamicQuestionTemplate` par bloc
2. Cache du DecisionPath compile
3. Pre-compilation des expressions `visible_if`
4. Snapshot incremental (eviter recalcul global)
5. Indexation forte sur:
- `session_id`
- `question_key`
- `fact_key`

Objectif: temps constant par reponse.

---

# 13. Tableau de Bord de Risque Systeme

| Composant | Performance | Contrainte Majeure |
| --- | --- | --- |
| DecisionPathEngine | Rapide (O(1) a O(n)) | Maintenir coherence des versions |
| VisibleIf Logic | Moderee | Complexite conditions imbriquees |
| RiskEngine | Instantane | Eviter double comptage |
| FlagEngine | Instantane | Priorite absolue garantie |
| DataQualityEngine | Leger | Coherence chemin reel |

---

# 14. Recommandation Structurante

Pour limiter la complexite:

1. Isoler strictement les blocs symptomatiques (Breast, Cervix, Pain)
2. Autoriser les `routing_rules` uniquement entre "Entry Points" de blocs
3. Interdire les sauts internes non controles
4. Forcer validation graphe avant publication
5. Versionner tout changement structurel

Principe directeur:

Moins de sauts transversaux = plus de stabilite.

Le moteur doit rester un graphe maitrise, pas une toile incontrolee.

---

# 15. Regles Strictes de Design des Blocs

Cette section formalise les regles obligatoires pour garantir stabilite, coherence et auditabilite.

## 15.1 Definition d'un Bloc

Un bloc est une unite logique coherente representant:

- Un theme fonctionnel (ex: `profil_socio`)
- Un ensemble de questions sequentielles
- Un seul point d'entree

## 15.2 Regles Obligatoires

1. Un bloc = exactement un `entry_point`.
2. Les questions internes ne peuvent jamais etre ciblees par routing externe.
3. Les `routing_rules` ne peuvent cibler que:
- `bloc:<entry_point>`
- `end_session`
4. Aucun jump question->question hors bloc.
5. Les blocs ne doivent pas dependre d'un order global inter-bloc.
6. Un bloc doit pouvoir etre execute independamment.
7. Un bloc ne doit pas contenir de logique clinique implicite non versionnee.

## 15.3 Typologie des Blocs

- Bloc Structurel (profil, consentement)
- Bloc Clinique (symptomes)
- Bloc Conditionnel (`cmu_details`)
- Bloc Terminal (recommendation / end)

Les blocs conditionnels doivent toujours etre atteints via routing explicite.

---

# 16. Validateur de Seeder (Anti-Incoherence)

Tout publish doit declencher un validateur automatique.

## 16.1 Validation Structurelle

Le validateur doit verifier:

1. Chaque bloc possede un seul `entry_point`.
2. Tous les blocs sont accessibles depuis le DecisionPath.
3. Aucun bloc n'est orphelin.
4. Aucun routing ne cible une question non-entry.
5. Aucun cycle infini dans le graphe.
6. Tous les chemins menent a au moins un node terminal.

## 16.2 Validation Graphique (Cycle Detection)

Algorithme recommande:

- Construction d'un graphe dirige
- DFS avec detection de back-edge
- Refus de publication si cycle non autorise

## 16.3 Validation Clinique

1. `S_max` recalcule automatiquement.
2. Aucune duplication de fact dans `RiskSignals`.
3. Aucun flag sans fact associe.
4. Aucun fact sans mapping question.

## 16.4 Validation Navigation vs Routing

Le validateur doit simuler:

- Parcours lineaire
- Parcours avec chaque routing active
- Parcours avec retour arriere

Si un etat produit:

- Boucle
- Node inaccessible
- Bloc jamais termine

-> Publication refusee.

---

# 17. Hierarchie Officielle: DecisionPath vs Routing Rules

La hierarchie suivante est obligatoire.

## 17.1 Ordre d'Autorite

1. FlagEngine (override absolu)
2. Routing Rules
3. DecisionPath
4. Order interne du bloc

Navigation ne doit jamais modifier le risque.

## 17.2 Regle d'Execution

Dans `advance()`:

1. Apply FactsEngine
2. Apply RiskEngine
3. Apply FlagEngine
4. If flag triggered -> terminate
5. Apply RoutingRules
- Si routing -> jump bloc entry
- Stop execution
6. Sinon -> DecisionPathEngine

RoutingRules sont un override controle du DecisionPath.  
DecisionPath est la navigation par defaut.

## 17.3 Interdictions Strictes

- RoutingRules ne doivent jamais modifier snapshot risk.
- DecisionPath ne doit jamais dependre d'un score.
- Aucun calcul clinique dans DecisionPath.

---

Ces regles structurent le moteur pour un deploiement a grande echelle tout en conservant stabilite, auditabilite et securite clinique.

---
