# Rate limiting<a href="https://commercekings.net"><picture><source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/commercekings/module-rate-limit/main/.github/commercekings-dark.svg"><img align="right" width="152" src="https://raw.githubusercontent.com/commercekings/module-rate-limit/main/.github/commercekings-light.svg" alt="Built by CommerceKings. Ready to sell more? Talk to us"></picture></a>

Works with Magento 2.4.7+ · PHP 8.2–8.5 · Redis or Valkey

[![License](https://badgen.net/github/license/commercekings/module-rate-limit)](LICENSE) [![Release](https://badgen.net/github/release/commercekings/module-rate-limit)](https://github.com/commercekings/module-rate-limit/releases) [![Checks](https://github.com/commercekings/module-rate-limit/actions/workflows/checks.yml/badge.svg?branch=main)](https://github.com/commercekings/module-rate-limit/actions/workflows/checks.yml)

**Stop scripts from hammering your storefront endpoints – with Magento's own rate limiting, set up in the admin.**

- **Built on Magento.** Uses Magento's backpressure (the mechanism behind its rate limit for placing orders) – no
  new counters, no new tables.
- **Clean 429.** Limited requests get `HTTP 429 Too Many Requests` – for REST and for storefront controllers, where
  Magento alone would answer with an error page.
- **Your limits.** Per endpoint: requests per guest IP, per signed-in customer, per minute, hour or day. An IPv6
  guest counts per /64 network, so rotating addresses inside it does not help.
- **Blocklist.** IP addresses and CIDR ranges that get 429 while rate limiting is enabled – also without Redis.
- **Open.** Any module can add its endpoints in its `di.xml` ([how](docs/endpoints.md)).

## Installation

```sh
composer require commercekings/module-rate-limit:^0.1
bin/magento setup:upgrade
```

It needs Magento 2.4.7 or later.

## How it fits in

**Optional, in both directions.** The CommerceKings feature modules (consent, notification, abandoned cart) do not
require it and contain no rate-limiting code: without it they work the same, on Magento 2.4.4+. It does not require
them either: it names their endpoints by class name only, and skips an endpoint whose module is not installed – no
error, the row is just not offered. Installed together, their endpoints are listed automatically.

**Where the configuration comes from** – only from this module and from Magento, never from the features' settings
or data (subscriptions, consents):

| What | Source | Set by |
|---|---|---|
| Which endpoints can be limited | the endpoint registry in `di.xml` (this module's, plus any module that adds its own) | developers |
| Limits and blocked IP addresses | **Stores > Configuration > CommerceKings > Rate limiting** (`commercekings_rate_limit/*`) | admins |
| Where requests are counted | `backpressure/logger` in `app/etc/env.php` (Redis or Valkey) | developers / hosting |
| Who the client is | Magento: the customer id when signed in, otherwise the client IP address | Magento |

A limited request is answered with 429 before the endpoint runs; the feature never sees it. Removing the module
returns every endpoint to Magento's normal handling; its saved settings are then ignored.

## Settings

**Stores > Configuration > CommerceKings > Rate limiting** (website scope):

| Field | Default |
|---|---|
| Enabled | Yes – without the counter store nothing is counted, only the blocklist applies |
| Limits | One row per endpoint – see below; endpoints without a row are not limited |
| Blocked IP addresses | empty |
| Status | Whether Magento can count requests (the backpressure logger) |

Suggested limits, filled in for the CommerceKings endpoints that are installed:

| Endpoint | Per guest IP | Per customer | Per |
|---|---|---|---|
| Notifications: subscribe (`POST /V1/ck-notification/subscriptions`, `/mine`) | 10 | 10 | minute |
| Notifications: consent for an address (`POST /V1/ck-notification/address-consents`, `/mine`) | 10 | 10 | minute |
| Consent: record a choice (`/ckconsent/grant`) | 30 | 30 | minute |
| Abandoned cart: restore link (`/cartreminder/cart/restore`) | 10 | 10 | minute |

Stopping emails has no suggested limit – a refused "no" would keep emails going: the cookie banner's withdrawal
(`/ckconsent/withdraw`), the email withdrawal link (`/ckconsent/optout`) and the Do not contact page
(`/cknotification/stop`). Mail providers also send one-click unsubscribes from shared servers (RFC 8058), so a
limit per IP address could drop a shopper's unsubscribe. They are listed in the admin; add a rule only if you need
one.

## Before going live

- Add the backpressure logger to `app/etc/env.php` – without it Magento cannot count and only the blocklist works
  ([how](docs/operations.md#counter-store)).
- Make sure Magento sees the shopper's IP address, not your proxy's – or guests share one counter, or a script
  fakes a new address per request. *Status* warns when Magento sees a private or local address,
  and when it takes the address from a forwarded header ([how](docs/operations.md#client-ip-address)).
- Keep a rule in your web server, proxy or CDN as the first line of defense; this module is the second.

## Extending

Any module can put its endpoints under the same limits with a few lines of `di.xml` and no PHP (the `definitions`
argument of `Model\EndpointRegistry` is `@api`, a stable extension point). The endpoint then appears in the admin
table and gets the limits, the blocklist and the 429. How, and how to handle 429 in your storefront code:
[endpoints](docs/endpoints.md).

## Limitations

- Counts requests, not their content: a script spread over many IP addresses stays under a per-IP limit. Use the
  features' own limits too (e.g. the notification cooldown, abandoned cart's hourly limit).
- Storefront controllers and REST only; GraphQL is not covered.
- Guests are counted per IP address as Magento resolves it – see the client IP note above.

## More

[Operations](docs/operations.md) · [Endpoints for developers](docs/endpoints.md) ·
[Compatibility](COMPATIBILITY.md) · [Changes](CHANGELOG.md)

## Need help installing or setting it up?

**[Get a quote →](https://commercekings.net)**
