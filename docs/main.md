TRIAGE API — Description Complète du Projet
Vue d'ensemble
TRIAGE API est une API médicale Laravel 12 pour une plateforme de triage et d'aide à la décision clinique, probablement orientée dépistage de cancers (sein, col utérin). Elle est conçue pour des professionnels de santé, agents de terrain, et patients (bénéficiaires) dans un contexte multi-organisationnel.

Architecture
Le projet suit une architecture Domain-Driven Design (DDD) stricte avec :

Séparation claire par domaine métier
Pattern Repository + Mappers (DTOs/Entities)
Architecture événementielle (Event Bus, Outbox Pattern)
Support CQRS (Commands & Queries)
Multi-tenancy (Organisations)
Domaines métier (10 domaines)
1. Identity — Authentification & Utilisateurs
Authentification via Laravel Sanctum (tokens Bearer)
Gestion des identités, credentials, devices enregistrés
Security Gate : PIN obligatoire, tentatives limitées, verrou d'urgence (panic-lock)
Événements de sécurité audités
7 rôles : PlatformAdmin, OrgManager, Beneficiary, FieldAgent, Ambassador, Practitioner, System
2. Evaluation — Cœur métier : Triage médical
C'est le domaine le plus complexe avec 16 services internes :

Service	Rôle
EvaluationFlowService	Orchestration générale
QuestionFlowService	Visibilité et routage des questions
AnswerService	Traitement des réponses
FactsEngine	Conversion réponses → faits cliniques
RiskEngine	Calcul du score de risque (incrémental)
FlagEngine	Drapeaux urgence/emergency
DataQualityEngine	Qualité et complétude des données
DecisionInsightService	Décision expliquable
GoldenPathRunner	Tests de parcours de référence
RiskModelService	Gestion modèles de risque versionnés
Flux d'évaluation :

POST /evaluations/start → démarre une session
POST /evaluations/{session}/advance → avance dans le questionnaire
Réponses → Faits → Scores de risque → Flags → Décision
POST /evaluations/{session}/complete → finalise
POST /decisions/{session}/finalize → génère le rapport
Classification du risque :

Faible : score 0–30
Modéré : 30–60
Élevé : >60
Actions suggérées : self_care, medical_check, refer_to_specialist
3. Ambassador — Programme de parrainage
Système de codes de référencement
Badges d'achievement, niveaux ambassadeur
Métriques, tableau de bord, audit logs
Politiques anti-triche (AntiGamingPolicy)
Jobs de recalcul en arrière-plan
4. Engagement — QR Codes & Campagnes marketing
Génération, validation, révocation de QR codes
Suivi des scans (ScanEvent)
Campagnes marketing avec rapports
Réservé aux rôles PlatformAdmin / OrgManager
5. Organization — Multi-tenancy
Organisations, pays, villes, communes
Cache de tableau de bord (OrganizationDashboardCacheObserver)
Memberships utilisateur-organisation
6. Profile — Profils utilisateurs (3 types)
PersonalProfile — Informations personnelles
MedicalProfile — Antécédents médicaux
ProfessionalProfile — Informations professionnelles (praticiens)
7. Decision — Arbres de décision
DecisionPath — Parcours décisionnel
DecisionNode — Nœuds de l'arbre
DecisionInsight — Résultats expliqués avec score de confiance
8. ExternalApi — Intégration PSM
Synchronisation asynchrone vers un système PSM externe
Outbox Pattern pour fiabilité (pas de perte d'événements)
Job SyncEvaluationToPSM déclenché à la complétion d'une évaluation
9. Notification — Rappels
UserReminder — Notifications/rappels utilisateurs
10. Recommendation — Capsules d'information
Capsule + CapsuleItem — Recommandations médicales à afficher après évaluation
Base de données
75 migrations, organisées chronologiquement
23 seeders avec données cliniques réelles (questions, faits, signaux de risque, flags, modèles)
Données médicales pour cancers du sein et du col de l'utérus
Tables versionnées (model_versions, shadow columns vnext)
Support soft deletes sur plusieurs tables
Sécurité
Authentification Sanctum (Bearer tokens)
Security Gate personnalisé (middleware security.gate) : PIN requis pour les endpoints sensibles
Idempotency keys pour prévenir les doubles soumissions
Audit trail complet (security events, ambassador audit logs)
CORS configuré via config/cors.php + config NGINX incluse
Technologies
Composant	Technologie
Framework	Laravel 12.0
PHP	8.2+
Auth	Laravel Sanctum
PDF	dompdf/dompdf
QR Codes	simplesoftwareio/simple-qrcode
Cache/Queue	Redis (predis)
Tests	Pest PHP 3.8+
DB (dev)	SQLite
DB (prod)	MySQL / MariaDB
Résumé chiffré
Élément	Quantité
Domaines DDD	10
Fichiers app/	250+
Migrations	75
Seeders	23
Services métier	30+
Fichiers de routes	8
Rôles utilisateurs	7
En une phrase : TRIAGE API est une plateforme médicale complète pour le dépistage de cancers, avec un moteur de triage clinique intelligent, un programme d'ambassadeurs, du multi-tenancy, et une intégration externe, le tout bâti sur une architecture DDD événementielle en Laravel 12.