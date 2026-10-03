import type { ComboCatalogPromptDto } from './types.js';

/**
 * Собирает текст для Cursor Agent.send из PHP ComboCatalogPromptDto.
 * Node **не** хранит и не дополняет system ENUM-текстом — только передаёт DTO.
 */
export function composeAgentPrompt(prompt: ComboCatalogPromptDto): string {
  assertPhpPromptDto(prompt);
  return `${prompt.system.trim()}\n\n---\n\n${prompt.user.trim()}`;
}

/**
 * Проверяет, что payload пришёл из PHP (или fixture с тем же контрактом),
 * а не является пустой заглушкой.
 */
export function assertPhpPromptDto(prompt: ComboCatalogPromptDto): void {
  if (!prompt || typeof prompt !== 'object') {
    throw new Error('ComboCatalogPromptDto required (from PHP PromptBuilder)');
  }
  if (typeof prompt.system !== 'string' || prompt.system.trim() === '') {
    throw new Error('ComboCatalogPromptDto.system is required (ENUM sections from PHP)');
  }
  if (typeof prompt.user !== 'string' || prompt.user.trim() === '') {
    throw new Error('ComboCatalogPromptDto.user is required (built by PHP PromptBuilder)');
  }
}

/**
 * Маркер того, что system похож на ENUM MatchNames (не локальный хардкод Node).
 * Используется в тестах fixture; не дублирует полный текст секций.
 */
export function looksLikeEnumMatchNamesSystem(system: string): boolean {
  const markers = [
    'сопоставляешь наименования позиций source-каталога VPS',
    'compare_name',
    'Не включай price в ответ LLM',
  ];
  return markers.every((m) => system.includes(m));
}
