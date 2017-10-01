<?php
namespace ltbapi;

use ZF\Apigility\Provider\ApigilityProviderInterface;
use \Zend\Db\ResultSet\ResultSet;
use \Zend\Db\TableGateway\TableGateway;


class Module implements ApigilityProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/../../config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'ZF\Apigility\Autoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }
    
    public function getServiceConfig(){
		return array(
				'factories' => array(
					'ltbapi\V2\Rest\Stack\StackTable' => 
						function($sm) {
							list($obj, $tableGateway) = $sm->get('StackTableGateway');
                            $table = new V2\Rest\Stack\StackTable($tableGateway, $obj);
                            return $table;
						},
					'StackTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$resultSetPrototype = new ResultSet();
							$obj = new V2\Rest\Stack\StackEntity();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, $dbAdapter, null, $resultSetPrototype));
						},                         
                    'ltbapi\V2\Rest\Tag\TagTable' => 
						function($sm) {
							list($obj, $tableGateway) = $sm->get('TagTableGateway');
                            $table = new V2\Rest\Tag\TagTable($tableGateway, $obj);
                            return $table;
						},
					'TagTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$resultSetPrototype = new ResultSet();
							$obj = new V2\Rest\Tag\TagEntity();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, $dbAdapter, null, $resultSetPrototype));
						},
                    'ltbapi\V2\Rest\Message\MessageTable' => 
						function($sm) {
							list($obj, $tableGateway) = $sm->get('MessageTableGateway');
                            $table = new V2\Rest\Message\MessageTable($tableGateway, $obj);
                            return $table;
						},
					'MessageTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$resultSetPrototype = new ResultSet();
							$obj = new V2\Rest\Message\MessageEntity();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, $dbAdapter, null, $resultSetPrototype));
						},
                    'ltbapi\V2\Rest\Reference\ReferenceTable' => 
						function($sm) {
							list($obj, $tableGateway) = $sm->get('ReferenceTableGateway');
                            $table = new V2\Rest\Reference\ReferenceTable($tableGateway, $obj);
                            return $table;
						},
					'ReferenceTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$resultSetPrototype = new ResultSet();
							$obj = new V2\Rest\Reference\ReferenceEntity();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, $dbAdapter, null, $resultSetPrototype));
						},
                    'message_read' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							return new TableGateway('message_read', $dbAdapter);
						},
                            
                    'debug_verify' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							return new TableGateway('debug_verify', $dbAdapter);
						},
                    'debug_session' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							return new TableGateway('debug_session', $dbAdapter);
						},
                    'debug_record' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							return new TableGateway('debug_record', $dbAdapter);
						},
                    'user_log' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							return new TableGateway('user_log', $dbAdapter);
						},
                    'ltbapi\V2\Rest\Favourite\FavouriteTable' =>
                       function($sm) {
							list($obj, $tableGateway) = $sm->get('FavouriteTableGateway');
                            $table = new V2\Rest\Favourite\FavouriteTable($tableGateway, $obj);
                            return $table;
						},
					'FavouriteTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$resultSetPrototype = new ResultSet();
							$obj = new V2\Rest\Favourite\FavouriteEntity();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, 
                                $dbAdapter, null, $resultSetPrototype));
						},
                            
                    'ltbapi\V2\Rest\Profile\ProfileTable' =>
                       function($sm) {
							list($obj, $tableGateway) = $sm->get('ProfileTableGateway');
                            $table = new V2\Rest\Profile\ProfileTable($tableGateway, $obj);
                            return $table;
						},
					'ProfileTableGateway' =>
						function ($sm) {
							$dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
							$obj = new V2\Rest\Profile\ProfileEntity();
                            $resultSetPrototype = new ResultSet();
							$resultSetPrototype->setArrayObjectPrototype($obj);
							return array($obj, new TableGateway($obj->table, 
                                $dbAdapter, null, $resultSetPrototype));
						},
				),
		);
	}
}
