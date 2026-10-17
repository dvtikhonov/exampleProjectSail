<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunServiceInterface;
use App\Modules\SchedulePhoto\Http\Requests\CompleteSchedulePhotoOcrRequest;
use Illuminate\Http\JsonResponse;

/**
 * Internal колбэк sidecar после vision OCR.
 */
class InternalSchedulePhotoOcrController extends Controller
{
    public function __construct(
        private readonly SchedulePhotoRunServiceInterface $runs,
    ) {}

    /**
     * POST /api/food/internal/schedule-photo/ocr-complete.
     */
    public function complete(CompleteSchedulePhotoOcrRequest $request): JsonResponse
    {
        $run = $this->runs->completeOcr($request->toCallbackDto());

        return response()->json([
            'ok' => true,
            'run' => $run->toArray(),
        ]);
    }
}
