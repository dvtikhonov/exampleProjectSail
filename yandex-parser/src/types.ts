/**
 * Типы контракта HTTP API и DTO парсера.
 * Resolve отдаёт сырые данные; Sync — уже нормализованные org + reviews.
 */
/** Кандидат организации из поиска Яндекс.Карт или прямой ссылки. */
export interface OrganizationCandidate {
  org_id: string;
  name: string;
  address: string;
  average_rating: number | null;
  reviews_count: number | null;
  ratings_count: number | null;
  canonical_url: string;
}

/** Метаданные организации со страницы отзывов. */
export interface OrganizationMeta {
  org_id: string;
  name: string;
  address: string;
  average_rating: number | null;
  reviews_count: number | null;
  ratings_count: number | null;
  canonical_url: string;
}

/** Распарсенный отзыв. */
export interface ParsedReview {
  external_id: string;
  author_name: string;
  published_at: string | null;
  text: string | null;
  rating: number | null;
}

export interface ResolveRequestBody {
  /** URL поиска или прямой карточки на yandex.ru/maps. */
  url: string;
}

/** Сырой фрагмент DOM из сниппета поиска или ссылки на карточку. */
export interface DomOrgHarvest {
  href: string;
  link_text: string;
  card_text: string;
  rating_aria_label: string;
  meta_text: string;
}

/** Метаданные страницы без бизнес-интерпретации (title, заголовок, адрес). */
export interface PageMeta {
  title: string;
  header_text: string;
  address_text: string;
}

/** Ответ POST /resolve — Laravel сам выбирает org_id из dom_harvest и network_payloads. */
export interface ResolveCollectResponseBody {
  resolved_url: string;
  is_direct_org: boolean;
  direct_org_id: string | null;
  network_payloads: unknown[];
  dom_harvest: DomOrgHarvest[];
  page_meta: PageMeta;
}

/** @deprecated Используйте ResolveCollectResponseBody. */
export type ResolveResponseBody = ResolveCollectResponseBody;

export interface SyncReviewsRequestBody {
  org_id: string;
  canonical_url: string;
  /** external_id уже сохранённых отзывов для инкрементальной синхронизации. */
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
