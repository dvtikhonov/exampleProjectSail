/**
 * Контракты match/classify для Briskly sync orchestrator.
 * Промпт system/user — только из PHP ComboCatalogPromptDto (ENUM + PromptBuilder).
 */

/** Готовый промпт из service-c ComboCatalogPromptDto::toArray(). */
export interface ComboCatalogPromptDto {
  system: string;
  user: string;
}

/** Source-линия VPS (как SourceMenuLineDto / API source-lines). */
export interface SourceMenuLine {
  line_key: string;
  type: 'single' | 'combo' | string;
  display_name: string;
  /** Decimal string; единственный источник цены для apply. */
  price: string;
  part_dish_ids?: number[];
}

/** Элемент snapshot Briskly (id+name+price для classify; LLM видит id+name). */
export interface BrisklySnapshotItem {
  id: number;
  name: string;
  price: number | string;
}

export interface MatchCandidate {
  id: number;
  name: string;
}

/**
 * Результат LLM по одной source-линии (OutputContract).
 * Любой price от LLM отбрасывается на парсинге.
 */
export interface MatchLineResult {
  line_key: string;
  display_name: string;
  compare_name: string;
  candidates: MatchCandidate[];
}

export interface PriceDiffItem {
  line_key: string;
  display_name: string;
  briskly_item_id: number;
  briskly_display_name: string;
  source_price: string;
  briskly_price: string;
}

export interface VpsOnlyCreateItem {
  line_key: string;
  display_name: string;
  source_price: string;
}

export interface SyncResultsSection<T> {
  items: T[];
  total: number;
  shown: number;
  truncated: boolean;
}

export interface SyncResultsCounts {
  skipped_briskly_only: number;
  ambiguous: number;
  equal_price: number;
}

/** Ответ sync-results (cap 25 на секцию, 1D/2B). */
export interface SyncResults {
  price_updates: SyncResultsSection<PriceDiffItem>;
  creates: SyncResultsSection<VpsOnlyCreateItem>;
  counts: SyncResultsCounts;
}

export interface ClassifyMatchInput {
  sourceLines: SourceMenuLine[];
  brisklySnapshot: BrisklySnapshotItem[];
  matchLines: MatchLineResult[];
  /** Лимит строк на секцию (по умолчанию 25). */
  cap?: number;
}

export interface MatchRunInput {
  /** Обязателен: system/user из PHP, без локальной копии ENUM. */
  prompt: ComboCatalogPromptDto;
  sourceLines: SourceMenuLine[];
  brisklySnapshot: BrisklySnapshotItem[];
  /** Offline/fixture: подменить Cursor SDK. */
  matcher?: (prompt: ComboCatalogPromptDto) => Promise<string>;
  cursorApiKey?: string;
  modelId?: string;
  cwd?: string;
  /** Подключить MCP food-source + briskly-target к Agent. */
  enableMcp?: boolean;
  mcp?: {
    foodSourceCommand?: string;
    foodSourceArgs?: string[];
    brisklyTargetCommand?: string;
    brisklyTargetArgs?: string[];
    env?: Record<string, string>;
  };
  sectionCap?: number;
}

export interface MatchRunOutput {
  matchLines: MatchLineResult[];
  syncResults: SyncResults;
  rawText: string;
  /** system, переданный в агент (= prompt.system из PHP). */
  systemUsed: string;
}

export interface ApplyApprovalPriceUpdate {
  line_key: string;
  apply: boolean;
}

export interface ApplyApprovalCreate {
  line_key: string;
  apply: boolean;
  briskly_category_id?: number;
}

export interface ApplyApprovals {
  price_updates: ApplyApprovalPriceUpdate[];
  creates: ApplyApprovalCreate[];
}

export interface ApplyRunInput {
  approvals: ApplyApprovals;
  /** Proposals после match (с server source_price). */
  priceUpdates: PriceDiffItem[];
  creates: VpsOnlyCreateItem[];
  /** Каталог create (обязателен для отмеченных creates). */
  defaultCatalogId?: number;
  dryRun?: boolean;
  token: string;
  baseUrl?: string;
}

export interface ApplyRunReport {
  updated: number;
  created: number;
  skipped_unchecked: number;
  skipped_equal: number;
  errors: Array<{ line_key: string; message: string }>;
  dry_run: boolean;
}
