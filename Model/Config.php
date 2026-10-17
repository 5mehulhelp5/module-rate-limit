<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model;

use CommerceKings\RateLimit\Model\Config\Source\Period;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/** Stores > Configuration > CommerceKings > Rate limiting, read for the current store (website scope). */
class Config
{
    public const XML_PATH_ENABLED = 'commercekings_rate_limit/general/enabled';
    public const XML_PATH_RULES = 'commercekings_rate_limit/general/rules';
    public const XML_PATH_BLOCKED_IPS = 'commercekings_rate_limit/general/blocked_ips';

    /** @var array<string, array{guest_limit: int, limit: int, period: int}>|null */
    private ?array $rules = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json,
        private readonly Period $periods
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /** The limit of an endpoint, or null when the admin table has no valid row for it. */
    public function getRule(string $endpointId): ?array
    {
        if ($this->rules === null) {
            $this->rules = $this->parseRules(
                (string)$this->scopeConfig->getValue(self::XML_PATH_RULES, ScopeInterface::SCOPE_STORE)
            );
        }
        return $this->rules[$endpointId] ?? null;
    }

    public function getBlockedIps(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_BLOCKED_IPS, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Rows of the admin table (serialized by Magento's ArraySerialized backend); invalid rows are skipped, so a bad
     * value can never block an endpoint.
     *
     * @return array<string, array{guest_limit: int, limit: int, period: int}>
     */
    public function parseRules(string $value): array
    {
        try {
            $rows = $value === '' ? [] : $this->json->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }
        $rules = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $endpoint = is_array($row) ? ($row['endpoint'] ?? null) : null;
            if (!is_string($endpoint) || isset($rules[$endpoint])) {
                continue;
            }
            $guest = $this->positive($row['guest_limit'] ?? null);
            $limit = $this->positive($row['limit'] ?? null);
            $period = (string)($row['period'] ?? '');
            if ($guest !== null && $limit !== null && in_array($period, $this->periods->getValues(), true)) {
                $rules[$endpoint] = ['guest_limit' => $guest, 'limit' => $limit, 'period' => (int)$period];
            }
        }
        return $rules;
    }

    private function positive(mixed $value): ?int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int)$value > 0 ? (int)$value : null;
    }
}
