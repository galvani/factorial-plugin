<?php

namespace MauticPlugin\MauticFactorialBundle\Tests\Stubs;

use MauticPlugin\MauticFactorialBundle\Integration\CrmAbstractIntegration;

class StubIntegration extends CrmAbstractIntegration
{
    public function getName()
    {
        return 'Stub';
    }
}
