# Журнал SOLID-рефакторинга service-j

## Попытка 1 — 2026-10-27

### Оценка
- SRP: нарушение. `SalesOutletsController.index` сам вызывал `Validator` и собирал тело 422, хотя формат ошибок уже был обязанностью `ApiExceptionHandler`.
- OCP: нарушение. Бейдж в `SalesOutletsCell.vue` выбирал классы по кодам `blocked` / `review` / `approved`, хотя API уже отдаёт `row_tone`, и строка таблицы красится по нему. Два типа column filter в `JpaSalesOutletQueryApplicator` стратегией не закрывались: ветка ещё не разрослась.
- LSP: соблюдено. `findById` не возвращает удалённые, `save` бросает `SalesOutletNotFoundException`, если активной точки нет.
- ISP: соблюдено. Порты короткие, клиенты используют их методы.
- DIP: соблюдено. Domain и application не зависят от Spring, JPA, infrastructure и Servlet API.
- validation: соблюдено по месту (граница api, до сервиса). Сборка карты ошибок была отдельным SRP-нарушением, не дырой в проверке.
- layering: нарушение. Сопоставление подстроки метки статуса жило в `JpaSalesOutletQueryApplicator`, а не в `SalesOutletStatus`.

Рекомендации (по убыванию пользы):
- R1-1, layering, `SalesOutletStatus.java`, `SalesOutletStatusTest.java`, `JpaSalesOutletQueryApplicator.java`. Правило совпадения метки статуса убрать из SQL-класса в enum.
- R1-2, SRP, `SalesOutletsController.java`, `ApiExceptionHandler.java`, `ApplicationConfig.java`, новый резолвер в api. Контроллер не собирает 422.
- R1-3, OCP, `SalesOutletsCell.vue`. Бейдж по `row_tone`.

Отложено, не рекомендация: стратегия на два типа column filter; вынос `toMap()` из доменных DTO; строгая 422-валидация `page`/`per_page` (сменила бы контракт).

### Субагенты
- R1-1: закрыт. `valuesByLabelSubstring` на `SalesOutletStatus`. Аппликатор только подставляет готовый список в `IN` для колонок с типом `FILTER_STATUS_LABEL`. Файлы: `SalesOutletStatus.java`, `SalesOutletStatusTest.java`, `JpaSalesOutletQueryApplicator.java`. Тест субагента: `mvn -pl sales-outlets-domain,sales-outlets-infrastructure -am test -DskipITs` в образе Maven, BUILD SUCCESS, 21 тест domain. Риск: в infrastructure нет теста, который исполняет SQL `IN`.
- R1-2: закрыт. `IndexSalesOutletsRequestResolver` проверяет query до контроллера и бросает `ConstraintViolationException`. Тело 422 собирает `ApiExceptionHandler`. В контроллере нет `Validator`. Файлы: `SalesOutletsController.java`, `ApiExceptionHandler.java`, `ApplicationConfig.java`, `IndexSalesOutletsRequestResolver.java`. Сборка `mvn -pl sales-outlets-api -am test -DskipITs -DskipTests`, BUILD SUCCESS, тесты пропущены. Риск на момент субагента: MockMvc 422 ещё не гонялся.
- R1-3: закрыт. Бейдж смотрит на `row.row_tone` (`danger`/`warning`/`success`), сравнений с кодами статуса нет. Файл: `SalesOutletsCell.vue`. Юнит-тестов нет, проверка — поиск по файлу. Риск: строка без `row_tone` без цветного фона; в браузере не смотрели.

### Проверка
- Команда: `./scripts/test-services.sh service-j --no-prepare`
- Итог: зелёный. `BUILD SUCCESS`. Domain 21, application 7, api 16, падений нет. В том числе проверка 422 index.

### Итог попытки
- Закрыто: R1-1, R1-2, R1-3.
- Осталось: повторная оценка на попытке 2.
- Стоп: нет.

## Попытка 2 — 2026-10-27

### Оценка
- SRP: нарушение. Правило вида головной организации (код↔метка, префикс «ООО ») было скопировано в `SalesOutletEditModal.vue`, `SalesOutletsTable.vue` и `HeadOrganizationPoptip.vue`.
- OCP: нарушение. Модалка редактирования держала свой список `approved` / `review` / `blocked`, хотя `meta.status_options` уже приходит с API.
- LSP: соблюдено.
- ISP: соблюдено. Порты не толстые.
- DIP: соблюдено. Spring и JPA остаются в api и infrastructure.
- validation: соблюдено. GET index проверяет `IndexSalesOutletsRequestResolver` до use-case, 422 собирает `ApiExceptionHandler`.
- layering: соблюдено. Сопоставление метки статуса на `SalesOutletStatus.valuesByLabelSubstring`. JPA-сущность только в infrastructure.

Рекомендации:
- R2-1, SRP, три Vue-компонента и новый `frontend/src/domain/headOrganizationKind.js`. Один словарь и разбор префикса.
- R2-2, OCP, `SalesOutletEditModal.vue`, `SalesOutletsIndex.vue`. Статусы формы из `meta.status_options` без пункта с пустым value.

Отложено: стратегия на два типа column filter; `switch` из четырёх имён полей в `ApiExceptionHandler`; отдельный HTTP-клиент таблицы. Это не текущие нарушения, которые уже пора закрывать.

### Субагенты
- R2-1: закрыт. Модуль `headOrganizationKind.js`. Компоненты вызывают его. В API по-прежнему уходят метки ООО/ИП/АО/СПК. Неизвестный вид в таблице даёт пустую строку, в формах — ООО. Субагент сверил 95 случаев со старым правилом и скомпилировал три SFC. Риск: браузерный сценарий не прогонялся.
- R2-2: закрыт, после R2-1 (общий файл модалки). Селект статуса берёт проп `statusOptions`. Страница отфильтровывает пустой value. Литералов статусов в модалке нет. Риск: пока `status_options` не пришли, селект пуст.

### Проверка
- Команда: `./scripts/test-services.sh service-j --no-prepare`
- Итог: зелёный. `BUILD SUCCESS`. Domain 21, application 7, api 16, падений нет.
- Браузер `http://localhost:5174/objects-sales-outlets-4/` из инструмента не открылся: connection reset. Поведение Vue тестами Maven не покрыто.

### Итог попытки
- Закрыто: R2-1, R2-2.
- Осталось: повторная оценка на попытке 3.
- Стоп: нет.

## Попытка 3 — 2026-10-27

### Оценка
- SRP: соблюдено. Контроллер не собирает ошибки. Правило вида ГО в одном модуле. Use-case только грузит агрегат, применяет изменение и сохраняет.
- OCP: соблюдено. Бейдж идёт по `row_tone`, статусы формы — из `status_options`. Два типа column filter и четыре имени поля в обработчике ошибок не разрослись до стратегии.
- LSP: соблюдено. Контракты `findById` / `save` / soft delete те же.
- ISP: соблюдено.
- DIP: соблюдено. Domain и application без Spring, JPA и Servlet API.
- validation: соблюдено на границе api.
- layering: соблюдено. Доменные правила на `SalesOutlet` и enum. Entity только в infrastructure.

Рекомендации: нет.

### Субагенты
- нет

### Проверка
- Новых правок нет. Последний прогон попытки 2 зелёный.

### Итог попытки
- Закрыто: нечего закрывать.
- Осталось: нет.
- Стоп: рекомендаций 0.
