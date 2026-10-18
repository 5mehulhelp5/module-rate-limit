# Endpoints for developers

Adding endpoints to `commercekings/module-rate-limit`. Start with the [README](../README.md).

The endpoint registry is open (`@api`: the `definitions` argument of `Model\EndpointRegistry`): a module adds its own
endpoints in its `etc/di.xml`, and they appear in the admin
table under **Stores > Configuration > CommerceKings > Rate limiting > Limits**. They then get the same limits per
guest IP and per customer, the IP blocklist and the HTTP 429 answer as the CommerceKings endpoints.

## A storefront controller

```xml
<type name="CommerceKings\RateLimit\Model\EndpointRegistry">
    <arguments>
        <argument name="definitions" xsi:type="array">
            <item name="vendor_newsletter_subscribe" xsi:type="array">
                <item name="label" xsi:type="string">Newsletter: subscribe</item>
                <item name="controller" xsi:type="string">Vendor\Newsletter\Controller\Subscriber\NewAction</item>
            </item>
        </argument>
    </arguments>
</type>
```

`controller` is the action class. Subclasses match too, including Magento's generated interceptors. Magento's own
endpoints can be listed the same way from a project module (e.g. `Magento\Customer\Api\AccountManagementInterface`
with `isEmailAvailable`). A limited controller answers with JSON, so AJAX endpoints suit best: a classic full-page
form post would show the JSON body as the page.

## A REST service

```xml
<item name="vendor_quote_request" xsi:type="array">
    <item name="label" xsi:type="string">Quote request: send</item>
    <item name="service" xsi:type="string">Vendor\QuoteRequest\Api\RequestManagementInterface</item>
    <item name="methods" xsi:type="array">
        <item name="guest" xsi:type="string">sendGuest</item>
        <item name="customer" xsi:type="string">sendCustomer</item>
    </item>
</item>
```

`service` and `methods` are the class and methods named in your `webapi.xml`. GraphQL is not covered.

## Rules

- **Id** (the item name): lower case letters, digits and `_`, at most 64 characters, unique. It is stored in the
  admin table and in the counter key, so keep it stable. Prefix it with your vendor.
- **Label**: shown in the admin dropdown; translatable like any phrase.
- **Exactly one** of `controller` or `service` + `methods`.
- Entries are matched by class name strings. If the class does not exist (your module is not installed), the entry
  is left out silently: no DI error, and no Composer dependency in either direction. Malformed entries are left
  out the same way.
- Add `<module name="CommerceKings_RateLimit"/>` to your module's `<sequence>` only if you need the order; the
  registry does not.
- An endpoint is limited only while rate limiting is enabled and the shop added a row for it (suggest values in
  your documentation). Without a row it costs nothing: no counter, no Redis call.

## What a limited request gets

- **REST:** Magento's own `429 Too Many Requests`.
- **Controllers:** `429` with `{"message": "Too Many Requests"}`. Magento's controller backpressure alone throws an
  exception that ends in an HTTP 500 error page; this module replaces it for registered endpoints only. Treat 429
  as temporary in your storefront code (retry later, keep the user's input).

The request type id in Magento's backpressure is `ck-rate-limit/<id>`; Magento's own types (`quote-order`, …) are
untouched.

## Keep it optional for your module

Classes are matched by name, so your module needs no dependency on this one; suggest it in your `composer.json`:

```json
"suggest": {
    "commercekings/module-rate-limit": "Rate limits for the quote request endpoint (Magento 2.4.7+)"
}
```

Don't register a webhook that a service calls only once (e.g. an inbound SMS reply): a 429 is not sent again, so
the message – a STOP, say – would be lost. Protect it with the service's signature instead.

## Handle 429 in your storefront code

Keep the shopper's input and ask them to try again shortly:

```js
const response = await fetch('/acme_quote/request/send', {method: 'POST', body: data});
if (response.status === 429) {
    showMessage('Too many requests – please try again in a minute.');
    return;
}
```

## Check that it works

Set a low limit on your endpoint (e.g. 3 per minute), then send a few requests – the fourth gets 429:

```sh
for i in 1 2 3 4; do
  curl -s -o /dev/null -w '%{http_code}\n' -X POST -H 'X-Requested-With: XMLHttpRequest' \
    https://shop.example/acme_quote/request/send
done
```

Without the counter store in `app/etc/env.php` every request passes – the admin *Status* line tells you which.
