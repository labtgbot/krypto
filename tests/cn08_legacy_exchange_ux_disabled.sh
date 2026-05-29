#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

failures=0

fail() {
  printf 'FAIL: %s\n' "$1"
  failures=1
}

pass() {
  printf 'ok: %s\n' "$1"
}

assert_contains() {
  local file="$1"
  local pattern="$2"
  local message="$3"

  if grep -Eq "$pattern" "$file"; then
    pass "$message"
  else
    fail "$message"
  fi
}

assert_not_contains() {
  local file="$1"
  local pattern="$2"
  local message="$3"

  if grep -Eq "$pattern" "$file"; then
    fail "$message"
  else
    pass "$message"
  fi
}

assert_missing() {
  local path="$1"
  local message="$2"

  if [[ -e "$path" ]]; then
    fail "$message"
  else
    pass "$message"
  fi
}

assert_php_lints() {
  local file="$1"

  if php -l "$file" >/dev/null; then
    pass "PHP syntax: $file"
  else
    fail "PHP syntax: $file"
  fi
}

assert_contains app/src/App/App.php 'function _legacyExchangeConnectionsEnabled' 'legacy exchange rollback flag exists for old settings reads'
assert_contains install/assets/sql/krypto.sql "'legacy_exchange_connections_enabled', '0'" 'fresh installs disable legacy exchange setup'
assert_contains app/modules/kr-admin/views/trading.php 'ChangeNOW' 'admin trading view shows ChangeNOW provider status'
assert_contains app/modules/kr-admin/src/actions/saveTrading.php '_legacyExchangeConnectionsEnabled\(\)' 'admin trading save ignores forged legacy settings unless rollback flag is enabled'

legacy_runtime_paths=(
  app/modules/kr-trade/src/0Exchange.php
  app/modules/kr-trade/src/Trade.php
  app/modules/kr-trade/src/Widthdraw.php
  app/modules/kr-trade/src/HiddenThirdParty.php
  app/modules/kr-trade/src/Binance.php
  app/modules/kr-trade/src/Kraken.php
  app/modules/kr-trade/src/Gdax.php
  app/modules/kr-trade/src/Yobit.php
  app/modules/kr-trade/src/actions
  app/modules/kr-trade/views
  app/modules/kr-trade/statics
  assets/img/icons/trade
)

for legacy_path in "${legacy_runtime_paths[@]}"; do
  assert_missing "$legacy_path" "legacy exchange runtime files are removed: $legacy_path"
done

legacy_route_paths=(
  app/modules/kr-user/views/exchanges.php
  app/modules/kr-user/views/widthdraw.php
  app/modules/kr-admin/views/walletaddress.php
  app/modules/kr-admin/views/autowithdrawconfigure.php
  app/modules/kr-admin/src/actions/saveWallets.php
  app/modules/kr-admin/src/actions/saveWithdrawExchange.php
  app/modules/kr-payment/views/directdeposit.php
)

for legacy_path in "${legacy_route_paths[@]}"; do
  assert_missing "$legacy_path" "legacy exchange or wallet route is removed: $legacy_path"
done

active_files=(
  dashboard.php
  app/modules/kr-user/views/account.php
  app/modules/kr-admin/views/trading.php
  app/modules/kr-dashboard/src/actions/loadChart.php
  app/modules/kr-dashboard/src/actions/loadChartContent.php
  app/modules/kr-dashboard/statics/js/chartTrade.js
  app/modules/kr-dashboard/statics/js/leftinfos.js
  app/modules/kr-coin/views/coin.php
  app/modules/kr-coin/statics/js/script.js
)

for active_file in "${active_files[@]}"; do
  assert_not_contains "$active_file" 'connectThirdparty\.php|saveThirdpartySettings\.php|removeThirdparty\.php|changeMainThirdparty\.php|balanceList\.php|initWidthdrawAccount\.php|placeTrade\.php|getOrderList\.php' "active file has no legacy exchange action routes: $active_file"
  assert_not_contains "$active_file" 'new Trade\(|new HiddenThirdParty\(|new Widthdraw\(' "active file does not instantiate removed legacy classes: $active_file"
done

php_files=(
  app/src/App/App.php
  app/modules/kr-trade/src/Balance.php
  app/modules/kr-admin/views/trading.php
  app/modules/kr-admin/views/bankaccounts.php
  app/modules/kr-admin/src/actions/saveTrading.php
  app/modules/kr-dashboard/src/actions/loadChartContent.php
  app/modules/kr-dashboard/src/actions/loadChart.php
  app/modules/kr-dashboard/views/dashboard.php
  app/modules/kr-coin/views/coin.php
  app/modules/kr-marketanalysis/views/marketlist.php
  app/modules/kr-manager/views/payments.php
  app/modules/kr-manager/views/userinfos.php
)

for php_file in "${php_files[@]}"; do
  assert_php_lints "$php_file"
done

if [[ "$failures" -ne 0 ]]; then
  exit 1
fi
