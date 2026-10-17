# Changelog

## Unreleased

First release. Features:

- Rate limiting on Magento's backpressure for storefront controllers and REST services, configured per endpoint
  under **Stores > Configuration > CommerceKings > Rate limiting**: requests per guest IP, per signed-in customer,
  per minute, hour or day. Off by default.
- Open endpoint registry in `di.xml`; ships entries for the CommerceKings notification, consent and abandoned cart
  endpoints, matched by class name so none of those modules is required.
- Suggested limits for the CommerceKings endpoints, except the email withdrawal link: one-click unsubscribes come from
  mail providers' shared servers and must not be limited per IP.
- HTTP 429 with a JSON body for limited storefront controllers, instead of Magento's HTTP 500.
- IP blocklist (IPv4/IPv6, CIDR) on registered endpoints, also without the counter store. IPv4 and IPv4-mapped IPv6
  (`::ffff:203.0.113.7`) match each other, as dual-stack servers may report either.
- Without the counter store in `app/etc/env.php`, only the blocklist applies (no error per request); the admin
  status line says so.
- Requires Magento 2.4.7 or later (`magento/framework ^103.0.7`).

No public release has been tagged or published yet.
