<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\UploadStoreRequest;
use App\Domains\Evaluation\Models\Upload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

class UploadsController extends Controller
{
    /**
     * @OA\Post(
     *     path="/uploads",
     *     operationId="evaluationUpload",
     *     summary="Upload a file for evaluation answers",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Upload created", @OA\JsonContent(ref="#/components/schemas/UploadResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(UploadStoreRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('uploads/evaluations', 'public');
        $fileId = (string) Str::uuid();

        $upload = Upload::create([
            'file_id' => $fileId,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'identity_id' => $request->user()?->id,
        ]);

        return response()->json([
            'file_id' => $upload->file_id,
            'upload' => [
                'file_id' => $upload->file_id,
                'original_name' => $upload->original_name,
                'mime_type' => $upload->mime_type,
                'size' => $upload->size,
            ],
        ], 201);
    }
}
