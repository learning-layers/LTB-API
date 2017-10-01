<?php
namespace ltbapi\V2\Rpc\Show;

class ShowControllerFactory
{
    
    public function __invoke($controllers) {
        //It seems that since $controllers is a Zend\Mvc\Controller\ControllerManager which is an extension
        //of the ServiceManager (extends ServiceManager implements ServiceLocatorAwareInterface), we can 
        //use getServiceLocator to get other services and inject them.
        $sl = $controllers->getServiceLocator();
        $config = $sl->get('Config');
        return new ShowController($config['This_server']);
    }
}