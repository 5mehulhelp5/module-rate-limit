<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Fixture;

use Magento\Framework\App\ActionInterface;

/** A storefront action registered in the tests' endpoint registry. */
class LimitedAction implements ActionInterface
{
    public function execute()
    {
        throw new \BadMethodCallException('Not dispatched in unit tests.');
    }
}
