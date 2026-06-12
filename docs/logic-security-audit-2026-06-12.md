# Остаточный аудит логики и безопасности приложения — 2026-06-12

Документ выполняет аналитическую часть запроса #127: повторный сквозной обзор
текущей ветки Krypto после закрытия задач SEC-01..SEC-19 (#88-#106) и связанных
PR #107-#126. Цель этой волны — не переоткрывать уже исправленные пункты, а
зафиксировать подтвержденные в коде остаточные дефекты, баги и уязвимости,
которые остались после предыдущего аудита.

По каждой подтвержденной находке заведена отдельная задача с метками `security`,
`severity:*`, `audit-2026-06` и существующим milestone/stage. Трекер этой волны
хранится в [`docs/logic-security-audit-tracker-2026-06-12.md`](logic-security-audit-tracker-2026-06-12.md).

## Область анализа

Анализ статический: без боевых ключей, без развернутой БД и без live provider
callbacks. Проверены:

- текущий код `app/`, `install/`, `index.php`, `dashboard.php`;
- тесты и CI-конфигурация;
- закрытые audit/security issues и PR предыдущей волны;
- публичный ChangeNOW flow и legacy payment callbacks, потому что эти зоны
  остаются HTTP-доступными и содержат внешние provider boundaries.

## Базовая линия после предыдущей волны

На старте #127 открытой оставалась только зонтичная задача аудита. Предыдущая
волна закрыла крупные классы рисков: пароли и сессии, XSS, IDOR, установщик,
секреты, TLS/SSRF, доверие к IP/Host, ChangeNOW quote integrity, debug/cron и
supply-chain. В этой волне повторно не выносятся уже закрытые направления, если
текущий код не показывает новый или частично оставшийся дефект.

Отдельно проверено, что следующие исправления не переоткрываются как новые
задачи:

- ChangeNOW API client больше не следует redirect с API-ключом.
- Публичный ChangeNOW geo/eligibility flow не трактует unknown country как
  allowed при наличии blocked-country list.
- Public rate limiter использует fingerprinted identities и доверяет forwarded
  IP только через configured trusted proxies.
- CSRF allowlist документирует provider-specific validation для callback
  endpoints.

## Сводка находок

| Код | Задача | Severity | Этап | Кратко |
| --- | --- | --- | --- | --- |
| R1 | #129 | Medium | Stage 2 | `status`/`refund`/`continue` публичного ChangeNOW-свопа обходят rate limit |
| R2 | #130 | Medium | Stage 4 | Generic exceptions публичного ChangeNOW endpoint возвращаются клиенту raw |
| P1 | #131 | High | Stage 1 | Payeer callback подменяет incoming webhook тестовым payload |
| P2 | #132 | High | Stage 1 | Perfect Money IPN использует hard-coded passphrase и не подтверждает deposit |

---

## R. Публичный ChangeNOW flow

### R1 — `status`/`refund`/`continue` обходят rate limit · Medium · #129

`app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php:16-22` назначает
bucket только для `quote`, `validate` и `create`; для остальных actions
возвращается `null`. В
`app/modules/kr-changenow/src/actions/publicSwap.php:187-189` это означает
полный пропуск лимитера перед выполнением action.

Публичные actions `status`, `refund` и `continue`
(`app/modules/kr-changenow/src/actions/publicSwap.php:235-266`) работают по
lookup token. `status` создает неограниченную нагрузку на lookup/status path, а
`refund` и `continue` меняют состояние swap. Текущий тест
`tests/changenow_public_swap_rate_limit_test.php` прямо закрепляет проблему:
`bucketForAction('status') === null`, limiter не вызывается и session key не
создается (`:134-138`, `:186-195`).

**Требуемое направление исправления:** добавить bucket для `status` и отдельный
или более строгий bucket для state-changing `refund`/`continue`, обновить тесты
так, чтобы отсутствие limiter coverage стало регрессией.

### R2 — Generic exceptions публичного ChangeNOW endpoint возвращаются raw · Medium · #130

`app/modules/kr-changenow/src/actions/publicSwap.php:270-272` безопасно
обрабатывает `ChangeNowApiException` через user-facing `_getUserMessage()`. Но
следующий общий блок `app/modules/kr-changenow/src/actions/publicSwap.php:273-274`
возвращает `$e->getMessage()` в JSON поле `msg`.

До этого блока выполняются инициализация ChangeNOW API client, market data,
repository/flow и публичные actions
(`app/modules/kr-changenow/src/actions/publicSwap.php:198-203`, `:235-266`).
Обычные исключения из этих участков могут содержать SQL/provider/filesystem или
configuration details. Для публичного endpoint это информационная утечка и
разведочный канал.

**Требуемое направление исправления:** логировать подробность на сервере, а
клиенту возвращать стабильное generic-сообщение. Отдельный тест должен проверять
generic exception path, не ломая безопасные provider validation messages из
`ChangeNowApiException`.

---

## P. Legacy payment callbacks

### P1 — Payeer callback подменяет webhook тестовым payload · High · #131

`app/src/App/csrf_policy.php:48-50` исключает Payeer callback из CSRF и
документирует две компенсирующие проверки: source IP restriction и `m_sign`
against configured order signature. IP-check действительно есть в
`app/modules/kr-payment/src/actions/processPayeer.php:32`, но сразу после него
production action перезаписывает `$_POST` hard-coded JSON sample
(`app/modules/kr-payment/src/actions/processPayeer.php:34-48`).

В результате `Payeer::_checkPayment()`
(`app/modules/kr-payment/src/Payeer.php:106-115`) проверяет подпись и меняет
deposit status не по фактическому callback provider, а по зашитому примеру.
Дополнительно после закрывающего PHP tag endpoint содержит raw GET/POST sample
block (`app/modules/kr-payment/src/actions/processPayeer.php:67-97`), который не
должен находиться в HTTP-доступном callback файле.

**Требуемое направление исправления:** удалить hard-coded fixture из production
action, валидировать фактический incoming payload, убрать raw sample output и
добавить regression test на отсутствие тестовой подмены в endpoint.

### P2 — Perfect Money IPN не подтверждает deposit и использует hard-coded secret · High · #132

`app/src/App/csrf_policy.php:80-82` исключает Perfect Money IPN из CSRF и
документирует, что `V2_HASH` пересчитывается с configured alternate passphrase.
Фактический endpoint
`app/modules/kr-payment/src/actions/deposit/processPerfectMoney.php` использует
hard-coded `PASSWORD_ACCOUNT` (`:10`), логирует весь `$_POST` (`:11`), проверяет
набор полей и hash (`:12-31`), а затем только присваивает локальные переменные
(`:32-35`) и не обновляет deposit status.

Класс `PerfectMoney::_checkPayment()` полностью закомментирован
(`app/modules/kr-payment/src/PerfectMoney.php:62-73`). Форма оплаты уводит
success/failure URLs на `https://krypto.dev.ovrley.com/.../test.php`
(`app/modules/kr-payment/views/perfectmoney.php:61-65`). Админские настройки
Perfect Money скрыты `if(false)`, payee account берется из чужих
RaveFlutterwave/AppTitle полей (`app/modules/kr-admin/views/payment.php:551-580`),
а `app/modules/kr-admin/src/actions/savePayment.php:56-125` не сохраняет
`perfectmoney_enabled`, payee account/name или alternate passphrase.

**Требуемое направление исправления:** добавить encrypted configurable
alternate passphrase, реализовать идемпотентное подтверждение только ожидающего
депозита с проверкой account/sum/currency/ref, убрать raw POST logging и
dev-return URLs, а также починить или явно отключить admin settings до готовности.

## Трассируемость

| Задача | Находки | Метки | Milestone |
| --- | --- | --- | --- |
| #129 | R1 | `security`, `severity: medium`, `audit-2026-06` | Stage 2 — Medium hardening |
| #130 | R2 | `security`, `severity: medium`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #131 | P1 | `security`, `severity: high`, `audit-2026-06` | Stage 1 — Critical & High security |
| #132 | P2 | `security`, `severity: high`, `audit-2026-06` | Stage 1 — Critical & High security |

Эти задачи являются отдельной очередью исправлений. PR #128 фиксирует результат
анализа #127 и оставляет реализацию конкретных исправлений в специализированных
PR по #129-#132.
