# Остаточный аудит логики и безопасности приложения — 2026-06-13

Документ выполняет аналитическую часть запроса #137: третья сквозная волна
обзора текущей ветки Krypto после закрытия SEC-01..SEC-19 (#88-#106) и волны
SEC-20..SEC-23 (#129-#132, PR #128). Цель — не переоткрывать уже исправленные
пункты, а зафиксировать новые подтвержденные в коде остаточные дефекты, баги и
уязвимости, найденные при повторном анализе платежных callback-эндпоинтов,
публичного ChangeNOW-флоу и расчетной/отображающей логики.

По каждой подтвержденной находке заведена отдельная задача с метками `security`,
`severity:*`, `audit-2026-06` и существующим milestone/stage. Трекер этой волны
хранится в [`docs/logic-security-audit-tracker-2026-06-13.md`](logic-security-audit-tracker-2026-06-13.md).

## Область анализа

Анализ статический: без боевых ключей, без развернутой БД и без live provider
callbacks. Проверены:

- платежные deposit/IPN callbacks (`app/modules/kr-payment/`), особенно Mollie,
  Coinbase Commerce и Blockonomics, потому что они HTTP-доступны и кредитуют
  баланс пользователя;
- публичный ChangeNOW swap flow (`app/modules/kr-changenow/`): кеширование
  котировок, синхронизация рыночных данных, rate limiting, refund path,
  реферальная атрибуция;
- расчетная и отображающая логика (`app/src/CryptoApi/`, `kr-blockfolio`) и
  смежные access-control проверки (`kr-identity`);
- слой БД (`app/src/MySQL/MySQL.php`) на предмет SQL-инъекций.

## Базовая линия после предыдущих волн

Подтверждено, что следующие исправления НЕ переоткрываются как новые задачи:

- `status`/`refund`/`continue` публичного ChangeNOW-свопа уже забакечены
  (SEC-20/#129) — повторно не выносятся; в этой волне отдельно вынесена только
  неохваченная action `destinations`.
- Raw exception messages публичного endpoint (SEC-21/#130), Payeer callback
  (SEC-22/#131) и Perfect Money IPN (SEC-23/#132) уже оформлены.
- SQL-слой (`querySqlRequest`/`countSqlRequest`/`execSqlRequest`) использует
  PDO prepared statements; сквозная трассировка конкатенационных sink-ов новых
  SQL-инъекций не выявила. Новых SQLi-задач в этой волне нет.

## Сводка находок

| Код | Задача | Severity | Этап | Кратко |
| --- | --- | --- | --- | --- |
| P1 | #139 | High | Stage 1 | Mollie deposit webhook без идемпотентности → replay double-credit |
| P2 | #140 | Medium | Stage 1 | Coinbase Commerce webhook: неверный секрет подписи, неверный status-ключ, ложный CSRF-комментарий |
| P3 | #141 | Medium | Stage 1 | Blockonomics кредитует неверную сумму из-за early return (vin вместо vout) |
| N1 | #142 | Medium | Stage 2 | Fixed-rate котировка кешируется с одноразовым rateId → параллельные свопы падают |
| N2 | #143 | Medium | Stage 2 | Синхронизация market-data не атомарна → рынок может полностью «погаснуть» |
| N3 | #144 | Low | Stage 2 | Публичная action `destinations` не охвачена rate limit |
| N4 | #145 | Low | Stage 4 | Refund path пропускает `_validateAddress`, который есть в create path |
| N5 | #146 | Low | Stage 4 | `_checkReferalSource($_POST)` игнорирует аргумент → POST-реферал теряется |
| L1 | #147 | Medium | Stage 4 | Blockfolio считает прибыль на строке с разделителем тысяч → ломается при holding ≥ 1000 |
| L2 | #148 | Medium | Stage 4 | Histo cache dedup-ключ не совпадает → дубликаты `histo_krypto` копятся ежеминутно |
| L3 | #149 | Low | Stage 4 | Инвертированный/некорректный компаратор `usort` сортировки histo по времени |
| L4 | #150 | Low | Stage 4 | `changeIdentityStatus` блокирует реальных менеджеров из-за двойной проверки роли |

---

## P. Платежные deposit callbacks

### P1 — Mollie deposit webhook без идемпотентности · High · #139

`app/modules/kr-payment/src/actions/deposit/processMollie.php:21-46` получает
платеж Mollie по клиентскому `$_POST['id']` и затем **безусловно** вызывает
`$Balance->_addDeposit(...)`. `_addDeposit`
(`app/modules/kr-trade/src/Balance.php:171-200`) — это `INSERT` без дедупликации;
`_depositAlreadyDone` (`app/modules/kr-trade/src/Balance.php:206-212`)
существует, но в Mollie-пути **не вызывается**. `_checkPayment`
(`app/modules/kr-payment/src/Mollie.php:126-150`) корректно берет
`user_id`/`amount`/`currency` из server-fetched платежа, но повторная обработка
того же id ничем не ограничена.

Повтор того же оплаченного Mollie id кредитует баланс многократно.
Дополнительно: при `_checkPayment === false` код все равно выполняет
`new User($paymentCheck['user_id'])` (то есть `false['user_id']` → null) и
вставляет строку со `status=0`.

**Направление исправления:** привязать payment id к единственному pending
депозиту и отклонять повторы (через `_depositAlreadyDone` / уникальный
`payment_reference`), кредитовать только pending-депозит, и прерывать обработчик
при falsy `_checkPayment`. Регрессионный тест: повторный вызов с тем же id не
создает второй кредит.

### P2 — Coinbase Commerce webhook: неверный секрет, неверный ключ статуса, ложный CSRF-комментарий · Medium · #140

`app/modules/kr-payment/src/CoinbaseCommerce.php:143-157` (`_validateRequest`)
считает HMAC тела с `_getCoinbaseCommerceAPIKey()`, тогда как Coinbase
подписывает webhooks выделенным **shared webhook secret**. Геттера/настройки
такого секрета нет — `app/src/App/App.php:840` содержит только
`_getCoinbaseCommerceAPIKey`. Подпись никогда не совпадет → легитимные webhooks
отклоняются (fail-closed, депозиты не подтверждаются).

`_confirmTransaction` (`app/modules/kr-payment/src/CoinbaseCommerce.php:124-141`)
вызывает `_changeDepositStatus($payload['event']['id'], '1')` — это **event id**,
но в `payment_data` сохранен **charge id** (`data->id`), то есть сопоставляется
неверный идентификатор. `_changeDepositStatus`
(`app/modules/kr-trade/src/Balance.php:229-236`) к тому же не ограничен по
`id_user` и не проверяет текущий статус. Комментарий
`app/src/App/csrf_policy.php:60-63` ложно утверждает про «configured Coinbase
Commerce shared secret».

**Направление исправления:** добавить шифруемый настраиваемый webhook shared
secret и HMAC по нему; сопоставлять сохраненный charge id (`data->id`);
ограничить `_changeDepositStatus` владельцем и pending-условием; исправить
комментарий CSRF-политики.

### P3 — Blockonomics кредитует неверную сумму из-за early return · Medium · #141

`app/modules/kr-payment/src/Blockonomics.php:109-116`:

```php
public function _calcAmountPayment($PaymentDetail){
    $amount = 0;
    return $this->_convertSatoshiToStandard($PaymentDetail->vin[0]->value); // early return
    foreach ($PaymentDetail->vout as $key => $value) { $amount += $value->value; } // dead
    return $amount;
}
```

Возвращается значение первого **входа** (`vin[0]` — потраченный UTXO
отправителя), а не сумма, доставленная на deposit-адрес. Недостижимый `foreach`
к тому же суммировал бы **все** выходы (включая сдачу). Сумма кредитуемого
депозита (`_validPayment`, `app/modules/kr-payment/src/Blockonomics.php:132`)
поэтому неверна — обычно сильно завышена.

**Направление исправления:** убрать early return и суммировать только выход(ы),
платящие на ожидаемый deposit-адрес, перед конвертацией satoshi → standard.

---

## N. Публичный ChangeNOW flow

### N1 — Fixed-rate котировка кешируется с одноразовым rateId · Medium · #142

`_getQuote` (`app/modules/kr-changenow/src/ChangeNowMarketData.php:94-132`)
кеширует весь результат, включая `rateId`
(`app/modules/kr-changenow/src/ChangeNowMarketData.php:275`), для обоих flow —
`standard` и `fixed-rate`. Ключ `_quoteCacheKey`
(`app/modules/kr-changenow/src/ChangeNowMarketData.php:245-249`) — `sha256`
только от нормализованного запроса, тип flow не отключает кеш; TTL = 30s
(`app/modules/kr-changenow/src/ChangeNowMarketData.php:14`).

Для fixed-rate ChangeNOW выдает одноразовый `rateId`. В пределах TTL все
запросы получают один и тот же `rateId`; после того как первый `_createSwap`
его израсходует, остальные падают с «fixed-rate quote expired».

**Направление исправления:** не кешировать fixed-rate котировки (или кешировать
только нерасходуемые поля), либо делать каждую fixed-rate котировку
single-use. Standard-кеш можно сохранить.

### N2 — Синхронизация market-data не атомарна · Medium · #143

`_replaceAssets` (`app/modules/kr-changenow/src/ChangeNowMarketRepository.php:30`)
и `_replacePairs`
(`app/modules/kr-changenow/src/ChangeNowMarketRepository.php:42`) сначала
`UPDATE ... SET provider_active = 0` для всех строк, затем upsert в цикле без
транзакции; вызываются из
`app/modules/kr-changenow/src/ChangeNowMarketData.php:62-65`. Сбой в середине
цикла оставляет все активы/пары деактивированными — свопы недоступны до
следующей полностью успешной синхронизации.

**Направление исправления:** обернуть deactivate + repopulate в одну транзакцию
либо собирать новый набор в staging и переключать атомарно.

### N3 — Action `destinations` не охвачена rate limit · Low · #144

`bucketForAction`
(`app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php:16-24`) возвращает
`null` для `destinations`, а `app/modules/kr-changenow/src/actions/publicSwap.php:276-281`
выполняет ее с default-allow. (`status`/`refund`/`continue` уже забакечены под
SEC-20/#129 — здесь они не дублируются.) Позволяет нетротлированный перебор и
выжигание provider-квоты с одного клиента.

**Направление исправления:** назначить `destinations` read-bucket в
`bucketForAction` и расширить rate-limit тест.

### N4 — Refund path пропускает `_validateAddress` · Low · #145

Create-путь вызывает `_validateAddress`
(`app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php:164`), а refund-путь
(`app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php:259-268`) и его
support-двойник
(`app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php:335-344`) только
тримят и проверяют непустоту refund-адреса. Некорректный/чужесетевой
refund-адрес может уйти провайдеру.

**Направление исправления:** прогонять refund-адрес через `_validateAddress` в
обоих местах перед отправкой.

### N5 — `_checkReferalSource($_POST)` игнорирует аргумент · Low · #146

`_checkReferalSource()` (`app/src/App/App.php:2094-2103`) не принимает
параметров и читает только `$_GET['ref']`, но вызывается как
`$App->_checkReferalSource($_POST)`
(`app/modules/kr-changenow/src/actions/publicSwap.php:240`). POST-реферал на
публичном swap-endpoint молча теряется (логическая ошибка атрибуции, без
прямого security-эффекта).

**Направление исправления:** либо принять и учитывать явный аргумент реферала,
либо передавать реферал через тот канал, который функция реально читает; тест на
POST-реферал.

---

## L. Расчетная и отображающая логика

### L1 — Blockfolio считает прибыль на строке с разделителем тысяч · Medium · #147

В ветке `_hiddenThirdpartyActive`
`app/modules/kr-blockfolio/views/blockfolio.php:156-157` сначала прогоняет
размер позиции через `_formatNumber` (`number_format` с разделителем тысяч,
`app/src/App/App.php:2082-2088`), а затем умножает полученную **строку** на цену.
PHP приводит, например, `"1,234.56"` к float `1.0`, поэтому для любой позиции
≥ 1000 отображаемая прибыль схлопывается (≈ цена × 1). `else`-ветка
(`app/modules/kr-blockfolio/views/blockfolio.php:159`) использует сырое число и
корректна; атрибут `kr-holding-size`
(`app/modules/kr-blockfolio/views/blockfolio.php:163`) тоже выводит
форматированную строку. Только отображение, средства не двигаются.

**Направление исправления:** хранить сырое число для арифметики и форматировать
только при выводе.

### L2 — Histo cache dedup-ключ не совпадает · Medium · #148

В `app/src/CryptoApi/CryptoCoin.php:359-404` read-cache SELECT (`:359`) и
INSERT/UPDATE (`:386`/`:397`) используют `type_histo = $type.'/'.$market`, а
dedup-SELECT «строка уже есть?» (`:378`) — голый `type_histo = $type`. Сохраненные
строки всегда `$type/$market`, поэтому dedup-SELECT всегда возвращает 0 → каждую
минуту срабатывает ветка INSERT вместо UPDATE, и `histo_krypto` растет на одну
дублирующую строку на монету/валюту/тип/минуту.

**Направление исправления:** использовать тот же `$type.'/'.$market` в dedup-SELECT
(`:378`); рассмотреть разовую очистку дубликатов и уникальный индекс.

### L3 — Инвертированный/некорректный компаратор `usort` · Low · #149

`app/src/CryptoApi/CryptoCoin.php:416-420`: при равном `time` компаратор
возвращает `-1` (должно быть `0`), а в случае `a < b` возвращает `0` (должно быть
`-1`). Это нарушает контракт строгого порядка `usort`; сортировка истории цен по
времени для графика становится неверной/нестабильной.

**Направление исправления:** заменить тело на `return $a['time'] <=> $b['time'];`.

### L4 — `changeIdentityStatus` блокирует реальных менеджеров · Low · #150

`app/modules/kr-identity/src/actions/changeIdentityStatus.php:35-41` содержит и
`if(!$User->_isAdmin()) throw`, и `if(!$User->_isManager()) throw`. Поскольку
`_isManager()` (`app/src/User/User.php:221`: `admin_user == 2 || _isAdmin()`)
истинна для админов, совокупная проверка фактически требует admin и исключает
реальных менеджеров (`admin_user == 2`) — обратное намерению. Over-restrictive
логика (fail-closed, без эскалации привилегий).

**Направление исправления:** заменить два throw одной проверкой
`if(!$User->_isAdmin() && !$User->_isManager()) throw ...`.

## Трассируемость

| Задача | Находки | Метки | Milestone |
| --- | --- | --- | --- |
| #139 | P1 | `security`, `severity: high`, `audit-2026-06` | Stage 1 — Critical & High security |
| #140 | P2 | `security`, `severity: medium`, `audit-2026-06` | Stage 1 — Critical & High security |
| #141 | P3 | `security`, `severity: medium`, `audit-2026-06` | Stage 1 — Critical & High security |
| #142 | N1 | `security`, `severity: medium`, `audit-2026-06` | Stage 2 — Medium hardening |
| #143 | N2 | `security`, `severity: medium`, `audit-2026-06` | Stage 2 — Medium hardening |
| #144 | N3 | `security`, `severity: low`, `audit-2026-06` | Stage 2 — Medium hardening |
| #145 | N4 | `security`, `severity: low`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #146 | N5 | `security`, `severity: low`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #147 | L1 | `security`, `severity: medium`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #148 | L2 | `security`, `severity: medium`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #149 | L3 | `security`, `severity: low`, `audit-2026-06` | Stage 4 — Cleanup & robustness |
| #150 | L4 | `security`, `severity: low`, `audit-2026-06` | Stage 4 — Cleanup & robustness |

Эти задачи являются отдельной очередью исправлений. PR #138 фиксирует результат
анализа #137 и оставляет реализацию конкретных исправлений в специализированных
PR по #139-#150.

## Отклоненные кандидаты (проверено, не дефект)

- `Charges::_checkPaymentResult` «5-секундное окно» — redirect-URL'ы ставят
  `t=time()` (`processPaypal.php:60`) или `t=time()+100000` (Mollie), поэтому
  `time()-t > 5` не отклоняет реальные возвраты; это freshness/replay-guard.
- `Balance::_validateDeposit` «перепутанные позиционные аргументы» — пересчет
  показывает, что `wallet_target` — это литерал `'USD'`, а `$keycharge`
  корректно является `payment_reference`. Не дефект.
- Косметика отображения (`rtrim` в описании, out-of-band low/high %) — только
  робастность вывода, отдельной задачей не выносится.
