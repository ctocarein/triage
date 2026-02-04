<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Triage API",
 *     version="1.0.0",
 *     description="API documentation."
 * )
 * @OA\Server(
 *     url="/api",
 *     description="API base path"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer"
 * )
 * @OA\Schema(
 *     schema="ApiMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *     schema="UnauthorizedError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Unauthenticated.")
 * )
 * @OA\Schema(
 *     schema="ForbiddenError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Forbidden.")
 * )
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 * @OA\Schema(
 *     schema="Campaign",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="objective", type="string"),
 *     @OA\Property(property="organization_id", type="string", format="uuid"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * @OA\Schema(
 *     schema="CampaignCollection",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Campaign")
 *     )
 * )
 * @OA\Schema(
 *     schema="CampaignStoreRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="objective", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="CampaignUpdateRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="objective", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="CampaignReport",
 *     type="object",
 *     @OA\Property(property="summary", type="string"),
 *     @OA\Property(property="totals", type="object")
 * )
 * @OA\Schema(
 *     schema="CampaignResourceWithMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", ref="#/components/schemas/Campaign")
 * )
 * @OA\Schema(
 *     schema="QrCode",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="QrCodeCollection",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/QrCode")
 *     )
 * )
 * @OA\Schema(
 *     schema="QrCodeStoreRequest",
 *     type="object",
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="quantity", type="integer")
 * )
 * @OA\Schema(
 *     schema="QrCodeUpdateRequest",
 *     type="object",
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="QrCodeStats",
 *     type="object",
 *     @OA\Property(property="scans", type="integer"),
 *     @OA\Property(property="unique_users", type="integer")
 * )
 * @OA\Schema(
 *     schema="QrCodeResourceWithMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", ref="#/components/schemas/QrCode")
 * )
 * @OA\Schema(
 *     schema="QrCodeCollectionWithMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/QrCode")
 *     )
 * )
 * @OA\Schema(
 *     schema="Identity",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="kind", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="Credential",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="identifier", type="string"),
 *     @OA\Property(property="verified_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="SecurityGate",
 *     type="object",
 *     @OA\Property(property="state", type="string", enum={"entry","limited","full","locked"}),
 *     @OA\Property(property="gate_required", type="boolean"),
 *     @OA\Property(property="locked_until", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="panic_locked_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="device_trusted", type="boolean")
 * )
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="Membership",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(property="organization_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(
 *         property="role",
 *         type="string",
 *         enum={"Beneficiary","FieldAgent","Ambassador","Practitioner","OrgManager","PlatformAdmin","System"}
 *     ),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="EvaluationSession",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="score", type="number", format="float", nullable=true),
 *     @OA\Property(property="risk_level", type="string", nullable=true),
 *     @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="decision_path_id", type="integer", nullable=true),
 *     @OA\Property(property="decision_path_key", type="string", nullable=true),
 *     @OA\Property(property="decision_path_version", type="string", nullable=true),
 *     @OA\Property(property="catalog_version", type="integer", nullable=true),
 *     @OA\Property(property="risk_model_version", type="integer", nullable=true),
 *     @OA\Property(property="current_question_id", type="integer", nullable=true),
 *     @OA\Property(property="current_node_key", type="string", nullable=true),
 *     @OA\Property(property="target_blocs", type="array", @OA\Items(type="string"), nullable=true),
 *     @OA\Property(property="visited_blocs", type="array", @OA\Items(type="string"), nullable=true)
 * )
 * @OA\Schema(
 *     schema="EvaluationQuestion",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="question_id", type="integer"),
 *     @OA\Property(property="node_id", type="integer"),
 *     @OA\Property(property="node_type", type="string"),
 *     @OA\Property(property="key", type="string"),
 *     @OA\Property(property="text", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="bloc_key", type="string", nullable=true),
 *     @OA\Property(property="answer_target", type="string", nullable=true),
 *     @OA\Property(property="order", type="integer", nullable=true),
 *     @OA\Property(property="risk_weight", type="number", format="float", nullable=true),
 *     @OA\Property(property="min", type="number", format="float", nullable=true),
 *     @OA\Property(property="max", type="number", format="float", nullable=true),
 *     @OA\Property(property="media_type", type="string", nullable=true),
 *     @OA\Property(property="media_url", type="string", nullable=true),
 *     @OA\Property(property="options", type="array", @OA\Items(type="object"), nullable=true),
 *     @OA\Property(property="visible_if", type="array", @OA\Items(type="object"), nullable=true),
 *     @OA\Property(property="outcome_message", type="string", nullable=true),
 *     @OA\Property(property="specialties", type="array", @OA\Items(type="string"), nullable=true)
 * )
 * @OA\Schema(
 *     schema="EvaluationInsight",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="model_version", type="string"),
 *     @OA\Property(property="calibration_tag", type="string", nullable=true),
 *     @OA\Property(property="risk_score", type="number", format="float"),
 *     @OA\Property(property="risk_level", type="string"),
 *     @OA\Property(property="score_total", type="number", format="float"),
 *     @OA\Property(property="confidence_score", type="number", format="float"),
 *     @OA\Property(property="confidence_interval_low", type="number", format="float"),
 *     @OA\Property(property="confidence_interval_high", type="number", format="float"),
 *     @OA\Property(property="data_quality", type="object"),
 *     @OA\Property(property="explanations", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="suggested_action", type="string"),
 *     @OA\Property(property="requires_human_validation", type="boolean"),
 *     @OA\Property(property="human_validation_reason", type="string", nullable=true),
 *     @OA\Property(property="final_decision", type="object", nullable=true)
 * )
 * @OA\Schema(
 *     schema="EvaluationReport",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="score", type="number", format="float"),
 *     @OA\Property(property="max_score", type="number", format="float", nullable=true),
 *     @OA\Property(property="risk_level", type="string"),
 *     @OA\Property(property="recommendation", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="EvaluationFlowResponse",
 *     type="object",
 *     @OA\Property(property="session", ref="#/components/schemas/EvaluationSession"),
 *     @OA\Property(property="insight", ref="#/components/schemas/EvaluationInsight", nullable=true),
 *     @OA\Property(property="question", ref="#/components/schemas/EvaluationQuestion", nullable=true),
 *     @OA\Property(property="report", ref="#/components/schemas/EvaluationReport", nullable=true),
 *     @OA\Property(property="message", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="EvaluationStartRequest",
 *     type="object",
 *     required={"type"},
 *     @OA\Property(property="type", type="string", enum={"complete","thematic"}),
 *     @OA\Property(property="bloc_keys", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="referral_code", type="string")
 * )
 * @OA\Schema(
 *     schema="EvaluationAdvanceRequest",
 *     type="object",
 *     required={"question_id"},
 *     @OA\Property(property="question_id", type="integer"),
 *     @OA\Property(property="value"),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="string", format="uuid")),
 *     @OA\Property(property="is_skipped", type="boolean")
 * )
 * @OA\Schema(
 *     schema="UploadResponse",
 *     type="object",
 *     @OA\Property(property="file_id", type="string", format="uuid"),
 *     @OA\Property(
 *         property="upload",
 *         type="object",
 *         @OA\Property(property="file_id", type="string", format="uuid"),
 *         @OA\Property(property="original_name", type="string"),
 *         @OA\Property(property="mime_type", type="string"),
 *         @OA\Property(property="size", type="integer")
 *     )
 * )
 * @OA\Schema(
 *     schema="EvaluationAnswer",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="evaluation_session_id", type="integer"),
 *     @OA\Property(property="dynamic_question_template_id", type="integer"),
 *     @OA\Property(property="value", type="object", nullable=true),
 *     @OA\Property(property="is_skipped", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * @OA\Schema(
 *     schema="EvaluationAnswerStoreRequest",
 *     type="object",
 *     required={"dynamic_question_template_id"},
 *     @OA\Property(property="dynamic_question_template_id", type="integer"),
 *     @OA\Property(property="value", type="object", nullable=true),
 *     @OA\Property(property="is_skipped", type="boolean")
 * )
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="total", type="integer"),
 *     @OA\Property(property="last_page", type="integer")
 * )
 * @OA\Schema(
 *     schema="IdentityRegisterRequest",
 *     type="object",
 *     required={"credential_type","identifier"},
 *     @OA\Property(property="kind", type="string", example="person"),
 *     @OA\Property(property="credential_type", type="string", example="phone"),
 *     @OA\Property(property="identifier", type="string", example="+243000000000"),
 *     @OA\Property(property="secret", type="string", example="1234", description="Optional for phone credentials."),
 *     @OA\Property(property="device_fingerprint", type="string", example="device-123"),
 *     @OA\Property(
 *         property="role",
 *         type="string",
 *         example="Beneficiary",
 *         enum={"Beneficiary","FieldAgent","Ambassador","Practitioner","OrgManager","PlatformAdmin","System"}
 *     ),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="IdentityRegisterResponse",
 *     type="object",
 *     @OA\Property(property="token", type="string"),
 *     @OA\Property(property="identity", ref="#/components/schemas/Identity"),
 *     @OA\Property(property="credential", ref="#/components/schemas/Credential"),
 *     @OA\Property(property="membership", ref="#/components/schemas/Membership"),
 *     @OA\Property(property="security_gate", ref="#/components/schemas/SecurityGate")
 * )
 * @OA\Schema(
 *     schema="IdentityLoginRequest",
 *     type="object",
 *     required={"credential_type","identifier"},
 *     @OA\Property(property="credential_type", type="string", example="email"),
 *     @OA\Property(property="identifier", type="string", example="user@example.com"),
 *     @OA\Property(property="secret", type="string", example="secret123", description="Optional for phone credentials."),
 *     @OA\Property(property="device_fingerprint", type="string", example="device-123")
 * )
 * @OA\Schema(
 *     schema="IdentityLoginResponse",
 *     type="object",
 *     @OA\Property(property="token", type="string"),
 *     @OA\Property(property="identity", ref="#/components/schemas/Identity"),
 *     @OA\Property(property="security_gate", ref="#/components/schemas/SecurityGate")
 * )
 * @OA\Schema(
 *     schema="IdentityUnlockRequest",
 *     type="object",
 *     required={"method"},
 *     @OA\Property(property="method", type="string", enum={"pin","agent","biometric"}),
 *     @OA\Property(property="secret", type="string", example="1234"),
 *     @OA\Property(property="device_fingerprint", type="string", example="device-123")
 * )
 * @OA\Schema(
 *     schema="IdentityUnlockResponse",
 *     type="object",
 *     @OA\Property(property="security_gate", ref="#/components/schemas/SecurityGate")
 * )
 * @OA\Schema(
 *     schema="IdentitySetSecretRequest",
 *     type="object",
 *     required={"secret"},
 *     @OA\Property(property="secret", type="string", example="1234")
 * )
 * @OA\Schema(
 *     schema="IdentitySetSecretResponse",
 *     type="object",
 *     @OA\Property(property="credential", ref="#/components/schemas/Credential")
 * )
 * @OA\Schema(
 *     schema="IdentityUpdateRequest",
 *     type="object",
 *     @OA\Property(property="kind", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="IdentityResponse",
 *     type="object",
 *     @OA\Property(property="identity", ref="#/components/schemas/Identity")
 * )
 * @OA\Schema(
 *     schema="IdentityCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Identity")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 * @OA\Schema(
 *     schema="MembershipCreateRequest",
 *     type="object",
 *     required={"identity_id","role"},
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(
 *         property="role",
 *         type="string",
 *         enum={"Beneficiary","FieldAgent","Ambassador","Practitioner","OrgManager","PlatformAdmin","System"}
 *     ),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="MembershipUpdateRequest",
 *     type="object",
 *     required={"status"},
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="MembershipResponse",
 *     type="object",
 *     @OA\Property(property="membership", ref="#/components/schemas/Membership")
 * )
 * @OA\Schema(
 *     schema="MembershipCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Membership")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 * @OA\Schema(
 *     schema="ProfessionalProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(property="organization_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="full_name", type="string"),
 *     @OA\Property(property="job_title", type="string", nullable=true),
 *     @OA\Property(property="address", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="ProfessionalProfileCreateRequest",
 *     type="object",
 *     required={"full_name"},
 *     @OA\Property(property="full_name", type="string"),
 *     @OA\Property(property="job_title", type="string"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="ProfessionalProfileUpdateRequest",
 *     type="object",
 *     @OA\Property(property="full_name", type="string"),
 *     @OA\Property(property="job_title", type="string"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="ProfessionalProfileResponse",
 *     type="object",
 *     @OA\Property(property="data", ref="#/components/schemas/ProfessionalProfile")
 * )
 * @OA\Schema(
 *     schema="PersonalProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="birth_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="language", type="string", nullable=true),
 *     @OA\Property(property="education_level", type="string", enum={"none","primary","secondary","university"}, nullable=true),
 *     @OA\Property(property="gender", type="string", nullable=true),
 *     @OA\Property(property="commune", type="string", nullable=true),
 *     @OA\Property(property="cmu", type="boolean", nullable=true),
 *     @OA\Property(property="height", type="string", nullable=true),
 *     @OA\Property(property="occupation", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="PersonalProfileCreateRequest",
 *     type="object",
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="birth_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="language", type="string", nullable=true),
 *     @OA\Property(property="education_level", type="string", enum={"none","primary","secondary","university"}, nullable=true),
 *     @OA\Property(property="commune", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="PersonalProfileUpdateRequest",
 *     type="object",
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="birth_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="language", type="string", nullable=true),
 *     @OA\Property(property="education_level", type="string", enum={"none","primary","secondary","university"}, nullable=true),
 *     @OA\Property(property="commune", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="PersonalProfileResponse",
 *     type="object",
 *     @OA\Property(property="data", ref="#/components/schemas/PersonalProfile")
 * )
 * @OA\Schema(
 *     schema="MedicalProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="identity_id", type="string", format="uuid"),
 *     @OA\Property(property="cmu_number", type="string", nullable=true),
 *     @OA\Property(property="has_breast_cancer", type="boolean", nullable=true),
 *     @OA\Property(property="has_ovarian_cancer", type="boolean", nullable=true),
 *     @OA\Property(property="has_cervical_cancer", type="boolean", nullable=true),
 *     @OA\Property(property="has_other_cancer", type="boolean", nullable=true),
 *     @OA\Property(property="height", type="number", format="float", nullable=true),
 *     @OA\Property(property="weight", type="number", format="float", nullable=true),
 *     @OA\Property(property="blood_type", type="string", nullable=true),
 *     @OA\Property(property="allergies", type="string", nullable=true),
 *     @OA\Property(property="is_pregnant", type="boolean", nullable=true),
 *     @OA\Property(property="breastfeeding", type="boolean", nullable=true),
 *     @OA\Property(property="menopause_status", type="string", nullable=true),
 *     @OA\Property(property="family_cancer_history", type="boolean", nullable=true),
 *     @OA\Property(property="last_screening_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="screening_type", type="string", nullable=true),
 *     @OA\Property(property="first_sexual_intercourse_age", type="integer", nullable=true),
 *     @OA\Property(property="number_of_partners", type="integer", nullable=true),
 *     @OA\Property(property="number_of_pregnancies", type="integer", nullable=true),
 *     @OA\Property(property="number_of_abortions", type="integer", nullable=true),
 *     @OA\Property(property="number_of_children", type="integer", nullable=true),
 *     @OA\Property(property="exposed_to_smoke", type="boolean", nullable=true),
 *     @OA\Property(property="exposure_type", type="string", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * @OA\Schema(
 *     schema="MedicalProfileCreateRequest",
 *     type="object",
 *     @OA\Property(property="cmu_number", type="string", nullable=true),
 *     @OA\Property(property="height", type="number", format="float", nullable=true),
 *     @OA\Property(property="weight", type="number", format="float", nullable=true),
 *     @OA\Property(property="blood_type", type="string", nullable=true),
 *     @OA\Property(property="allergies", type="string", nullable=true),
 *     @OA\Property(property="is_pregnant", type="boolean", nullable=true),
 *     @OA\Property(property="breastfeeding", type="boolean", nullable=true),
 *     @OA\Property(property="menopause_status", type="string", nullable=true),
 *     @OA\Property(property="family_cancer_history", type="boolean", nullable=true),
 *     @OA\Property(property="last_screening_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="screening_type", type="string", nullable=true),
 *     @OA\Property(property="first_sexual_intercourse_age", type="integer", nullable=true),
 *     @OA\Property(property="number_of_partners", type="integer", nullable=true),
 *     @OA\Property(property="number_of_pregnancies", type="integer", nullable=true),
 *     @OA\Property(property="number_of_abortions", type="integer", nullable=true),
 *     @OA\Property(property="number_of_children", type="integer", nullable=true),
 *     @OA\Property(property="exposed_to_smoke", type="boolean", nullable=true),
 *     @OA\Property(property="exposure_type", type="string", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="MedicalProfileUpdateRequest",
 *     type="object",
 *     @OA\Property(property="cmu_number", type="string", nullable=true),
 *     @OA\Property(property="height", type="number", format="float", nullable=true),
 *     @OA\Property(property="weight", type="number", format="float", nullable=true),
 *     @OA\Property(property="blood_type", type="string", nullable=true),
 *     @OA\Property(property="allergies", type="string", nullable=true),
 *     @OA\Property(property="is_pregnant", type="boolean", nullable=true),
 *     @OA\Property(property="breastfeeding", type="boolean", nullable=true),
 *     @OA\Property(property="menopause_status", type="string", nullable=true),
 *     @OA\Property(property="family_cancer_history", type="boolean", nullable=true),
 *     @OA\Property(property="last_screening_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="screening_type", type="string", nullable=true),
 *     @OA\Property(property="first_sexual_intercourse_age", type="integer", nullable=true),
 *     @OA\Property(property="number_of_partners", type="integer", nullable=true),
 *     @OA\Property(property="number_of_pregnancies", type="integer", nullable=true),
 *     @OA\Property(property="number_of_abortions", type="integer", nullable=true),
 *     @OA\Property(property="number_of_children", type="integer", nullable=true),
 *     @OA\Property(property="exposed_to_smoke", type="boolean", nullable=true),
 *     @OA\Property(property="exposure_type", type="string", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true)
 * )
 * @OA\Schema(
 *     schema="MedicalProfileResponse",
 *     type="object",
 *     @OA\Property(property="data", ref="#/components/schemas/MedicalProfile")
 * )
 * @OA\Schema(
 *     schema="OrganizationCreateRequest",
 *     type="object",
 *     required={"name","type"},
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="OrganizationUpdateRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 * @OA\Schema(
 *     schema="OrganizationResponse",
 *     type="object",
 *     @OA\Property(property="organization", ref="#/components/schemas/Organization")
 * )
 * @OA\Schema(
 *     schema="OrganizationCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Organization")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 * @OA\Schema(
 *     schema="Ambassador",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_id", type="string", format="uuid"),
 *     @OA\Property(property="status", type="string", enum={"active","paused","exited"}),
 *     @OA\Property(property="activated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="deactivated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="AmbassadorResponse",
 *     type="object",
 *     @OA\Property(property="data", ref="#/components/schemas/Ambassador")
 * )
 * @OA\Schema(
 *     schema="AmbassadorResponseWithMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", ref="#/components/schemas/Ambassador")
 * )
 * @OA\Schema(
 *     schema="AmbassadorMetrics",
 *     type="object",
 *     @OA\Property(property="total_referrals", type="integer"),
 *     @OA\Property(property="active_referrals", type="integer"),
 *     @OA\Property(property="expired_referrals", type="integer"),
 *     @OA\Property(property="revoked_referrals", type="integer"),
 *     @OA\Property(property="started_usages", type="integer"),
 *     @OA\Property(property="completed_usages", type="integer"),
 *     @OA\Property(property="last_used_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="badges_earned", type="integer"),
 *     @OA\Property(property="level_code", type="string", enum={"starter","active","leader","champion"})
 * )
 * @OA\Schema(
 *     schema="AmbassadorMetricsResponse",
 *     type="object",
 *     @OA\Property(property="data", ref="#/components/schemas/AmbassadorMetrics")
 * )
 * @OA\Schema(
 *     schema="AmbassadorLevel",
 *     type="object",
 *     @OA\Property(property="ambassador_id", type="string", format="uuid"),
 *     @OA\Property(property="level_code", type="string", enum={"starter","active","leader","champion"}),
 *     @OA\Property(property="computed_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferral",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="ambassador_id", type="string", format="uuid"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="channel", type="string", enum={"whatsapp","sms","qr","other"}),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="revoked_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_used_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferralCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AmbassadorReferral"))
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferralResponseWithMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", ref="#/components/schemas/AmbassadorReferral")
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferralLinkResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="url", type="string"),
 *     @OA\Property(property="reused", type="boolean"),
 *     @OA\Property(property="remaining_weekly", type="integer", nullable=true),
 *     @OA\Property(property="data", ref="#/components/schemas/AmbassadorReferral")
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferralUsage",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="referral_id", type="string", format="uuid"),
 *     @OA\Property(property="event_type", type="string", enum={"started","completed"}),
 *     @OA\Property(property="source", type="string", nullable=true),
 *     @OA\Property(property="occurred_at", type="string", format="date-time")
 * )
 * @OA\Schema(
 *     schema="AmbassadorReferralUsageResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="usage", ref="#/components/schemas/AmbassadorReferralUsage")
 * )
 * @OA\Schema(
 *     schema="AmbassadorBadge",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="ambassador_id", type="string", format="uuid"),
 *     @OA\Property(property="badge_code", type="string"),
 *     @OA\Property(property="status", type="string", enum={"earned","revoked"}),
 *     @OA\Property(property="earned_at", type="string", format="date-time"),
 *     @OA\Property(property="revoked_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="evidence", type="object"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 * @OA\Schema(
 *     schema="AmbassadorBadgeCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AmbassadorBadge"))
 * )
 * @OA\Schema(
 *     schema="AmbassadorDashboardResponse",
 *     type="object",
 *     @OA\Property(property="ambassador", ref="#/components/schemas/Ambassador"),
 *     @OA\Property(property="metrics", ref="#/components/schemas/AmbassadorMetrics"),
 *     @OA\Property(property="badges", type="array", @OA\Items(ref="#/components/schemas/AmbassadorBadge")),
 *     @OA\Property(property="level", ref="#/components/schemas/AmbassadorLevel")
 * )
 * @OA\Schema(
 *     schema="AmbassadorRecomputeBadgesResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="badges", type="array", @OA\Items(ref="#/components/schemas/AmbassadorBadge"))
 * )
 * @OA\Schema(
 *     schema="AmbassadorRecomputeLevelResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="level", ref="#/components/schemas/AmbassadorLevel")
 * )
 * @OA\Schema(
 *     schema="ActivateAmbassadorRequest",
 *     type="object",
 *     required={"identity_id"},
 *     @OA\Property(
 *         property="identity_id",
 *         type="string",
 *         format="uuid",
 *         description="Identity to activate as ambassador",
 *         example="11111111-1111-1111-1111-111111111111"
 *     )
 * )
 * @OA\Schema(
 *     schema="DeactivateAmbassadorRequest",
 *     type="object",
 *     @OA\Property(property="reason", type="string"),
 *     @OA\Property(property="status", type="string", enum={"paused","exited"})
 * )
 * @OA\Schema(
 *     schema="GenerateReferralRequest",
 *     type="object",
 *     required={"channel"},
 *     @OA\Property(property="channel", type="string", enum={"whatsapp","sms","qr","other"}),
 * )
 * @OA\Schema(
 *     schema="GenerateReferralLinkRequest",
 *     type="object",
 *     required={"channel"},
 *     @OA\Property(property="channel", type="string", enum={"whatsapp","sms","qr","other"}),
 * )
 * @OA\Schema(
 *     schema="IdentityPanicLockResponse",
 *     type="object",
 *     @OA\Property(property="security_gate", ref="#/components/schemas/SecurityGate")
 * )
 * @OA\Schema(
 *     schema="IdentitySecurityStatusResponse",
 *     type="object",
 *     @OA\Property(property="security_gate", ref="#/components/schemas/SecurityGate")
 * )
 */
final class OpenApiSpec {}
