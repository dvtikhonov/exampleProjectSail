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

export interface ResolveResponseBody {
  resolved_url: string;
  candidates: OrganizationCandidate[];
}

export interface SyncReviewsRequestBody {
  org_id: string;
  canonical_url: string;
}

export interface SyncReviewsResponseBody {
  org: OrganizationMeta;
  reviews: ParsedReview[];
}

export interface ApiErrorBody {
  error: string;
  message: string;
}
