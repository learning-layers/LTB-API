<?php
namespace ltbapi\V2\Rpc\Embedly;

class EmbedlyControllerFactory
{
    public function __invoke($controllers)
    {
        $sl = $controllers->getServiceLocator();
        $config = $sl->get('Config');
        $account = $sl->get('Application\Service\Account');
        $apikey = isset($config['Embed_apikey']) ? $config['Embed_apikey'] : '';
        return new EmbedlyController($apikey, $account);
    }
}