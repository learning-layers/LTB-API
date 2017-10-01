<?php
namespace ltbapi\V2\Rpc\Debug;

class DebugControllerFactory
{
    public function __invoke($controllers) {
        //It seems that since $controllers is a Zend\Mvc\Controller\ControllerManager which is an extension
        //of the ServiceManager (extends ServiceManager implements ServiceLocatorAwareInterface), we can 
        //use getServiceLocator to get other services and inject them.
        $sl = $controllers->getServiceLocator();
        $account = $sl->get('Application\Service\Account');
        return new DebugController($account);
    }
}
