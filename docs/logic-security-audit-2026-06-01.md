# Аудит логики и безопасности приложения — 2026-06-01

Документ выполняет аналитическую часть запроса #85: сквозной разбор логики
приложения Krypto (legacy PHP, non-custodial своп на базе ChangeNOW) с
фиксацией подтверждённых в коде дефектов, багов и уязвимостей. Каждая находка
имеет устойчивый код (`A1`, `B2`, …), ссылку на файл и строки, оценку severity и
этап реализации (milestone). По находкам заведены отдельные профессиональные
задачи; трекер — #87. Структура трекера (коды SEC-NN, severity, этапы, метки)
зафиксирована в [`docs/logic-security-audit-tracker-2026-06-01.md`](logic-security-audit-tracker-2026-06-01.md).

Аудит охватывает первичный PHP-код под `app/`, `install/`, `public/`,
`index.php`, `dashboard.php`, а также конфигурацию Composer и завендоренные
фронт-зависимости. Анализ статический: без боевых креденшелов и развёрнутой БД.

## Базовая линия

- `php scripts/lint_php.php` — проходит до изменений этого PR.
- `php scripts/run_tests.php` — проходит до изменений этого PR.
- Composer CLI в подготовленном окружении недоступен, поэтому `composer audit`
  не запускался локально; выводы по зависимостям сделаны из `composer.json`,
  `composer.lock` и завендоренных каталогов.

## Что НЕ входит (закрыто ранее)

Ранее реализованные направления повторно не выносятся: #55 (AEAD-шифрование),
#56 (CSRF-защита), #57 (хардненинг загрузок), #58 (документация веб-доступа),
#69–#76 (серия OPEN: rate limiting, региональные ограничения, retention, e2e,
вывод legacy-custody, документация, прунинг зависимостей).

## Сводка по этапам реализации

| Этап (milestone) | Задачи | Фокус |
| --- | --- | --- |
| Stage 1 — Critical & High | #88–#96 | Аутентификация, XSS, контроль доступа, секреты |
| Stage 2 — Medium hardening | #97–#103 | TLS, SSRF, IP/Host-доверие, целостность свопа, крипто |
| Stage 3 — Supply-chain | #104 | Заброшенные и уязвимые зависимости |
| Stage 4 — Cleanup & robustness | #105–#106 | Гейтинг модулей, утечки, cron |

## Примечание о нумерации

Коды находок присвоены по категориям (A — аутентификация, B — XSS, C —
контроль доступа, D — установщик/runtime, E — секреты и поставка, F —
сеть/транспорт/SSRF, G — целостность свопа, I — раскрытие информации). При
триаже часть кандидатных кодов (`A8`, `C4`, `C5`) была объединена со смежными
находками и не выделена в отдельные пункты — поэтому нумерация внутри категорий
не сплошная. Каждый используемый код соответствует ровно одной находке и ровно
одной задаче.

---

## A. Аутентификация и учётные записи

### A1 — Несолёное хеширование паролей `sha512` · Critical · #88
`app/src/User/User.php:342,463,756,844` — пароли хешируются `hash('sha512', …)`
без соли и без алгоритма с настраиваемой стоимостью. Уязвимо к радужным
таблицам и массовому перебору при утечке БД.
**Исправление:** `password_hash`/`password_verify` (bcrypt/argon2) с прозрачным
`password_needs_rehash` при входе.

### A2 — Нет троттлинга входа/2FA/сброса · High · #93
`app/modules/kr-user/src/actions/login.php:69` → `User.php:331-380` — нет
ограничения числа попыток входа, проверки 2FA и запроса сброса. Допускает
brute-force и credential stuffing.

### A3 — Нет `session_regenerate_id` при входе · High · #92
`User.php:371` — идентификатор сессии не пересоздаётся после аутентификации →
session fixation.

### A4 — Cookie сессии без флагов безопасности · High · #92
Не выставляются `HttpOnly`/`Secure`/`SameSite` для cookie сессии → перехват
через XSS и по незащищённому каналу, CSRF-вектор.

### A5 — Предсказуемый reset-токен без срока · High · #94
`User.php:620-639` — токен сброса формируется через `str_shuffle`, без срока
жизни и одноразовости. Предсказуем и переиспользуем.
**Исправление:** `random_bytes`, срок жизни, single-use, хеш в БД.

### A6 — Оракул перечисления учёток · High · #93
`login.php:73-77` — разные ответы для «нет пользователя» и «неверный пароль»
позволяют перечислять существующие email/логины.

### A7 — Константный пароль OAuth-входа · High · #96
`User.php:386-401` — для OAuth-пользователей применяется фиксированный
пароль-константа, что фактически делает учётки предсказуемо аутентифицируемыми.

### A9 — Смена пароля/email без ре-аутентификации · Medium · #102
`app/modules/kr-user/src/actions/updateUserprofile.php:52-59` → `User.php:843-855`
— смена пароля и email без проверки текущего пароля и без 2FA.

### A10 — Снятие 2FA без подтверждения вторым фактором · Medium · #102
`removeGoogleTFS.php:39-41` → `User.php:1073-1080` — TOTP-секрет удаляется без
ввода кода/пароля (тогда как включение код проверяет, `validateGoogleTFS.php:41`).

### A11 — `_getGoogleTFSSecret` без фильтра статуса · Medium · #102
`User.php:1040-1050` — `SELECT * … WHERE id_user=:id_user` без
`status_googletfs=1` и без `ORDER BY`; при нескольких строках может
использоваться неактивный/pending-секрет.

### A12 — Legacy-путь детерминированного шифрования · Medium · #103
`app/src/App/App.php:1767-1768,1837-1855` — AES-256-CBC с фиксированным IV
`substr(hash('sha256', strrev(CRYPTED_KEY)),0,16)`; шифр детерминирован, утекает
равенство открытых текстов. Сосуществует с новым AEAD (#55).

---

## B. Межсайтовый скриптинг (XSS)

### B1 — Отражённый XSS через `rmsg` · High · #89
`index.php:227` — `base64_decode($_GET['rmsg'])` выводится в контекст
`<script>` без экранирования.

### B5 — Отражённый XSS в `coinlist` · High · #89
`coinlist.php:50` — `$_POST['search']` отражается без экранирования.

### B6 — Отражённый XSS в `exportGraph` · High · #89
`exportGraph.php:56` — `$_POST['container']` попадает в вывод без санитизации.

### B2 — Хранимый XSS через имя пользователя · High · #90
`signup.php:66` — имя сохраняется без санитизации; небезопасные стоки:
`userinfos.php:72,106`, `users.php`, `identity.php:69`, `loadRoom.php:129`.

### B3 — Хранимый XSS в чате · High · #90
`roomSendMessage.php:41` → `ChatRoom.php:141-154`; стоки `loadRoom.php:132`,
`loadChat.php:82`, `chat.js:83`, `bar.js:79`.

### B4 — Хранимый XSS в новостях · High · #90
`loadNews.php:55,61-76` — содержимое новостей рендерится без экранирования.

### B7 — Хранимый XSS в календаре · High · #90
`loadSideCalendarItem.php:67,70,100-103`.

### B8 — Небезопасные стоки профиля/идентичности · High · #90
Связанные с B2 стоки имени/профиля без экранирования в админ- и
кабинетных представлениях.

---

## C. Нарушение контроля доступа / IDOR

### C1 — Менеджер может удалить администратора · High · #91
`deleteUser.php:36-48` — отсутствует проверка роли цели; менеджер удаляет
учётки администраторов (привилегированная эскалация/вред).

### C2 — IDOR и смена статуса в банковских пруфах · High · #91
`addProofBanktransfert.php:35-38` → `Banktransfert.php:222-247` — нет проверки
владения; чужой перевод можно изменить/подтвердить.

### C3 — Нет проверки членства в комнате чата · High · #91
`loadRoom.php:36`, `roomSendMessage.php:39-64` — доступ/отправка в комнату без
проверки участия.

### C6 — Скачивание чужих вложений · High · #91
`downloadAttachedFile.php:36-45` — выдача файла без проверки прав на вложение.

### C7 — Отключённая проверка владения в `sendProof` · High · #91
`sendProof.php:40` → `Manager.php:130` — проверка владения закомментирована.

---

## D. Установщик и runtime-конфигурация

### D1 — Небезопасная генерация `CRYPTED_KEY` · High · #95
`install/app/src/Install.php:117-126` — ключ формируется через `rand()` (не
криптостойко). **Исправление:** `random_bytes`.

### D2 — Установщик без блокировки повторного запуска · High · #95
`install/index.php:9-14`, `Install.php:90-96,168-220` — нет lock после
установки; возможен повторный прогон/перезапись конфигурации.

### D3 — Мёртвый гейтинг модулей, нет allowlist контроллеров · Medium · #105
`app/src/App/AppModule.php:76-92` — `_isEnable()`/`_checkConfig()` оба
`return true;`; `App.php:88-124,146-158` безусловно подключают `src` всех
модулей. Модуль нельзя отключить конфигом; attack surface расширен.

---

## E. Секреты и цепочка поставок

### E1 — Захардкоженные секреты · High · #96
`app/modules/kr-api/src/Api.php:5` (`'ZGz3dSbvv8EGhFBX'`),
`RssFeed.php:57` (rss2json key), `Etherblock.php:19,30`. Секреты в VCS.

### E2 — Заброшенные composer-пакеты · High (supply-chain) · #104
`composer.json:18,20` — `facebook/graph-sdk: ^5.6` (abandoned),
`milqmedia/poeditor-api-client: ^0.0.1`; в `composer.lock` `"abandoned": true`
также для `sonata-project/google-authenticator`.

### E3 — Завендоренные уязвимые фронт-библиотеки · High (supply-chain) · #104
`assets/bower/…`: jQuery 3.1.1 (CVE-2020-11022/11023), jQuery UI 1.12.1,
moment 2.10.6, sweetalert2 7.26.11; `assets/node_modules/…`: core-js 2.5.3.

### E4 — Сборочные артефакты в репозитории · High (supply-chain) · #104
`.gitignore` не игнорирует `assets/bower`, `assets/node_modules`, `vendor` —
уязвимые зависимости закоммичены в дерево.

---

## F. Сеть, транспорт, SSRF и доверие к заголовкам

### F1 — Отключена проверка TLS · Medium · #97
`CURLOPT_SSL_VERIFYPEER, 0` в `User.php:1288`, `App.php:2138,2180`,
`CryptoApi.php:197`, `Calendar.php:29`, `ChainSo.php:31`, `Etherblock.php:32`,
`BitcoinExplorer.php:26`. MITM исходящих вызовов.

### F2 — Динамическая инстанциация класса в `getOrderBook` · Medium · #98
`getOrderBook.php:46-48` — `new ('\\ccxt\\'.strtolower($_GET['market']))()`,
symbol/currency без валидации, без `try/catch`.

### F3 — SSRF/инъекция параметров в URL explorer'ов · Medium · #98
`BitcoinExplorer.php:23,45-46,77`, `ChainSo.php:29`, `Etherblock.php:22-30,52-61`
— address/tx/symbol конкатенируются без валидации и URL-кодирования.

### F4 — Латентный XXE в неиспользуемом RSS-парсере · Medium · #98
`Feed.php:143-162` — `new SimpleXMLElement($data, …)` без отключения внешних
сущностей. Класс загружается, но сейчас не вызывается.

### F5 — Подделываемый IP клиента · Medium · #99
`App.php:2163-2172` (`_getVisitorIP`) и `User.php:793-802` (`_getUserIP`)
доверяют `HTTP_CLIENT_IP`/`HTTP_X_FORWARDED_FOR` прежде `REMOTE_ADDR`.
Дополнительно `_saveUserLoginHistory` (`User.php:1274`) — мёртвый код
(`return true;`), alert о новом IP не работает.

### F6 — Доверие к заголовку `Host` при построении URL · Medium · #100
`App.php:1689-1697` (`_checkDomain`) строит `Location` из `HTTP_HOST`/`PHP_SELF`;
`PayBearOrder.php:126` — `'http://' . $_SERVER['HTTP_HOST'] . …`. Host-injection,
open-redirect, plaintext callback.

---

## G. Целостность ChangeNOW-свопа

### G1 — Котировка не привязана к серверу · Medium · #101
`ChangeNowPublicSwapFlow.php:587-588,724-731` — `rateId`/`validUntil` берутся из
клиента; проверка срока опирается на клиентскую строку, а не на серверную
запись. Для standard-флоу гарантия срока котировки не enforced.

### G2 — Гео-проверка fail-open по plaintext HTTP · Medium · #101
`App.php:2135,2138` — `http://geoip.nekudo.com/…` с отключённым TLS; пустая
страна трактуется как разрешённая (`ChangeNowGuardrails.php:347-353`), блокировка
стран молча обходится.

### G3 — `FOLLOWLOCATION` с API-ключом · Medium · #101
`ChangeNowApiClient.php:263` (`CURLOPT_FOLLOWLOCATION, 1`) при заголовке
`x-changenow-api-key` (`:307-313`) — редирект может переотправить ключ.

---

## I. Раскрытие информации и операционные риски

### I1 — Утечки через ошибки и отладочный вывод · Low/Medium · #106
`dashboard.php:80` `die($e->getMessage())`; `Lang.php:127`;
`install/.../Install.php:111-113`; остаточные `var_dump`: `Api.php:51`,
`BitcoinExplorer.php:24,61`, `DepositAddress.php:53`.

### I2 — Неаутентифицированные cron/служебные эндпоинты · Low/Medium · #106
`clearCron.php:21-29` — `var_dump` строк чата без авторизации;
`cronCleanCache.php` — сброс кеша без авторизации/секрета. Доступны по HTTP.

---

## Соответствие задачам

| Код(ы) | Задача | Severity | Этап |
| --- | --- | --- | --- |
| A1 | #88 | Critical | 1 |
| B1, B5, B6 | #89 | High | 1 |
| B2, B3, B4, B7, B8 | #90 | High | 1 |
| C1, C2, C3, C6, C7 | #91 | High | 1 |
| A3, A4 | #92 | High | 1 |
| A2, A6 | #93 | High | 1 |
| A5 | #94 | High | 1 |
| D1, D2 | #95 | High | 1 |
| E1, A7 | #96 | High | 1 |
| F1 | #97 | Medium | 2 |
| F2, F3, F4 | #98 | Medium | 2 |
| F5 | #99 | Medium | 2 |
| F6 | #100 | Medium | 2 |
| G1, G2, G3 | #101 | Medium | 2 |
| A9, A10, A11 | #102 | Medium | 2 |
| A12 | #103 | Medium | 2 |
| E2, E3, E4 | #104 | High | 3 |
| D3 | #105 | Medium | 4 |
| I1, I2 | #106 | Low/Medium | 4 |

Трекер: #87. Источник запроса: #85.
