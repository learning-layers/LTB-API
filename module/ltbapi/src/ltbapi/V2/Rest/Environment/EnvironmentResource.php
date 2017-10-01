<?php
namespace ltbapi\V2\Rest\Environment;

use ZF\ApiProblem\ApiProblem;
//use ZF\Rest\AbstractResourceListener;

class EnvironmentResource extends \Application\Listener\MyAbstractResourceListener
{
    private $openid_config;
    private $this_server_config;
    private $valid = TRUE;
    protected $defined_methods = array('fetch');
    protected $end_point = 'Environment';
    
    public function __construct($config, $account){
        
        if (! isset($config['Openid_server']) || ! isset($config['This_server'])){
            $this->valid = FALSE;
        } else {
            $this->openid_config = $config['Openid_server'];
            $this->this_server_config = $config['This_server'];
            parent::__construct(null, $account);
        }
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id Will be the subset of the environment to deliver
     * @return ApiProblem|mixed
     */
    public function fetch($subset){
        $method = __FUNCTION__;
        if (! $this->valid){
            return new ApiProblem(500, 'The environment was not properly configured. Please inform the admin of the Learning Toolbox.');
        }
        if ($subset === 'server' || $subset === 'my') {
            $environment = array(
                'auth_provider_name' => $this->openid_config['AuthProviderName'],
                'auth_provider'  => $this->openid_config['Provider'],
                'auth_provider_path' => $this->openid_config['Provider_path'],
                'auth_scope' =>  $this->openid_config['Scope'],
                'auth_access_type' => $this->openid_config['Refresh_access_type'],
                'auth_clientid'  => $this->openid_config['ClientID'],
                'auth_logout' => $this->openid_config['OidcEndpointLogout'],

                //The api_uri is already known at the client side but we include it 
                //for completeness.
                'api_uri' => $this->this_server_config['ApiUri'],
                'default_stack' => $this->this_server_config['DefaultStack'],
                'box_label' => $this->this_server_config['BoxLabel'],
                'box_id' => $this->this_server_config['BoxId'],
                'unsplash_api_key' => $this->this_server_config['Unsplash_apikey'],
                'pixabay_api_key' => $this->this_server_config['Pixabay_apikey']
            );
        } elseif ($subset === 'apps') {
            //the other option is that we have apps
            ////First check the authentication
            $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
            $user_id = $this->isAuthorised($soft);
            if (FALSE === $user_id) {
                return $this->account->unAuthorisedObject();
            } else {
                //read json file
                $json_str = file_get_contents(_API_HOME ._LTBAPI_SERVICE_DIR.'/Rest/Environment/appsAvailable.json');
                $environment = json_decode($json_str);
            }
        } else {
            return $this->returnResourceProblem(500,
                "You have asked for a subset ($subset) of the environment definition that does not exist.", 'Unknown environment option', array($subset));
        }
        return array('result'=> $environment);
    }
    
    /* This function creates a tableGateway to the log table of the database and stores the action
     * the user has initiated
     */
    public function userLog($method, $soft, $user_id, $id=0, $params=null, $granted=TRUE){
        //Skip this function. We do not log actions.
    }
}
