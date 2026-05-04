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

assert_php_lints() {
  local file="$1"

  if php -l "$file" >/dev/null; then
    pass "PHP syntax: $file"
  else
    fail "PHP syntax: $file"
  fi
}

assert_contains app/src/App/App.php 'function _legacyExchangeConnectionsEnabled' 'legacy exchange rollback flag exists'
assert_contains install/assets/sql/krypto.sql "'legacy_exchange_connections_enabled', '0'" 'fresh installs disable legacy exchange setup'

assert_not_contains app/modules/kr-user/views/account.php 'kr-user-v="(exchanges|widthdraw)"' 'account navigation has no exchange or wallet setup tabs'
assert_not_contains app/modules/kr-coin/views/coin.php '_showThirdpartySetup|Login with' 'coin view does not prompt for exchange API login'
assert_not_contains app/modules/kr-admin/views/trading.php '_showThirdpartySetup|kr-adm-chk-enablenativetrading|kr-admin-boxthird|Configure exchanges|Ex : BTC = Binance' 'admin trading view exposes no legacy exchange credential controls'
assert_contains app/modules/kr-admin/views/trading.php 'ChangeNOW' 'admin trading view shows ChangeNOW provider status'
assert_contains app/modules/kr-admin/src/actions/saveTrading.php '_legacyExchangeConnectionsEnabled\(\)' 'admin trading save ignores forged legacy settings unless rollback flag is enabled'

assert_not_contains app/modules/kr-dashboard/statics/js/chartTrade.js 'connectThirdparty\.php|saveThirdpartySettings\.php|removeThirdparty\.php' 'dashboard JavaScript has no legacy exchange setup routes'
assert_not_contains app/modules/kr-trade/statics/js/balance.js 'changeMainThirdparty\.php' 'balance JavaScript has no legacy exchange switch route'

legacy_guarded_routes=(
  app/modules/kr-user/views/exchanges.php
  app/modules/kr-user/views/widthdraw.php
  app/modules/kr-trade/views/connectThirdparty.php
  app/modules/kr-trade/views/initWidthdraw.php
  app/modules/kr-trade/src/actions/saveThirdpartySettings.php
  app/modules/kr-trade/src/actions/removeThirdparty.php
  app/modules/kr-trade/src/actions/changeMainThirdparty.php
  app/modules/kr-trade/src/actions/balanceList.php
  app/modules/kr-trade/src/actions/initWidthdrawAccount.php
  app/modules/kr-admin/views/walletaddress.php
  app/modules/kr-admin/views/autowithdrawconfigure.php
  app/modules/kr-admin/src/actions/saveWallets.php
  app/modules/kr-admin/src/actions/saveWithdrawExchange.php
)

for route in "${legacy_guarded_routes[@]}"; do
  assert_contains "$route" '_legacyExchangeConnectionsEnabled\(\)' "legacy flag guards $route"
done

php_files=(
  app/src/App/App.php
  app/modules/kr-user/views/account.php
  app/modules/kr-user/views/exchanges.php
  app/modules/kr-user/views/widthdraw.php
  app/modules/kr-trade/views/connectThirdparty.php
  app/modules/kr-trade/views/initWidthdraw.php
  app/modules/kr-trade/src/actions/saveThirdpartySettings.php
  app/modules/kr-trade/src/actions/removeThirdparty.php
  app/modules/kr-trade/src/actions/changeMainThirdparty.php
  app/modules/kr-trade/src/actions/balanceList.php
  app/modules/kr-trade/src/actions/initWidthdrawAccount.php
  app/modules/kr-admin/views/trading.php
  app/modules/kr-admin/views/walletaddress.php
  app/modules/kr-admin/views/autowithdrawconfigure.php
  app/modules/kr-admin/src/actions/saveWallets.php
  app/modules/kr-admin/src/actions/saveWithdrawExchange.php
  app/modules/kr-admin/src/actions/saveTrading.php
  app/modules/kr-dashboard/src/actions/loadChartContent.php
  app/modules/kr-coin/views/coin.php
)

for php_file in "${php_files[@]}"; do
  assert_php_lints "$php_file"
done

if [[ "$failures" -ne 0 ]]; then
  exit 1
fi
