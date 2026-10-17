<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Model;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedAction;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedActionInterceptor;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedServiceInterface;
use CommerceKings\RateLimit\Test\Unit\Fixture\OtherAction;
use PHPUnit\Framework\TestCase;

class EndpointRegistryTest extends TestCase
{
    private function registry(): EndpointRegistry
    {
        return new EndpointRegistry([
            'limited_action' => ['label' => 'Limited action', 'controller' => '\\' . LimitedAction::class],
            'limited_service' => [
                'label' => 'Limited service',
                'service' => LimitedServiceInterface::class,
                'methods' => ['create', '', 42],
            ],
            'missing_module' => ['label' => 'Not installed', 'controller' => 'Vendor\\Missing\\Controller\\Index'],
            'no_label' => ['controller' => LimitedAction::class],
            'Bad-Id' => ['label' => 'Bad id', 'controller' => LimitedAction::class],
            'both' => [
                'label' => 'Both',
                'controller' => LimitedAction::class,
                'service' => LimitedServiceInterface::class,
                'methods' => ['create'],
            ],
            'no_methods' => ['label' => 'No methods', 'service' => LimitedServiceInterface::class],
            'not_an_array' => 'oops',
        ]);
    }

    public function testKeepsOnlyWellFormedEndpointsOfInstalledModules(): void
    {
        $this->assertSame(
            [
                'limited_action' => ['label' => 'Limited action', 'controller' => LimitedAction::class],
                'limited_service' => [
                    'label' => 'Limited service',
                    'service' => LimitedServiceInterface::class,
                    'methods' => ['create'],
                ],
            ],
            $this->registry()->getAll()
        );
    }

    public function testMatchesTheActionAndItsInterceptor(): void
    {
        $registry = $this->registry();
        $this->assertSame('limited_action', $registry->getIdForController(new LimitedAction()));
        $this->assertSame('limited_action', $registry->getIdForController(new LimitedActionInterceptor()));
        $this->assertNull($registry->getIdForController(new OtherAction()));
    }

    public function testMatchesOnlyTheListedServiceMethods(): void
    {
        $registry = $this->registry();
        $this->assertSame('limited_service', $registry->getIdForService(LimitedServiceInterface::class, 'create'));
        $this->assertSame(
            'limited_service',
            $registry->getIdForService('\\' . LimitedServiceInterface::class, 'create')
        );
        $this->assertNull($registry->getIdForService(LimitedServiceInterface::class, 'read'));
        $this->assertNull($registry->getIdForService('Magento\\Quote\\Api\\CartManagementInterface', 'create'));
    }

    public function testRequestTypeIdsAreOursAndRoundTrip(): void
    {
        $registry = $this->registry();
        $this->assertSame('ck-rate-limit/limited_action', $registry->getTypeId('limited_action'));
        $this->assertSame('limited_action', $registry->getIdForTypeId('ck-rate-limit/limited_action'));
        $this->assertNull($registry->getIdForTypeId('ck-rate-limit/missing_module'));
        $this->assertNull($registry->getIdForTypeId('quote-order'));
        $this->assertNull($registry->getIdForTypeId('limited_action'));
    }

    public function testLabels(): void
    {
        $this->assertSame('Limited action', $this->registry()->getLabel('limited_action'));
        $this->assertSame('unknown', $this->registry()->getLabel('unknown'));
    }
}
