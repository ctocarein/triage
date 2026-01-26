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
 *     @OA\Property(property="role", type="string"),
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
 *     @OA\Property(property="bloc_keys", type="array", @OA\Items(type="string"))
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
 *     required={"credential_type","identifier","secret"},
 *     @OA\Property(property="kind", type="string", example="person"),
 *     @OA\Property(property="credential_type", type="string", example="phone"),
 *     @OA\Property(property="identifier", type="string", example="+243000000000"),
 *     @OA\Property(property="secret", type="string", example="1234"),
 *     @OA\Property(property="role", type="string", example="Beneficiary"),
 *     @OA\Property(property="organization_id", type="string", format="uuid")
 * )
 * @OA\Schema(
 *     schema="IdentityRegisterResponse",
 *     type="object",
 *     @OA\Property(property="token", type="string"),
 *     @OA\Property(property="identity", ref="#/components/schemas/Identity"),
 *     @OA\Property(property="credential", ref="#/components/schemas/Credential"),
 *     @OA\Property(property="membership", ref="#/components/schemas/Membership")
 * )
 * @OA\Schema(
 *     schema="IdentityLoginRequest",
 *     type="object",
 *     required={"credential_type","identifier","secret"},
 *     @OA\Property(property="credential_type", type="string", example="email"),
 *     @OA\Property(property="identifier", type="string", example="user@example.com"),
 *     @OA\Property(property="secret", type="string", example="secret123")
 * )
 * @OA\Schema(
 *     schema="IdentityLoginResponse",
 *     type="object",
 *     @OA\Property(property="token", type="string"),
 *     @OA\Property(property="identity", ref="#/components/schemas/Identity")
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
 *     @OA\Property(property="role", type="string"),
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
 */
final class OpenApiSpec
{
}
