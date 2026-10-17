# Compatibility and verification

The first-release runtime target is Magento Open Source 2.4.9 with PHP 8.5.
The Composer PHP range is a dependency constraint, not a claim that every
Magento/PHP combination has passed runtime testing.

**Minimum: Magento Open Source 2.4.7**, enforced by `magento/framework: ^103.0.7`. Magento added its backpressure
(rate limiting) framework in 2.4.7. This module builds on classes that Magento does not mark `@api`
(`Magento\Framework\App\Backpressure\*`, `App\Request\Backpressure\*`, `Webapi\Backpressure\*`,
`App\Request\BackpressureValidator`); recheck them with each Magento release.

| Magento Open Source | PHP | Runtime verification |
| --- | --- | --- |
| 2.4.9 | 8.5 | Passed locally: Composer, DI, unit tests, PHPStan level 5, coding standard, live checks below |
| 2.4.7–2.4.8 | 8.2–8.4 | Unverified; use a matching Magento dependency set and services |

Live checks on 2026-10-17 in the CommerceKings sandbox (Magento 2.4.9, Valkey as the counter store): a storefront
controller (`/ckconsent/grant`) answered 429 with a JSON body after its limit, where Magento alone answered HTTP 500;
the REST subscribe endpoint answered Magento's 429 after its limit; blocked addresses got 429 from the first request,
also without the counter store; without the counter store, limited endpoints stayed available and nothing was
logged; normal pages were not affected; the admin table rendered, refused an invalid limit and saved a valid one.

The GitHub workflow checks package metadata, PHP syntax on 8.1–8.5 and Git archive
contents. Runtime support for other Magento versions remains unverified.
