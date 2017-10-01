<?php

/* 
 * based on: http://framework.zend.com/manual/2.0/en/user-guide/unit-testing.html
 * 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace ltabapitests;

use ltbapitests\Bootstrap;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use ltabapi\V2\Rest\Stack\StackResource;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use PHPUnit_Framework_TestCase;

class StackResourceTest extends \PHPUnit_Framework_TestCase
{
    

    protected function setUp()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $this->request    = new Request();
        //$this->routeMatch = new RouteMatch(array('controller' => 'index'));
        $this->event      = new MvcEvent();
        $config = $serviceManager->get('Config');
        $routerConfig = isset($config['router']) ? $config['router'] : array();
        $router = HttpRouter::factory($routerConfig);

        $this->event->setRouter($router);
        $this->event->setRouteMatch($this->routeMatch);
        //$this->controller->setEvent($this->event);
        //$this->controller->setServiceLocator($serviceManager);
      
        
    }
    
    public function testFetchAll()
    {
        $response = $this->StackResource->fetchAll();
        $this->assertEquals(200, $response->getStatusCode());
    }
}