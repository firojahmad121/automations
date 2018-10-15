<?php

namespace Webkul\UVDesk\AutomationBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Webkul\UVDesk\AutomationBundle\DependencyInjection\UVDeskExtension;

class UVDeskAutomationBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new UVDeskExtension();
    }
}
