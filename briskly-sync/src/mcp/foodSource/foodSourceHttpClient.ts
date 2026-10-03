/**
 * HTTP-клиент MCP food-source → service-c admin source-lines / session endpoint.
 * Токен админки не логируется.
 */

export interface FoodSourceClientConfig {
  baseUrl: string;
  authToken: string;
  fetchImpl?: typeof fetch;
}

export interface ListSourceLinesParams {
  /** Session id (Part 4); если задан — приоритет над query filters. */
  sessionId?: string | null;
  restaurantId?: number | null;
  vpsCategoryId?: number | null;
  searchText?: string | null;
}

export class FoodSourceHttpClient {
  private readonly baseUrl: string;
  private readonly authToken: string;
  private readonly fetchImpl: typeof fetch;

  constructor(config: FoodSourceClientConfig) {
    this.baseUrl = config.baseUrl.replace(/\/+$/, '');
    this.authToken = config.authToken;
    this.fetchImpl = config.fetchImpl ?? fetch;
  }

  async listSourceLines(params: ListSourceLinesParams): Promise<unknown> {
    const sessionId = params.sessionId?.trim();
    if (sessionId) {
      return this.getJson(`/sessions/${encodeURIComponent(sessionId)}/source-lines`);
    }

    const restaurantId = params.restaurantId;
    if (restaurantId == null || !Number.isFinite(Number(restaurantId))) {
      throw new Error('restaurant_id or session_id is required for list_source_lines');
    }

    const qs = new URLSearchParams();
    qs.set('restaurant_id', String(restaurantId));
    if (params.vpsCategoryId != null) {
      qs.set('vps_category_id', String(params.vpsCategoryId));
    }
    if (params.searchText?.trim()) {
      qs.set('search_text', params.searchText.trim());
    }

    return this.getJson(`/source-lines?${qs.toString()}`);
  }

  private async getJson(path: string): Promise<unknown> {
    const url = `${this.baseUrl}${path.startsWith('/') ? path : `/${path}`}`;
    const response = await this.fetchImpl(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${this.authToken}`,
      },
    });

    if (!response.ok) {
      throw new Error(`food-source HTTP ${response.status} for ${path.split('?')[0]}`);
    }

    return response.json();
  }
}

export function resolveFoodSourceConfigFromEnv(
  env: NodeJS.ProcessEnv = process.env,
): FoodSourceClientConfig {
  const baseUrl = (env.FOOD_SOURCE_BASE_URL ?? '').trim();
  const authToken = (env.FOOD_SOURCE_AUTH_TOKEN ?? '').trim();
  if (!baseUrl) {
    throw new Error('FOOD_SOURCE_BASE_URL is required for food-source MCP');
  }
  if (!authToken) {
    throw new Error('FOOD_SOURCE_AUTH_TOKEN is required for food-source MCP');
  }
  return { baseUrl, authToken };
}
