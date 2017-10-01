<?php
namespace ltbapi\V2\Rpc\File;

class FileControllerFactory
{
    public function __invoke($controllers)
    {
        $sl = $controllers->getServiceLocator();
        $account = $sl->get('Application\Service\Account');
        return new FileController($account);
    }
}
