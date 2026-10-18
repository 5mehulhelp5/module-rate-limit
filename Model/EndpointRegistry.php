<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model;

/**
 * The endpoints that can be rate-limited. Open: any module adds its own in its di.xml, see docs/endpoints.md.
 *
 * An endpoint is either a storefront controller (`controller`: the action class, subclasses match too) or a REST
 * service (`service`: the interface named in webapi.xml, `methods`: its methods). Entries whose class does not exist
 * (the module is not installed) or that are malformed are left out, so the registry never depends on a module.
 *
 * @api the "definitions" di.xml argument is a stable extension point
 */
class EndpointRegistry
{
    /** Prefix of the backpressure request type ids, so they cannot collide with Magento's own. */
    public const TYPE_PREFIX = 'ck-rate-limit/';

    /** @var array<string, array{label: string, controller?: string, service?: string, methods?: string[]}>|null */
    private ?array $endpoints = null;

    /** @param array<string, mixed> $definitions di.xml `definitions`, keyed by endpoint id */
    public function __construct(
        private readonly array $definitions = []
    ) {
    }

    /** @return array<string, array{label: string, controller?: string, service?: string, methods?: string[]}> */
    public function getAll(): array
    {
        if ($this->endpoints === null) {
            $this->endpoints = [];
            foreach ($this->definitions as $id => $definition) {
                $endpoint = is_string($id) && is_array($definition) ? $this->normalize($id, $definition) : null;
                if ($endpoint !== null) {
                    $this->endpoints[$id] = $endpoint;
                }
            }
        }
        return $this->endpoints;
    }

    public function has(string $id): bool
    {
        return isset($this->getAll()[$id]);
    }

    public function getLabel(string $id): string
    {
        return $this->getAll()[$id]['label'] ?? $id;
    }

    /** The endpoint id of a storefront action, or null when it is not registered. */
    public function getIdForController(object $action): ?string
    {
        foreach ($this->getAll() as $id => $endpoint) {
            if (isset($endpoint['controller']) && $action instanceof $endpoint['controller']) {
                return $id;
            }
        }
        return null;
    }

    /** The endpoint id of a REST service method, or null when it is not registered. */
    public function getIdForService(string $service, string $method): ?string
    {
        $service = ltrim($service, '\\');
        foreach ($this->getAll() as $id => $endpoint) {
            if (($endpoint['service'] ?? null) === $service && in_array($method, $endpoint['methods'] ?? [], true)) {
                return $id;
            }
        }
        return null;
    }

    public function getTypeId(string $id): string
    {
        return self::TYPE_PREFIX . $id;
    }

    /** The endpoint id of one of our backpressure request types, or null for Magento's and other modules' types. */
    public function getIdForTypeId(string $typeId): ?string
    {
        if (!str_starts_with($typeId, self::TYPE_PREFIX)) {
            return null;
        }
        $id = substr($typeId, strlen(self::TYPE_PREFIX));
        return $this->has($id) ? $id : null;
    }

    /**
     * @param array<mixed> $definition
     * @return array{label: string, controller?: string, service?: string, methods?: string[]}|null
     */
    private function normalize(string $id, array $definition): ?array
    {
        $label = $definition['label'] ?? null;
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $id) || !is_string($label) || trim($label) === '') {
            return null;
        }
        $controller = $definition['controller'] ?? null;
        $service = $definition['service'] ?? null;
        if (is_string($controller) && $service === null) {
            $controller = ltrim($controller, '\\');
            return class_exists($controller) ? ['label' => $label, 'controller' => $controller] : null;
        }
        if (is_string($service) && $controller === null) {
            $service = ltrim($service, '\\');
            $methods = array_values(array_filter(
                is_array($definition['methods'] ?? null) ? $definition['methods'] : [],
                static fn ($method) => is_string($method) && $method !== ''
            ));
            return $methods && (interface_exists($service) || class_exists($service))
                ? ['label' => $label, 'service' => $service, 'methods' => $methods]
                : null;
        }
        return null;
    }
}
