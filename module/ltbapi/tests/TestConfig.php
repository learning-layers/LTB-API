<?php
/* based on: http://framework.zend.com/manual/2.0/en/user-guide/unit-testing.html */

return array(
    'modules' => array(
        'ltbapi',
    ),
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            '../../../config/autoload/{,*.}{global,local}.php',
        ),
        'module_paths' => array(
            'module',
            'vendor',
        ),
    ),
);

