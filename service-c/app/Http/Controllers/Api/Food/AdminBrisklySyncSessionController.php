<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklySyncApprovalsDto;
use App\DTO\Food\BrisklySync\CreateBrisklySyncSessionDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\CreateBrisklySyncSessionRequest;
use App\Http\Requests\Food\Admin\MatchBrisklySyncSessionRequest;
use App\Http\Requests\Food\Admin\UpdateBrisklySyncApprovalsRequest;
use App\Models\Max\MaxUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin API сессий синхронизации Briskly (роль max_manager).
 */
class AdminBrisklySyncSessionController extends Controller
{
    public function __construct(
        private readonly BrisklySyncSessionServiceInterface $sessions,
    ) {}

    /**
     * POST /sessions — создать сессию (токен только в cache).
     */
    public function store(CreateBrisklySyncSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $createdBy = $user instanceof MaxUser ? (int) $user->max_user_id : null;

        $session = $this->sessions->createSession(new CreateBrisklySyncSessionDto(
            restaurantId: $request->restaurantId(),
            brisklyToken: $request->brisklyToken(),
            vpsCategoryId: $request->vpsCategoryId(),
            searchText: $request->searchText(),
            clarification: $request->clarification(),
            createdByMaxUserId: $createdBy,
        ));

        return response()->json([
            'session' => $session->toMetaArray(),
        ], 201);
    }

    /**
     * GET /sessions/{id} — мета без token.
     */
    public function show(string $session): JsonResponse
    {
        $record = $this->sessions->getSession($session);

        return response()->json([
            'session' => $record->toMetaArray(),
        ]);
    }

    /**
     * GET /sessions/{id}/source-lines — для MCP food-source.
     */
    public function sourceLines(string $session): JsonResponse
    {
        $lines = $this->sessions->sourceLines($session);

        return response()->json([
            'source_lines' => array_map(
                static fn (SourceMenuLineDto $line): array => $line->toArray(),
                $lines,
            ),
        ]);
    }

    /**
     * POST /sessions/{id}/snapshot — загрузка Briskly snapshot.
     */
    public function snapshot(string $session): JsonResponse
    {
        $record = $this->sessions->loadSnapshot($session);

        return response()->json([
            'session' => $record->toMetaArray(),
            'snapshot_count' => is_array($record->brisklySnapshot)
                ? count($record->brisklySnapshot)
                : 0,
        ]);
    }

    /**
     * POST /sessions/{id}/match — Cursor match + классификация.
     */
    public function match(MatchBrisklySyncSessionRequest $request, string $session): JsonResponse
    {
        $record = $this->sessions->match($session, $request->rematch());

        return response()->json([
            'session' => $record->toMetaArray(),
        ]);
    }

    /**
     * GET /sessions/{id}/sync-results — ≤25+25 без token.
     */
    public function syncResults(string $session): JsonResponse
    {
        $results = $this->sessions->syncResults($session);

        return response()->json($results->toArray());
    }

    /**
     * PUT /sessions/{id}/approvals — галочки update/create.
     */
    public function approvals(UpdateBrisklySyncApprovalsRequest $request, string $session): JsonResponse
    {
        $record = $this->sessions->updateApprovals(
            $session,
            BrisklySyncApprovalsDto::fromArray($request->approvalsPayload()),
        );

        return response()->json([
            'session' => $record->toMetaArray(),
        ]);
    }

    /**
     * POST /sessions/{id}/apply — запись в Briskly.
     */
    public function apply(string $session): JsonResponse
    {
        $report = $this->sessions->apply($session);

        return response()->json([
            'report' => $report->toArray(),
        ]);
    }

    /**
     * GET /sessions/{id}/briskly-categories — категории для CREATE.
     * Также alias GET /briskly/categories?session_id=.
     */
    public function categories(Request $request, ?string $session = null): JsonResponse
    {
        $sessionId = $session ?? (string) $request->query('session_id', '');
        if ($sessionId === '') {
            return response()->json([
                'message' => 'Укажите session_id.',
            ], 422);
        }

        $categories = $this->sessions->listBrisklyCategories($sessionId);

        return response()->json([
            'categories' => array_map(
                static fn (BrisklyCategoryDto $category): array => $category->toArray(),
                $categories,
            ),
        ]);
    }
}
