<?php
namespace ltbapi\V2\Rest\Embed;

class EmbedResourceFactory
{
    public function __invoke($services)
    {
        $account = $services->get('Application\Service\Account');
        $config = $services->get('Config');
        $apikey = isset($config['Embed_apikey']) ? $config['Embed_apikey'] : '';
        return new EmbedResource($apikey, $account);
    }
}