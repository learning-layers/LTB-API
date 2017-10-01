<?php
namespace ltbapi\V2\Rpc\Auth;

use Application\Shared\SharedStatic;
use Zend\Mvc\Controller\AbstractActionController;
//use ZF\Rpc\RpcController;
/*
 * This class Services the requests from the OpenID where a login is initiated from the client and after
 * a good login (we do not assume that yet so we are a bit too cautious maybe), the OpenID sends a redirect to 
 * the client which will bring the client here. It is like a request from the client saying: 'I just logged in, 
 * got this code and I want to go here, please trust me. Then the AuthController will check the code and send
 * back a valid token to use trhoughout the lifetime of the session.
 */
class AuthController extends AbstractActionController //RpcController //
{
    private $message = '';
    
    public function __construct($open_config='', $account){
        $this->account = $account;
        $this->oidc = new OIDC($open_config);
    }
    
    public function authAction(){
        //Get code and state from GET
        $code = $this->params()->fromQuery('code', '');
        $state = $this->params()->fromQuery('state', '');
        //state is a base64 encoded json struct with info the client saved to
        //handle this authentiction call and to know where the call originated
        //and possibly where to go, etc. Just send back the state later, for now 
        //we only need the key, if present
        
        //TODO anders Ik denk dat dit onderscheid niet nodig is. INloggen via www
        //in de browser gaat om andere redenen fout
        
        $return_url = $this->oidc->web_agent_uri;
        if ($state) {
            $state_struct = json_decode(base64_decode($state), TRUE);
            if ($state_struct){
                $key = SharedStatic::altSubValue($state_struct, 'key', '');
                $state_return_url = SharedStatic::altSubValue($state_struct, 'state', '');
                if ($state_return_url){
                    $return_url = preg_replace('/\#\/.*/', '#/', $state_return_url);
                }
            } else {
                $key = '';
                $return_url = urldecode($state);
            }
        } else {
            $key = '';
        }
        
        //Send out code to OpenID to check and receive token
        //Send back 302 (header location call) + token in the url to the client
        if (! $code){
            $error_descr = $this->params()->fromQuery('error_description', '');
            $error_descr = $error_descr ? "($error_descr).":"";
            echo 'The OpenID server did not return a valid auth code for the '.
                'credentials provided. Check the parameters the user agent provided, '.
                "stem with the called registered client at OIDC. $error_descr";
            exit();
        }
        
        //Get user id information first time client comes back
        if ($token_struct = $this->getAccessToken($code)){
            //Get user info
            $user_info = $this->openIdUserinfo($token_struct['access_token']);
            
            if (!isset($user_info['name'])){
                $name = $user_info['given_name']." ".$user_info['family_name'];
                $user_info['name'] = $name;
            }
            SharedStatic::doLogging('hier token struct en userinfo', array($token_struct, 
                $user_info));
            
            //Send back the original state
            $token_struct['state'] = $state;
            if (isset($token_struct['refresh_token']) || $key){
                $aes = new AES();
            }
            
            if (isset($token_struct['refresh_token']) && $token_struct['refresh_token']){
                $refresh = $token_struct['refresh_token'];
                $token_struct['refresh_token'] =
                    $aes->cryptoJsAesEncrypt($this->oidc->client_secret,
                        $token_struct['refresh_token']);
            } else {
                $refresh = '';
            }
            //Store user info, token and new session in table
            $this->account->putUserInCache(
                $token_struct['access_token'], $user_info, $code, $refresh);
            
            if ($key){
                $encoding = 
                $query_str = "&auth_enc=" . $aes->cryptoJsAesEncrypt($key, $token_struct);
            } else {
                $query_str = http_build_query($token_struct);
            }
            
            //Redirect to client app: the Location directive will automatically
            //set the http status to 302
            header ("Location: ${return_url}${query_str}");
            exit;
        } else{
            $err_str = 'You do not seem to be known with the code provided. '.
                'Open ID Server could not identify you or went down.'.
                //TODO: remove this when out of test phase
                (_DEBUG ? " We got for the code [$code] no valid token back. " : ""). 
                (_DEBUG ? $this->message : "")
                .$this->oidc->getMessage();
            
            header ("Location: $return_url&auth_error=".  base64_encode($err_str));
            exit;
        }
    }
    
    public function refreshAction(){
        $aes = new AES();
        $state = $this->params()->fromQuery('state', '');
        if ($state) {
            $state_struct = json_decode(base64_decode($state), TRUE);
            if ($state_struct){
                $key = SharedStatic::altSubValue($state_struct, 'key', '');
            } else {
                $key = '';
            }
        } else {
            $key = '';
        }
        $refresh_token = $this->params()->fromQuery('refresh_token', '');
        if (_STORE_USER_SESSION) {
            $event = $this->getEvent();
        }
        if ($refresh_token) {
            $refresh_token = $aes->cryptoJsAesDecrypt($this->oidc->client_secret, $refresh_token);
        } elseif (_STORE_USER_SESSION){
            //Note that in this case we need a valid (but possibly expired) user session
            //to know which refresh token to use
            
            $refresh_token = $this->account->getCurrentRefreshToken($event);
            if (!$refresh_token){
                return new \Zend\View\Model\JsonModel(array(
                    'result'=>false, 
                    'message'=> 
                        $this->account->getMessage(). 
                         "A refresh token is obligatory to refresh your access token ".
                        "and it was not found in the tables.",
                    'status' => 406));
            }
        } else {
            return new \Zend\View\Model\JsonModel(array(
                    'result'=>false, 
                    'message'=>  
                         "A refresh token is obligatory to refresh your access ".
                         "token: provide in your url: refresh_token=...",
                    'status' => 406));
        }
        
        $data = array(
            'grant_type'=>'refresh_token',
            'refresh_token' => $refresh_token
            //, perhaps scope => not including the refresh itself
        );
        
        //Do the call
        try {
            $new_tokens = $this->getRefreshToken($data, true);
        } catch (\Exception $e){
            return \Application\Shared\SharedStatic::returnApiProblem($e->getCode(), 
                $e->getMessage(), 'Could not refresh the keys:', 'OIDC');
        }
        
        if ($new_tokens && ! isset($new_tokens['error'])){
            if ($new_tokens['refresh_token'] && ($new_tokens['refresh_token'] !== $refresh_token)){
                $store_refresh_token = $new_tokens['refresh_token'];
                $new_tokens['refresh_token'] = $aes->cryptoJsAesEncrypt(
                    $this->oidc->client_secret, $new_tokens['refresh_token']);
            } else {
                $store_refresh_token = $refresh_token;
                unset($new_tokens['refresh_token']);
            }
            
            //replace oid_token in user_session table            
            if (_STORE_USER_SESSION){
                //if previous_access_token_sent, update tables, otherwise we do not
                //know which user it was for
                if ($user = $this->account->getCurrentUser($event)){
                    $this->account->putUserInCache($new_tokens['access_token'],
                      $user, '', $store_refresh_token);
                }
            }
            
            //encrypt here the tokens
            if ($key){
                $return = array('tokens_enc' => $aes->cryptoJsAesEncrypt($key, $new_tokens));
            } else {
                $return = array('tokens' => $new_tokens);
            }
            return new \Zend\View\Model\JsonModel($return);
        } else {
            return new \Zend\View\Model\JsonModel(array(
                'result'=> false,
                'error'=> 'We could not retrieve the tokens '.
                (_DEBUG ? ("with ". print_r($data, 1)) : "").
                    ($new_tokens ? $new_tokens['error_description'] : '')));
        
        }
    }
    
    /* Get the information about a user and return it in JSON format
     * In the future this might be superfluous as we can send the name of the 
     * user to the client side right away and other info does not belong at the 
     * client side
     */
    public function profileAction() {
        $event = $this->getEvent();
        if ($this->account->getAuth($event, false)){
            if ($result = $this->account->getCurrentUserInfo()){
                unset($result['oid_token'], $result['oid_id'], $result['user_id'],
                    $result['session_token'], $result['refresh_token'], $result['expire']
                    , $result['session_expire'], $result['oid_code'], $result['sub']
                    , $result['preferred_username']);
                return new \Zend\View\Model\JsonModel($result);
            } else {
                $this->response->setStatusCode(500);
                return new \Zend\View\Model\JsonModel(
                    array(
                        'result'=>false, 
                        'error'=> "Bad user profile stored or not known.")
                );
            }
        } else {
            $this->response->setStatusCode(401);
            return new \Zend\View\Model\JsonModel(
                array('result'=> false, 'error'=> 'You are no longer logged in')
            );
        }
    }
    
    /* Gets the open id token of the current user that is logged in into the API
     * It gets it from the cache, if available. If getAuth was called before
     * the user might be set in the account object and there is no need to get
     * the user first. In that case check can be false.
     */
    private function getOpenIdToken($check=true, $event=''){
        if ($check){
            $event1 = $event ?: $this->getEventManager()->getEvent();
            if (!$this->account->getAuth($event1, false)){
                return '';
            }
        }
        return $this->account->getOpenIdToken();
    }    
    
    /* gets, based on a (valid) auth code, the user information belonging to
     * the user of that active session at the oid server.
     */
    private function openIdUserinfo($token, $verbose = false){
       return $this->oidc->connectOIDC("OidcEndpointUserinfo", 'AUTHORIZATION', null, $token, $verbose);
    }
    
    /* gets, based on a (valid) auth code, the user information belonging to
     * the user of that active session at the oid server.
     */
    private function getRefreshToken($data, $verbose = false){
        return $this->oidc->connectOIDC("OidcEndpointToken", 'BASIC', $data, '', $verbose);
    }
    
    private function getAccessToken($code, $verbose = false){
        $data = array(
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $this->oidc->redirect_uri    
        );
        
        $result = $this->oidc->connectOIDC("OidcEndpointToken", 'BASIC', $data, '', $verbose);
        
        if (! $result){
            $this->message = 'Could not connect well to OpenID server or got invalid data back: '.
                $this->oidc->getMessage();
            SharedStatic::doLogging($this->message);
            return FALSE;
        }
        //get tokens
        if (! ($access_token = SharedStatic::altSubValue($result, 'access_token',null))){
            $this->message = 'getAccessToken: No access_token received. Returning false.'.
                $this->oidc->getMessage();
            SharedStatic::doLogging($this->message);
            return FALSE;
        }
        
        SharedStatic::doLogging('In getAccessToken the oidc message:', $this->oidc->getMessage());
        $id_token = SharedStatic::altSubValue($result, 'id_token', null);
        $refresh_token = SharedStatic::altSubValue($result, 'refresh_token', null);
        //do some checks
        if($id_token){
            $id_array = explode(".", $id_token);
            $id_body = base64_decode($id_array[1]);
            $idb = json_decode($id_body, true);
            
            if ($idb['aud'] != $this->oidc->client_id && ($idb['aud'][0] && $idb['aud'][0] != $this->oidc->client_id)) {
                $this->message .= 'Client id passed does not stem with expected.'.print_r($idb['aud'], true).print_r($this->oidc->client_id , true);
                SharedStatic::doLogging('Client id passed does not stem with expected.');
                return FALSE;
            }
 
            if ($idb['exp'] < time()) {
                $this->message .= 'Expiration passed not valid anymore.';
                SharedStatic::doLogging('Expiration passed not valid anymore.');
                return FALSE;
            }
 
        } else {
            $this->message .= 'Returning false since id record was not set.';
            SharedStatic::doLogging('Returning false since id record was not set.');
            return FALSE;
        }
        //Checks done, return the token struct we got
        return $result;
    }
}