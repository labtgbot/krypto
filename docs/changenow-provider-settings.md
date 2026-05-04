# ChangeNOW Provider Settings

Related issue: https://github.com/labtgbot/krypto/issues/6

## Admin Setup

Create or sign in to a ChangeNOW Business or partner account before enabling the provider in Krypto. ChangeNOW documents the public API key in the Business Account Dashboard or Profile Details, and the partner API page also points admins to Profile Details for the key:

- https://support.changenow.io/hc/en-us/articles/22686553746204-Integration-API-setup-and-customization
- https://changenow.io/api/docs/support

Copy the public API key into Krypto. If ChangeNOW enables private account, reporting, refund, webhook, or `v2/exchanges` features for the account, also copy the private API key and callback secret supplied through the ChangeNOW partner setup process. ChangeNOW notes that private API keys are generated from the partner's GPG public key and are issued once per business account.

In Krypto, open the admin payment settings and configure the ChangeNOW provider section:

- Keep `Enable ChangeNOW provider` off until the account credentials and defaults are configured.
- Store the public API key, private API key, and callback secret only in the server-side settings form. Existing values are shown as `*********************` and are preserved when that mask is submitted.
- Set the enabled flows to `standard`, `fixed-rate`, or both. The default flow is used by later swap screens when a user has not chosen a flow.
- Set default source and destination assets and networks with ChangeNOW asset/network codes, such as `btc` on `btc` and `eth` on `eth`.
- Use the referral link ID or widget link ID provided by ChangeNOW when partner attribution or widget routing is enabled for the account.
- Keep the default rate limits at 30 requests per second and 1800 requests per minute unless ChangeNOW assigns different limits to the partner account.

## Live Swap Guard

Server-side ChangeNOW swap creation must call `App::_validateChangeNowLiveSwapSettings()` before creating a live transaction. The guard blocks live creation when the provider is disabled or when required settings, such as the public API key, are missing. This lets fresh installs and disabled-provider deployments keep loading dashboards and public pages without attempting live ChangeNOW calls.
