<?php

declare(strict_types=1);

namespace App\Enums\Food\ComboCatalog;

/**
 * Фиксированные секции system-промпта для матчинга combo/каталога.
 */
enum ComboCatalogPromptSection: string
{
    case SystemRole = 'system_role';
    case ComboCartesianRules = 'combo_cartesian_rules';
    case WeightRules = 'weight_rules';
    case NamingRules = 'naming_rules';
    case OutputContract = 'output_contract';

    /**
     * Русский текст секции для склейки system-промпта.
     */
    public function text(): string
    {
        return match ($this) {
            self::SystemRole => <<<'TXT'
Ты сопоставляешь наименования позиций source-каталога VPS (A) с позициями каталога Briskly.
Цены бери только из данных source; не считай и не выдумывай цены.
Не выдумывай блюда и не добавляй позиции, которых нет во входных списках.
TXT,
            self::ComboCartesianRules => <<<'TXT'
Комбо в source — unordered-декартово произведение блюд из разных combo-категорий ресторана.
Пара A/B и B/A — одна и та же позиция; дубликат B/A не рассматривай.
Имя комбо имеет вид «A / B»; цена комбо = сумма цен частей (ты цену не считаешь — только понимаешь формат имён).
TXT,
            self::WeightRules => <<<'TXT'
В контексте промпта вес указан только в каноническом виде: «N грамм», «N килограмм», «N миллилитр», «N литр».
Короткие формы «120г», «120 г», «120 г.», «120гр» означают то же, что «120 грамм»; аналогично для кг/мл/л.
При сравнении имён учитывай эквивалентность этих форм веса.
TXT,
            self::NamingRules => <<<'TXT'
Отображаемое имя (display_name) комбо — «A / B».
Сначала примени базовую нормализацию: ё/е, регистр, кавычки, лишние пробелы.
Если clarification — правило ИСКЛЮЧЕНИЯ (например «без шубы», «только без X», «исключить X»):
не сопоставляй позиции, которые нарушают правило; для таких source candidates = [];
позиции Briskly с исключаемым признаком не включай в candidates ни для одной линии;
запрещено вырезать исключаемое слово из compare_name и всё равно сматчить такую пару.
Если clarification — нормализация имён (игнорировать вес/скобки и т.п.):
примени правило к display_name source и к именам Briskly — получи compare_name, сравнивай только по compare_name.
Если clarification нет или это маркер «дополнительных уточнений нет» — сравнивай по compare_name после базовой нормализации.
TXT,
            self::OutputContract => <<<'TXT'
Ответ — только JSON без пояснений. Для каждой source-позиции укажи:
line_key, display_name, compare_name, candidates (массив объектов с id и name из snapshot Briskly).
Не включай price в ответ LLM: цены задаёт только source на сервере.
TXT,
        };
    }
}
