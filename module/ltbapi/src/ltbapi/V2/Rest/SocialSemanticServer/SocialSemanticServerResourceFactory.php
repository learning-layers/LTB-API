<?php
namespace ltbapi\V2\Rest\SocialSemanticServer;

class SocialSemanticServerResourceFactory
{
    public function __invoke($services)
    {
        $account = $services->get('Application\Service\Account');
        $sss = $services->get('Application\Service\SocialSemanticConnector');
        return new SocialSemanticServerResource($account, $sss);
    }
}
