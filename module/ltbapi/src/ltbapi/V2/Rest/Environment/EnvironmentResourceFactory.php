<?php
namespace ltbapi\V2\Rest\Environment;

class EnvironmentResourceFactory
{
    public function __invoke($services)
    {
        $config  = $services->get('Config') ?: array();
        $account = $services->get('Application\Service\Account');
        return new EnvironmentResource($config, $account);
    }
}
