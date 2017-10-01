<?php
namespace ltbapi\V2\Rpc\Auth;

class AuthControllerFactory {
    
    public function __invoke($controllers){
        //It seems that since $controllers is a Zend\Mvc\Controller\ControllerManager which is an extension
        //of the ServiceManager (extends ServiceManager implements ServiceLocatorAwareInterface), we can 
        //use getServiceLocator to get other services and inject them.
        $sl = $controllers->getServiceLocator();
        $config  = $sl->get('Config');
        $account = $sl->get('Application\Service\Account');
        
        $open_config = isset($config['Openid_server']) ? $config['Openid_server'] : array();
        return new AuthController($open_config, $account);
    }
}