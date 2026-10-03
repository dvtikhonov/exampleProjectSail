export type { BrisklyClientConfig } from './config.js';
export { DEFAULT_BRISKLY_BASE_URL, resolveClientConfig } from './config.js';
export { BrisklyHttpClient } from './client/BrisklyHttpClient.js';
export { buildCreateItemPayload } from './client/buildCreatePayload.js';
export { buildUpdatePricePayload } from './client/buildUpdatePayload.js';
export {
  buildItemGetListParams,
  buildQueryString,
  matchesSearchText,
} from './client/query.js';
export type {
  BrisklyCategory,
  BrisklyCategoryListResponse,
  BrisklyCreateItemPayload,
  BrisklyItemDetails,
  BrisklyListItem,
  BrisklyListResponse,
  BrisklyUpdateItemPayload,
  CreateItemInput,
  ListItemsOptions,
  SearchItemsOptions,
  SearchItemsResult,
  SnapshotOptions,
  UpdatePriceDryRunResult,
} from './types.js';
export { BrisklyApiError } from './types.js';

export { SECTION_RESULT_CAP } from './orchestrator/constants.js';
export {
  assertPhpPromptDto,
  composeAgentPrompt,
  looksLikeEnumMatchNamesSystem,
} from './orchestrator/buildMatchPrompt.js';
export { parseMatchJson } from './orchestrator/parseMatchJson.js';
export { classifyMatchResults } from './orchestrator/classifyMatchResults.js';
export { PriceDiffBuilder, buildPriceDiffItem } from './orchestrator/PriceDiffBuilder.js';
export {
  VpsOnlyCreateBuilder,
  buildVpsOnlyCreateItem,
} from './orchestrator/VpsOnlyCreateBuilder.js';
export { runMatch } from './orchestrator/runMatch.js';
export { runApply } from './orchestrator/runApply.js';
export { buildMatchMcpServers } from './orchestrator/mcpConfig.js';
export { normalizePrice, pricesEqual } from './orchestrator/price.js';
export type {
  ApplyApprovals,
  ApplyRunInput,
  ApplyRunReport,
  BrisklySnapshotItem,
  ClassifyMatchInput,
  ComboCatalogPromptDto,
  MatchCandidate,
  MatchLineResult,
  MatchRunInput,
  MatchRunOutput,
  PriceDiffItem,
  SourceMenuLine,
  SyncResults,
  VpsOnlyCreateItem,
} from './orchestrator/types.js';

export { createFoodSourceServer } from './mcp/foodSource/createFoodSourceServer.js';
export {
  FoodSourceHttpClient,
  resolveFoodSourceConfigFromEnv,
} from './mcp/foodSource/foodSourceHttpClient.js';
export { createBrisklyTargetServer } from './mcp/brisklyTarget/createBrisklyTargetServer.js';
