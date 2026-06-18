/** Candidate organization from Yandex Maps search or direct URL. */
export interface OrganizationCandidate {
  org_id: string;
  name: string;
  address: string;
  average_rating: number | null;
  reviews_count: number | null;
  ratings_count: number | null;
  canonical_url: string;
}

/** Organization metadata from reviews page. */
export interface OrganizationMeta {
  org_id: string;
  name: string;
  address: string;
  average_rating: number | null;
  reviews_count: number | null;
  ratings_count: number | null;
  canonical_url: string;
}

/** Parsed review from Yandex Maps. */
export interface ParsedReview {
  external_id: string;
  author_name: string;
  published_at: string | null;
  text: string | null;
  rating: number | null;
}

export interface ResolveRequestBody {
  url: string;
}

/** Raw DOM snippet harvested from a search result or org card link. */
export interface DomOrgHarvest {
  href: string;
  link_text: string;
  card_text: string;
  rating_aria_label: string;
  meta_text: string;
}

/** Page-level metadata harvested without business interpretation. */
export interface PageMeta {
  title: string;
  header_text: string;
  address_text: string;
}

/** Raw collect payload returned by POST /resolve for Laravel-side parsing. */
export interface ResolveCollectResponseBody {
  resolved_url: string;
  is_direct_org: boolean;
  direct_org_id: string | null;
  network_payloads: unknown[];
  dom_harvest: DomOrgHarvest[];
  page_meta: PageMeta;
}

/** @deprecated Use ResolveCollectResponseBody. Kept for transitional references. */
export type ResolveResponseBody = ResolveCollectResponseBody;

export interface SyncReviewsRequestBody {
  org_id: string;
  canonical_url: string;
  stop_anchors?: string[];
}

export interface SyncReviewsResponseBody {
  org: OrganizationMeta;
  reviews: ParsedReview[];
}

export interface ApiErrorBody {
  error: string;
  message: string;
}
