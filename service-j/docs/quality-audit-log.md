# Журнал аудита качества service-j

Категории: security, architecture, transactions, jpa, config.
Закрытые пункты SOLID не поднимались заново, если текущий код их не нарушает.

## Попытка 1 — 2026-10-28

### Оценка
- security: нарушение. В основном `application.yml` пароль БД `12345678AS` и JDBC URL с `useSSL=false` и `allowPublicKeyRetrieval=true` подставляются без обязательного env. `RestClientMessengerChoiceVisitLogger` делает POST на `URI.create(logUrl)` без ограничения хоста и схемы и отправляет туда `X-Messenger-Choice-Token`.
- architecture: соблюдено. Domain и application без Spring, JPA и Servlet. Entity только в infrastructure. `messenger-choice` не зависит от sales-outlets.
- transactions: нарушение. `update`, `updateHeadOrganization` и `delete` читают агрегат и пишут его разными вызовами репозитория. `save` перезаписывает снимок без блокировки и без версии, поэтому возможны потеря чужого обновления и запись `deleted_at = null` поверх уже удалённой строки. Классовый `@Transactional` на `JpaSalesOutletRepository` открывает read-write транзакцию на `findById` и `paginate`.
- jpa: соблюдено. `ddl-auto: none`, `open-in-view: false`, `show-sql: false`. `per_page` ограничен 5..50. Soft-delete есть на чтении и на `save`. Сортировка и фильтры идут в параметр Criteria через allow-list колонок.
- config: соблюдено. Тестовый профиль ходит в `sail_db_testing`. Драйвер MySQL — `runtime`. Лишней зависимости domain/application на Spring нет.

Рекомендации:
- R1-1, security, `application.yml`. Убрать из основного профиля дефолтный пароль и JDBC-флаги `useSSL=false` / `allowPublicKeyRetrieval=true`, оставив URL и пароль только из env.
- R1-2, security, логгер визита и его свойства. POST только на http(s) хост из allow-list, по умолчанию `service-c`; чужой хост и токен не отправлять.
- R1-3, transactions, порт репозитория, JPA-адаптер и `DefaultSalesOutletService`. Запись update/head-organization/delete одной операцией с `SELECT … FOR UPDATE`; чтения репозитория — `readOnly`.

Отложено (не рекомендация): доверие `TrustGatewayAuthFilter` к `X-User-Id` при опубликованном порте — закрытие требует секрета и правки nginx вне `service-j` и ломает текущий вызов gateway. `JpaGatewayUserResolver` может вставлять пользователя, отдельная read-only транзакция на короткий SELECT по первичному ключу блокировку не удлиняет. `:is` в `SalesOutletsCell.vue` уже смотрит в allow-list компонентов. Путь сортировки берётся из `SalesOutletColumns`, не из строки запроса.

### Субагенты
- R1-1: закрыт. `application.yml`: `url` и `password` только из env, строк `12345678AS`, `useSSL=false` и `allowPublicKeyRetrieval` в основном профиле нет. `application-test.yml` не менялся. Проверено чтением файла. Остаточный риск: те же значения по-прежнему может подставить `docker-compose.yml` и `service-j/.env.example`, их не трогали.
- R1-2: закрыт. POST только на http/https без userinfo и только на хост из `messenger.choice.allowed-hosts` (дефолт `service-c`). Чужой хост не вызывается, токен в лог не пишется. Файлы: `RestClientMessengerChoiceVisitLogger.java`, `MessengerChoiceProperties.java`, `MessengerChoiceConfiguration.java`, `RestClientMessengerChoiceVisitLoggerTest.java`, дефолт списка в `application.yml`. Проверка субагента: `mvn -B -pl messenger-choice test` в образе Maven, BUILD SUCCESS, 15 тестов. Остаточный риск: порт и путь на разрешённом хосте не зафиксированы, проверяется имя хоста, а не адрес после DNS.
- R1-3: закрыт. `SalesOutletRepository.modify` выполняется в одной транзакции: `findActiveByIdForUpdate` (`PESSIMISTIC_WRITE`, `deleted_at IS NULL`), правка агрегата, `copyOnto`. Сервис на update, head-organization и delete вызывает только `modify`. `findById` и `paginate` — `@Transactional(readOnly = true)`. Файлы: порт, `SalesOutletSpringDataRepository`, `JpaSalesOutletRepository`, `DefaultSalesOutletService`, `DefaultSalesOutletServiceTest`. Проверка субагента: `mvn -B test -pl sales-outlets-domain,sales-outlets-application,sales-outlets-infrastructure -am`, BUILD SUCCESS (domain 21, application 7). Остаточный риск: `save` по-прежнему без `FOR UPDATE`, но use-case его не вызывает. `SELECT … FOR UPDATE` этим прогоном на MySQL не исполнялся.

### Проверка
- Команда: `./scripts/test-services.sh service-j --no-prepare`
- Итог: BUILD SUCCESS. Domain, application, infrastructure, messenger-choice (15), api (16). Падений нет.

### Итог попытки
- Закрыто: R1-1, R1-2, R1-3.
- Осталось: повторная оценка на попытке 2.
- Стоп: нет.

## Попытка 2 — 2026-10-28

### Оценка
- security: соблюдено. Основной профиль не содержит пароля и флагов `useSSL=false` / `allowPublicKeyRetrieval`. Лог визита не уходит на произвольный хост: схема http/https, без userinfo, хост из allow-list. Bean Validation на index, update и head-organization на месте. `v-html` / `eval` нет, `:is` ограничен картой компонентов.
- architecture: соблюдено. В domain и application нет импортов Spring и JPA. Запись идёт через порт, правило остаётся на агрегате.
- transactions: соблюдено. Три записи use-case идут через `modify` с блокировкой активной строки в одной транзакции адаптера. Чтения списка и `findById` — read-only. `@Transactional` стоит на методах infrastructure-бина, который Spring отдаёт как порт; сервис вызывает его снаружи, self-invocation нет.
- jpa: соблюдено. `ddl-auto: none`, `open-in-view: false`. Страница ограничена. Условие `deletedAt IS NULL` есть и в блокирующем запросе.
- config: соблюдено. Тестовый профиль по-прежнему на `sail_db_testing`. В domain/application лишних зависимостей нет.

Рекомендации: нет.

Отложено (не рекомендация): `save` без `FOR UPDATE` — метод порта остался, use-case его не вызывает. Порт и путь лога на хосте `service-c` не сужены; URL задаёт оператор, не запрос. Доверие к `X-User-Id` и пароль в compose / `.env.example` — вне правки, которая сохраняет контракт gateway и границу каталога `service-j`. `useSSL=false` в тестовом профиле и в команде прогона — не основной профиль.

### Субагенты
- нет

### Проверка
- Новых правок нет. Прогон попытки 1: `./scripts/test-services.sh service-j --no-prepare`, BUILD SUCCESS.

### Итог попытки
- Закрыто: нечего закрывать.
- Осталось: нет.
- Стоп: рекомендации 0.

## Попытка 3 — 2026-10-28

### Оценка
- security: соблюдено. В основном `application.yml` нет пароля и флагов `useSSL=false` / `allowPublicKeyRetrieval`. Лог визита: http/https, без userinfo, хост из `allowed-hosts`, таймаут 2 с, токен в лог не пишется. Bean Validation на index, PATCH и head-organization на месте. `v-html` / `eval` нет, `:is` в `SalesOutletsCell.vue` берётся из карты компонентов.
- architecture: соблюдено. В `sales-outlets-domain` и `sales-outlets-application` нет импортов Spring, JPA и Servlet. Entity и `EntityManager` только в infrastructure и тестах api. `messenger-choice` не зависит от sales-outlets.
- transactions: соблюдено. update, head-organization и delete идут через `modify`: одна транзакция, `findActiveByIdForUpdate`, `deleted_at IS NULL`. `findById` и `paginate` — `readOnly`. Вызов снаружи адаптера, self-invocation нет.
- jpa: соблюдено. `ddl-auto: none`, `open-in-view: false`, `show-sql: false`. `per_page` 5..50. Soft-delete на чтении, поиске и записи. Колонки entity совпадают с миграцией `sales_outlets` (строки, enum-значения, `approved` 32, `user_id`, timestamps, `deleted_at`). Сортировка и фильтры — параметр Criteria, путь свойства из `SalesOutletColumns`, не из строки запроса.
- config: соблюдено. Тестовый профиль — `sail_db_testing`. Драйвер MySQL — `runtime`. В domain и application нет Spring. Версии модулей из parent BOM.
- assumptions: соблюдено. Часы — `Clock.systemUTC` и `Instant`, выборки по границе суток нет. У HTTP-клиента визита заданы connect и read timeout. `v-for` ключи — id, value или key колонки, не индекс. `Promise.all` нет.

Рекомендации: нет.

Отложено (не рекомендация): `save` без `FOR UPDATE` — use-case его не вызывает. Порт и путь лога на хосте из allow-list задаёт оператор. Доверие к `X-User-Id` и пароль в compose / `.env.example` ломают текущий вызов gateway. `useSSL=false` только в тестовом профиле. Один `ORDER BY` без запасного `id` совпадает с `DoctrineSalesOutletQueryApplicator` в service-e; сортировка по умолчанию — уникальный `id`. Настройки колонок в `localStorage` — раскладка таблицы, не строки другого пользователя. В SPA один маршрут, ответ index не применяется к размонтированному экрану.

### Субагенты
- нет

### Проверка
- Новых правок нет. Последний прогон: `./scripts/test-services.sh service-j --no-prepare`, BUILD SUCCESS (попытка 1).

### Итог попытки
- Закрыто: нечего закрывать.
- Осталось: нет.
- Стоп: рекомендации 0.

## Попытка 4 — 2026-10-28

Область: git diff (`service-j`, `service-c`, строка `MESSENGER_CHOICE_CLICK_LOG_URL` в `docker-compose.yml`). Неотслеживаемые скрипты и `main-app/bootstrap` в diff не входят.

### Оценка
- security: соблюдено. `GET /messenger/go/{messenger}` принимает только `telegram|max`, код ещё раз разбирает `ChosenMessenger.fromCode`, редирект берётся из `MessengerBotLinks`, не из строки запроса. Клик в service-c пишет enum `MessengerChoice`, ник и URL бота в лог не попадают; маршрут закрыт `messenger.choice.token` (`hash_equals`, пустой секрет — 401). `MessengerChoiceLogTransport` шлёт POST только на http(s) без userinfo и только на хост из `allowed-hosts`. В основном `application.yml` пароля и `useSSL=false` / `allowPublicKeyRetrieval` нет. `v-html` / `eval` в diff нет.
- architecture: соблюдено. В `sales-outlets-domain` и `sales-outlets-application` нет Spring, JPA и Servlet. `modify` остаётся портом, правило агрегата вызывает use-case. `messenger-choice` не импортирует sales-outlets. В service-c клик идёт FormRequest → контракт → логгер, без Eloquent в контракте.
- transactions: соблюдено. update, head-organization и delete вызывают только `modify`: одна `@Transactional`, `findActiveByIdForUpdate` (`PESSIMISTIC_WRITE`, `deleted_at IS NULL`), затем `copyOnto`. `findById` и `paginate` — `readOnly`. Вызов снаружи адаптера, self-invocation нет.
- jpa: соблюдено. `ddl-auto: none`, `open-in-view: false`, `show-sql: false` не менялись. Блокирующий запрос фильтрует `deletedAt IS NULL`. Новых колонок нет.
- config: соблюдено. `click-log-url` пустой без env, в compose задан `http://service-c:8000/internal/messenger-choice/clicks`. Токен по-прежнему только из env. Тестовый профиль этим diff не переключался с `sail_db_testing`.
- assumptions: соблюдено. У обоих RestClient connect и read timeout 2 с. Код мессенджера — enum на обеих сторонах, не только проверка формы. Ссылки в `App.vue` корневые (`/messenger/go/...`), не индекс в `v-for`. Ответ клика — 302, состояния Vue после unmount нет.

Рекомендации: нет.

Отложено (не рекомендация): пароль и JDBC `useSSL=false` / `allowPublicKeyRetrieval=true` в дефолте `docker-compose.yml` и `service-j/.env.example` — тот же пункт, что в попытках 1–3; эта строка diff их не меняет. Порт и путь лога на хосте из allow-list задаёт оператор; редирект `HttpURLConnection` после ответа `service-c` тем же остаточным риском и не стал отдельной правкой. `save` без `FOR UPDATE` use-case не вызывает. Доверие к `X-Forwarded-Host` / `X-Real-IP` на опубликованном порту service-j такое же, как у визита страницы; nginx эти заголовки перезаписывает. Повторный GET пишет ещё один клик — так задумано комментарием контроллера.

### Субагенты
- нет

### Проверка
- Правок в этой попытке нет. Прогон `./scripts/test-services.sh` не запускался.
- Последний записанный прогон: попытка 1, `./scripts/test-services.sh service-j --no-prepare`, BUILD SUCCESS.

### Итог попытки
- Закрыто: нечего закрывать.
- Осталось: нет.
- Стоп: рекомендации 0.
