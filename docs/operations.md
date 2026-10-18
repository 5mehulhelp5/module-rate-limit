# Operations

Running `commercekings/module-rate-limit`. Start with the [README](../README.md).

Two layers, use both where you can: a rule in front of Magento (web server, proxy or CDN) stops a flood before it
reaches PHP; this module is the second layer, set up in the admin, and the one that knows signed-in customers.

## Counter store

Magento counts requests in Redis or Valkey, configured in `app/etc/env.php` (a database number of its own is
clearer):

```sh
bin/magento setup:config:set --backpressure-logger=redis \
    --backpressure-logger-redis-server=<host> --backpressure-logger-redis-port=6379 \
    --backpressure-logger-redis-db=<free db number>
```

The admin *Status* line shows whether a logger is configured. Without one, limits are off (Magento could not count
and would log an error for every request), and only the blocklist applies.

## Client IP address

Guests are counted per IP address, and Magento takes it from `X-Forwarded-For` by default – the first address in the
header, whoever sent it. A script can then claim a new address per request. Make sure Magento is reachable only
through your proxy, and either let the proxy *replace* (not append to) `X-Forwarded-For`, or declare your proxies
so Magento takes the last address before them – exact IPs, no ranges:

```xml
<!-- di.xml of a project module -->
<type name="Magento\Framework\HTTP\PhpEnvironment\RemoteAddress">
    <arguments>
        <argument name="trustedProxies" xsi:type="array">
            <item name="lb1" xsi:type="string">10.0.0.10</item>
        </argument>
    </arguments>
</type>
```

If Magento sees your proxy's address instead, all guests share one counter and hit the limit together. Signed-in
customers are counted per customer id.

## Blocked IP addresses

One IPv4/IPv6 address or CIDR range per line, e.g. `203.0.113.7` or `198.51.100.0/24`. While rate limiting is
enabled these get `429` on every registered endpoint – also without the counter store. Invalid entries are refused
on save. Behind a CDN, block at the CDN where you can.

## In front of Magento

nginx example for the notification sign-up and address consent endpoints (keep your usual Magento REST handling in the location):

```nginx
# http {}
limit_req_zone $binary_remote_addr zone=ck_subscribe:10m rate=10r/m;

# server {}
location ~ ^/rest/[^/]+/V1/ck-notification/(subscriptions|address-consents) {
    limit_req zone=ck_subscribe burst=5 nodelay;
    limit_req_status 429;
    try_files $uri $uri/ /index.php$is_args$args;
}
```

`$binary_remote_addr` must be the shopper: behind a load balancer or CDN restore the client IP first
(`set_real_ip_from`, `real_ip_header`). A CDN or proxy rule does the same job (e.g. a Cloudflare rate limiting rule,
a Fastly rate limiter or Varnish `vsthrottle`).

## Choosing limits

Count what one honest shopper can send: a checkout subscribes a few times per minute at most; a consent banner sends
one request per changed choice. The suggested limits leave room for a household behind one IP address. Raise a
limit if real shoppers see 429 in your access logs.
