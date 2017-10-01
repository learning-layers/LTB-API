<?php
$dbParams = 
    array(
    'hostname' => $instance_hostname,
    'database' => $instance_db,
    'username' => $instance_db_user,
    'password' => $instance_db_pwd,
);

$redirect_uri = "${instance_uri_api}auth";
$web_agent_uri = "${instance_uri_ts}index.html#/";
$app_agent_uri = "${instance_uri_ts}www/index.html#/"; //"myapp://"; //TODO: this should be filled in

//Some constants on access
define('_PUBLIC_LEVEL_PRIVATE', 0);
define('_PUBLIC_LEVEL_PUBLIC', 1);
define('_PUBLIC_LEVEL_HIDDEN', 2);
define('_ACCESS_LEVEL_ANONYMOUS', 0);
define('_ACCESS_LEVEL_USER', 1);
define('_ACCESS_LEVEL_DOMAIN', 2);

define('_UNIX_LOG', $instance_unix_log);
define('_WIN_LOG', $instance_win_log);
define('_API_HOME', $instance_home_dir);
define('_LTBAPI_SERVICE_DIR', '/module/ltbapi/src/ltbapi/V2');
define('_SFS_FILE_LOCATION', _API_HOME."/files/");
define('_API_URI', $instance_uri_api);
if (!defined('_STORE_USER_SESSION')){
    define('_STORE_USER_SESSION', TRUE);
}
////Do not change from here. This is what is expected as config array in the rest of
//the application. Note that the non-apigility (own settings) start with uppercase.
$derived_settings = array(
    'This_server' => array(
        'DefaultStack' => $instance_default_stack,
        'ApiUri' => $instance_uri_api,
        'TsUri' => $instance_uri_ts,
        'BoxLabel' => $instance_box_label,
        'BoxId' => $instance_box_id,
        'Unsplash_apikey' => $unsplash_api_key,
        'Pixabay_apikey' => $pixabay_api_key,
        'ApiVersion' => $api_version,
        'ApiScriptsVersion' => $api_scripts_version,
        //'ApiDbVersion' => $api_db_version
    ),
    'Embed_apikey' => $embedly_api_key,
    'Openid_server' => array(
        'AuthProviderName' => $instance_oidc_provider_name,
        'ClientID' => $oidc_client_id,
        'ClientSecret' => $oidc_client_secret,
        'Provider' => $instance_oidc_provider,
        'Provider_path' => $instance_oidc_provider_path,
        'Scope' => $instance_oidc_scope,
        'Refresh_access_type' => $instance_oidc_refresh_access_type,
        'OidcEndpointToken' => $instance_oidc_endpoint_token,
        'OidcEndpointUserinfo' => $instance_oidc_endpoint_userinfo,
        'OidcEndpointLogout' => $instance_oidc_endpoint_logout,
        'RedirectUri' => $redirect_uri,
        'WebAgent' => $web_agent_uri,
        'AppAgent' => $app_agent_uri,
    ),
    'SocialSemantic_server' => array(
        'default_version' => $instance_sss_default_version,
        'version' => (isset($instance_sss_version) && ($instance_sss_version !== 'REPLACE:SSS_VERSION') ?
            $instance_sss_version : $instance_sss_default_version),
        'api_server' => $instance_uri_api,
        'get_target' => $instance_sss_target,
        'post_target' => $instance_sss_target,
        'auth' => 'oidc',
    ),
    'service_manager' => array(
        'factories' => array(
            'Zend\Db\Adapter\Adapter' => function ($sm) use ($dbParams) {
                return new Zend\Db\Adapter\Adapter(array(
                    'driver' => 'pdo',
                    //Note that we need to specify the charset in the connection to store 4-bytes chars correctly
                    'dsn' => 'mysql:dbname=' . $dbParams['database'] . ';host=' . $dbParams['hostname'] . ';charset=utf8',
                    'database' => $dbParams['database'],
                    'username' => $dbParams['username'],
                    'password' => $dbParams['password'],
                    'hostname' => $dbParams['hostname'],
                ));
            },
        ),
    ),
);
?>