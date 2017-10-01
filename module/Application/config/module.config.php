<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            'file' => array(
                'type' => 'Segment',
                'options' => array(
                    'route'    => '/file[/:ref_code][/:file_dir][/:file_name]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\File',
                        'action'     => 'file',
                    ),
                ),
                'may_terminate' => true,
            ),
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'application' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/application',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/][:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                               'action'      => 'get',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Db\Adapter\AdapterAbstractServiceFactory',
            //'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'factories' => array(
            'Application\Service\Account' => function ($sm) {
                $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
				$user_table = new \Zend\Db\TableGateway\TableGateway('user', $dbAdapter);
                $user_session_table = new \Zend\Db\TableGateway\TableGateway('user_session', $dbAdapter);
                $config = $sm->get('Config');
                $open_config = isset($config['Openid_server']) ? $config['Openid_server'] : array();
                return new Application\Service\Account($user_table, $user_session_table, $open_config);
            },
            'Application\Service\SocialSemanticConnector' => function ($sm) {
                $config_global = $sm->get('Config');
                $conf = $config_global['SocialSemantic_server'];
                return new Application\Service\SocialSemanticConnector($conf);
            },
            'Application\Service\Logging' => function ($sm) {
                return new Application\Service\Logging();
            }
        ),
        'aliases' => array(
            'translator' => 'MvcTranslator',
        ),
    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Application\Controller\Index' => 'Application\Controller\IndexController',
            'Application\Controller\File' => 'Application\Controller\FileController',
            'Application\Controller\Log' => 'Application\Controller\LogController',
        ),
    ),
    'controller_plugins' => array(
        'invokables' => array(
        )
//        ,
//        'factories' => array(
//            'translate' => 'Application\Controller\Plugin\Translate'
//        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
