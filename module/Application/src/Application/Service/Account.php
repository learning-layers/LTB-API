<?php
namespace Application\Service;
use ZF\ApiProblem\ApiProblem;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Sql;
use Application\Shared\SharedStatic;
//use Zend\Db\Sql\Predicate;
//use Zend\Db\Sql\Where;
class Account {
    private $table_gateway_user = null;
    private $table_gateway_session = null;
    private $user = null;
    private $user_id = null;
    private $oid_id = null;
    private $token = null;
    private $oid_token = null;//might be the same as token depending on auth method
    private $auth_session_setting = '';    
    private $message = '';
    private $oidc_config = null;
    
    /*
     * We switched from a flow based on a code sent to the AuthController to an implicit authentication
     * where we have to check with every call the auth code sent along. Since we want to prevent extra traffic
     * to the oidc server during testing and to prevent to copy paste the right oidc auth token every time
     * we keep the implemented method
     */
    //when true, always the same constant will be 'generated' in the session generator
    const _CONSTANT_SESSION = _USE_SAME_SESSION_TOKEN;
    const _LAZY_AUTH_SESSION = _LAZY_AUTH_SESSION;
    const _CHECK_ACCESS_BY_INFO = TRUE;
    
    //Some roles we define
    const ANONYMOUS_ROLE_RANK = 0;
    const USER_ROLE_RANK      = 1;
    //const TEACHER_ROLE_RANK   = 2;
    const EVALUATOR_ROLE_RANK = 3;
    const MODERATOR_ROLE_RANK = 4;
    const ADMIN_ROLE_RANK     = 5;
    
    const ANONYMOUS_ROLE = '';
    const USER_ROLE      = 'user';
    //const TEACHER_ROLE   = 'teacher';
    const MODERATOR_ROLE = 'moderator';
    const EVALUATOR_ROLE = 'evaluator';
    const ADMIN_ROLE     = 'admin';
    
    public function __construct(TableGateway $tblgw_user=NULL, TableGateway $tblgw_session=NULL, $open_config=null){
        $this->table_gateway_user = $tblgw_user;
        $this->table_gateway_session = $tblgw_session;
        
        if ($open_config){
            $this->oidc_config = $open_config;
        } else {
            throw new \Exception ('Bad configuration: settings not passed to account object.');
        }
    }
    
    public function initialize(){
        $this->token = $this->user_id = $this->user = null;
    }
    
    public function getMessage(){
        return $this->message;
    }
    
    private function setMessage($m){
        $this->message .= " $m.";
    }
    
    /* Checks for the Bearer key by getting the session token which is checked for 
     * in the user_session table. If the session is found and it can be coupled
     * to the user table, the user info is kept in this object and true is 
     * returned. Otherwise it depends on the REST api call: soft can be true to 
     * allow anonymous read actions for example.
     */
    public function getAuth($event, $soft=FALSE, $token=''){
        $access_token = $this->getToken();
        if (!$access_token) {
            $access_token = $this->retrieveToken($event, TRUE, $token);
            if (FALSE === $access_token){
                $this->setMessage('No valid token sent. Either expired or non-existent.');
                return FALSE;
            } elseif ($access_token === '') {
                return $soft;
            }
        }
        
        if ($this->user){
            //TODO does this ever occur? It seems the user property is only set after this function
            //Perhaps if an api-function is called from within another api-function and this same Account
            //object is created/retrieved from the factory
            
            //User was already known
           return TRUE;
        } else {
            
            //NOT IMPLICIT means the endpoint auth was sent a code and the user details 
            //were retrieved during login and stored. If on the contrary IMPLICIT is true,
            //tokens might be sent to the API that never passed there before.
            //Such a token can be valid, non existent or expired. If though, the
            // Lazy_authorisation is OFF, such a token was not stored at all and 
            // so we should check every call.
            if (self::_LAZY_AUTH_SESSION && (! _IMPLICIT)) {
                $test = $this->testUserFromCache($access_token, $soft);
                if ($test == 'OK'){
                    return TRUE;
                } elseif ($test == 'NOT OK'){
                    return FALSE;
                } else {
                    //User sends an invalid token that is not recognised at all. This means that
                    //since we expected it in the cache as we applied the old auth method via the
                    //auth RPC, we do not accept this as a valid user
                    $this->setMessage("This was not a valid user. Token sent ".
                        (_DEBUG ? "($access_token)": ""). " is not to be found");
                    return FALSE;
                }
            } else {
                //So we either have to check every call or we check first the 
                //cache and might find it necessary to go on testing
                if (self::_LAZY_AUTH_SESSION){
                    $test = $this->testUserFromCache($access_token, $soft);
                    if ($test == 'OK'){
                        return TRUE;
                    } elseif ($test == 'NOT OK'){
                        return FALSE;
                    } else {//test = 'TEST'
                        //User sends an invalid token that is not recognised at 
                        //all, so it can be that the user sends the token for 
                        //the first time and it was not stored nor checked yet.
                    }
                }
                //When we arrive here, we know that the user is not (yet) in the 
                //cache or will never be 
                $oid_token = $this->getOpenIdToken();
                if ($result = $this->checkAccessToken($oid_token)){
                   if (self::_CHECK_ACCESS_BY_INFO){
                        //TODO SharedStatic::doLogging('Heeft vanuit checkAccessToken al de volgende user info '.print_r($this->user, 1));
                        if (!isset($this->user['name'])){
                            @$this->user['name'] = ($this->user['given_name']." ".
                                $this->user['family_name']);
                        }
                        //Store user info, token and new session in table
                        //In checkAccessToken the user is already stored
                        return TRUE;
                   } else {
                       //It might be that the user is only allowed because of the
                       //setting that soft is TRUE and in that case the user is 
                       //not set yet. It might be set if: (the _LAZY_AUTH_SESSION 
                       //check returns FALSE or we arrive here via the case that
                       //the testUserFromCache returned test == 'TEST'
                        if (! $this->user){
                            //Haal user op
                            $user_info = $this->openIdUserinfo($oid_token);
                            if ($user_info){
                                //SharedStatic::doLogging('user info '.print_r($user_info, 1));
                                if (!isset($user_info['name'])){
                                    @$user_info['name'] = $user_info['given_name']." ".$user_info['family_name'];
                                }
                                //Store user info, token and new session in table
                                $session_token = $this->putUserInCache($oid_token, $user_info);
                                $stored_user = $this->getUserFromTable($oid_token, '', $user_info['sub']);
                                $this->user = array_merge($user_info, $stored_user);
                                return TRUE;
                            } else {
                                //This case should never occur: we retrieve an OK from OIDC, but just
                                //after the info cannot be retrieved
                                $result = FALSE;
                                $this->setMessage("This was a valid user. But".
                                    " we could not retrieve the proper".
                                    " information at the OIDC server");
                            } 
                        }
                   }
                } else {
                    $result = FALSE;
                    $this->setMessage("This was not a valid user. Token sent ".
                        (_DEBUG ? "($oid_token)":"")." is not accepted as valid by OIDC server");
                }
                return $result;
            } 
        }     
    }
    
    public function unAuthorisedObject($msg=''){
        return new ApiProblem(401, 
            ($msg ? "$msg. " : 'You are not authorised. '). ' '.$this->getMessage());
    }
    
    //TODO this function should go to a separate domain service that has access 
    //to the domain_user table 
    public function getUserDomains($user_id=0){
       if (!$user_id){
           $user_id = $this->getCurrentUserId();
       }
       if (!$user_id){
           return array();
       } else {
           return array('BAU-ABC');
       }
    }
    
    public function getUser($user_id){
        if (!($this->isAdmin() || ($this->getCurrentUserId() == $user_id))){
            $this->setMessage('You cannot ask the user details for a different '.
                'user if you are not admin');
        }
        return $this->getUserFromTable(null, $user_id);
    }
    
    public function userRanking($role){
        $rank = self::ANONYMOUS_ROLE_RANK;
        if ($role) {
            switch ($role) {
                case self::USER_ROLE: $rank = self::USER_ROLE_RANK;break;     
                case self::ADMIN_ROLE: $rank = self::ADMIN_ROLE_RANK;break;
                case self::EVALUATOR_ROLE: $rank = self::EVALUATOR_ROLE_RANK;break;
                case self::MODERATOR_ROLE: $rank = self::MODERATOR_ROLE_RANK;break;
                //case self::TEACHER_ROLE: $rank = self::TEACHER_ROLE_RANK;break;
                default: 
                    $rank = self::ANONYMOUS_ROLE_RANK;
                    SharedStatic::doLogging('There is a user with a non-recognised role', $role);
                    break;
            }
        }
        
        return $rank;
    }
    
    public function isAdmin(){
        return $this->getCurrentUserId() == _MY_ID;
    }
    
    public function isModerator(){
        return ($this->user && $this->userRanking($this->user['role']) >= self::MODERATOR_ROLE_RANK);        
    }
    
    public function isEvaluator(){
        return ($this->user && $this->userRanking($this->user['role']) >= self::EVALUATOR_ROLE_RANK);        
    }
    
    public function isUser(){
        return ($this->user && $this->userRanking($this->user['role']) >= self::USER_ROLE_RANK);        
    }
    
    public function getCurrentUserId(){
        if (is_null($this->user_id)){
           if ($this->user && isset($this->user['user_id'])){
               $this->user_id = $this->user['user_id'];
            } else {
                $this->user_id = _SWITCH_OFF_AUTH_CHECK ? _MY_ID : 0;
            }
        }
        return $this->user_id;
    }
    
    public function getCurrentUserCode() {
        if ($this->user && $this->user['user_code']){
            return $this->user['user_code'];
        } else {
            return _SWITCH_OFF_AUTH_CHECK ? _MY_CODE : '';
        }
    }
    
    public function getCurrentOpenId(){
        if (is_null($this->oid_id)){
           if ($this->user 
                && $this->user['oid_id']){
                $this->oid_id = $this->user['oid_id'];
            } else {
                $this->oid_id = (_SWITCH_OFF_AUTH_CHECK ? _MY_OID_ID : 0);
            }
        }
        return $this->oid_id;
    }
    
    public function getOpenIdToken(){
        if (is_null(@$this->oid_token)){
           if ($this->user && @$this->user['oid_token']){
                $this->oid_token = $this->user['oid_token'];
            } else {
                $this->oid_token = "";
            }
        }
       return $this->oid_token; 
    }
    
    public function getToken(){
       return $this->token; 
    }
    
    public function getCurrentUserName(){
        return $this->user && $this->user['name'] ? $this->user['name'] : '';
    }
    
    public function getCurrentUserInfo(){
        return $this->user;
    }
    /* These functions get current info based on the authorization token only. 
     * They can be called whenever the event is known and are not dependent on
     * the user object or token being calculated in getAuth for example
     */
    public function getCurrentRefreshToken($event=''){
      if (_STORE_USER_SESSION){
        $user = $this->getCurrentUser($event);
        return $user && $user['refresh_token'] ? $user['refresh_token'] : '';
      } else {
          $this->setMessage('The current refresh token was requested, but no '.
              'such information is stored according to the settings.');
          return '';
      }
    }
    
    /* This function can only be called if _STORE_USER_SESSION is on
     * otherwise the user cannot be retrieved based on the token. Currently
     * this is the case everywhere.
     */
    public function getCurrentUser($event=''){
        if (_STORE_USER_SESSION){
            $token = $this->getCurrentPassedToken($event);
            $user = $token ? $this->getUserFromTable($token) : false;
            $this->user = $user ?: false;
            return $this->user;
        } else {
            return false;
        }
    }
    
    /* TODO not used at the moment */
    public function getCurrentOidToken($event=''){
        $u = $this->getCurrentUser($event);
        return $u ? $u['oid_token'] : '';
    }
    
    //Check Functions
    public function checkAuthentication(){
        return ($user_id = $this->getCurrentUserId());
    }
    
    public function checkOwner($obj, $fld, $user_id=0){
        $user_id = $user_id ?: $this->getCurrentUserId();
        return ($user_id && (($user_id == $obj->$fld) || $this->isModerator()) );
    }
    
    /* Expects a token which might be the oidc token or a session token */
    private function checkAccessToken($token, $verbose=FALSE){
        if (self::_CHECK_ACCESS_BY_INFO) {
            $info = $this->openIdUserinfo($token, $verbose);
            if (!$info || isset($info['error'])){
                isset($info['error']) && $this->setMessage('Got back from Oidc: '.$info['error']);
                return FALSE;
            } else {
                $user_from_db = $this->getUserFromTable($token, '', $info['sub']);
                SharedStatic::doLogging('hoe zit het in checkAccessToken', array($info, $user_from_db));
                if (!$user_from_db){
                    $this->putUserInCache($token, $info);
                    if (!$user_from_db = $this->getUserFromTable($token, '', $info['sub'])){
                       return FALSE; 
                    }
                }
                $this->user = array_merge($info, $user_from_db);
                return TRUE;
            }
        }
    }
    
    /* gets, based on a (valid) auth code, the user information belonging to
     * the user of that active session at the oid server.
     */
    public function openIdUserinfo($token, $verbose = false){
        $oidc = new \ltbapi\V2\Rpc\Auth\OIDC($this->oidc_config);
        return $oidc->connectOIDC("OidcEndpointUserinfo", 'AUTHORIZATION', null, $token);
    }
    
    public function storeUserInfo($user_data, $user_id=0){
        try {
            if ($user_id){
                $this->table_gateway_user->update($user_data, array('user_id' => $user_id));
                return $user_id;
            } else {
                $this->table_gateway_user->insert($user_data);
                $uid = $this->table_gateway_user->getLastInsertValue();
                $user_code = SharedStatic::getShortCode($uid);
                $this->table_gateway_user->update(array('user_code' => $user_code),
                   array('user_id' => $uid));
                return $uid;
            }
        } catch (\Exception $ex) {
            $this->setMessage('Could not store the user: '.$ex->getMessage());
            return FALSE;
        }
    }
    
    public function putUserInCache($oid_token, $user, $code='', $refresh_token=''){
        $session_token = _STORE_USER_SESSION ? $this->generateToken() : 'dummy';
        $existing_session = FALSE; //initialisation
        $expired_session = FALSE;
        
        $inserting = FALSE;
        try {
            SharedStatic::doLogging("Arriving in putUserInCache (oid token, user"
                . "code refresh", array($oid_token, $user, $code, $refresh_token));
            //Get the OIDC id which comes either from the database or from the returned
            //result from an info request to the OIDC server like in an authentication
            //process
            $oid_id = SharedStatic::altSubValue($user, 'sub', 
                SharedStatic::altSubValue($user, 'oid_id', 0));
            if (!$oid_id){
                throw new \Exception('The user has no id attached. Cannot put such a user in the cache'. print_r($user, 1), 500);
            }

            //First handle the user storage
            //Update the user table with the $u to be inserted or updated
            $result = $this->table_gateway_user->select(array('oid_id' => $oid_id));
            if ($result->count()){
                $existing_user = $result->current();
                $uid = $existing_user->user_id;
                
                if ($existing_user['expire'] <= time()){
                    $update = array(
                        'name' => $user['name'], 
                        'email'  => $user['email'],
                        'username' => (isset($user['preferred_username'])?
                            $user['preferred_username'] : $user['email']), 
                        'expire' => time() + (3600 * _EXPIRE_USER_HOURS)
                    );
                    $this->table_gateway_user->update($update, array('user_id' => $uid));
                }
                SharedStatic::doLogging("Hij heeft een user gevonden hoor $uid");
                    
            } else {
                SharedStatic::doLogging("Hij heeft geen user gevonden");
                $inserting = TRUE;
                $u = array(
                        'oid_id' => $oid_id, 
                        'name' => $user['name'],
                        'email' => $user['email'], 
                        'username' => (isset($user['preferred_username'])?
                            $user['preferred_username'] : $user['email']), 
                        'expire' => time()+ (3600 * _EXPIRE_USER_HOURS)
                );
                SharedStatic::doLogging("Gaat gebruiker opslaan ", $u);
                $uid = $this->storeUserInfo($u);
                SharedStatic::doLogging("Hij heeft een user opgeslagen met  $uid]");
            }
            
            if (_STORE_USER_SESSION) {
                if (!$inserting){
                    $where = "user_id = $uid AND oid_token = '$oid_token'";
                    $existing_session = $this->table_gateway_session->select(
                        $where)->toArray();
                    if ($existing_session){
                        $expired_ts = $existing_session[0]['expire'];
                        $expired_session = ($existing_session && ($expired_ts < time()));
                    } else {
                        $expired_session = TRUE;
                    }
                }
                
                $us = array(
                    'oid_token' => $oid_token, 
                    'session_token' => $session_token,
                    'expire' =>time() + (3600 * _EXPIRE_OIDC_HOURS),
                    'user_id' => $uid
                );
                if ($code){
                    $us['oid_code'] = $code;
                } else {
                    $us['oid_code'] = 'dna';
                }
                if ($refresh_token){
                    $us['refresh_token'] = $refresh_token;
                }
                if ($inserting || (! $existing_session) || $expired_session){
                //The user may have logged out and logged in again retrieving a new token
                //within the expire time, so to be sure, we have to insert with every token
                //we receive
                    $this->table_gateway_session->insert($us);
                } else {
                    unset($us['expire']);
                    $this->table_gateway_session->update($us,
                        array('user_id'=>$uid, 'expire' => $expired_ts));
                }
                if (! _KEEP_USER_SESSION){
                    //DELETE FROM user_session where expire <= time() for this user
                    SharedStatic::doLogging("Gaat deleten met user_id = $uid AND expire <= ".time());
                    $this->table_gateway_session->delete("user_id = $uid AND expire <= ".time());
                }
            }
        } catch (\Exception $ex){
            return new ApiProblem(500, 'Could not store logged in user : '.
                (_DEBUG ? $ex->getMessage(): ''));
        }
        return $session_token;
    }
    
    private function getCurrentPassedToken($event=''){
        if (!$token = $this->getToken()) {
            $this->retrieveToken($event, false);
            if (!($token = $this->getToken())) {
                $this->setMessage("No identification provided (got $token). Cannot identify user of request.");
                return '';
            }
      }
      return $token;
    }
    
    /* 
     * Either:
     * - send the oid token with Bearer, _IMPLICIT = TRUE
     * - send the session token with LocalBearer , _IMPLICIT = TRUE
     * - send the session token with Bearer , _IMPLICIT = FALSE
     * @param $check_expired should a check on a valid entry (not expired) been 
     * done. Interesting to switch off if we just want to retrieve the current token and
     * inspect it like in @see getCurrentPassedToken.
     * 
     */
    private function retrieveToken($event='', $check_expired=TRUE, $token=''){
        //TODO this case should not occur
        if (!$event) {
            $this->token = '';
            throw new \Exception('Program Error: No event passed');
        }
        $this->auth_session_setting = 'local';
        
        if ($token){
            //Some token has been provided by parameter. Calculate further with that one
            $auth_value = $token;
        } else {
            $requestHeaders  = $event->getRequest()->getHeaders();
            $auth = $requestHeaders->get('Authorization');
            $auth_value = $auth ? $auth->getFieldValue() : '';        
        }
        
        if (!$auth_value){
            $this->token = $this->oid_token = '';
            $this->setMessage('There is no Authorization header or it is empty.');
        } else {
            if (strpos($auth_value, 'LocalBearer') !== FALSE){
                $this->setMessage('Receiving local Authorization header: '.$auth_value);
                $this->token = str_replace('LocalBearer ', '', $auth_value);
                //If Lazy is not on, the token will be translated to a connected oidc token
                //which will be tested later on anyway, so it is only
                //an error if the user cannot be found as we would expect that.
                if (!self::_LAZY_AUTH_SESSION){
                    //Make translation directly and continue as if we sent the oidc_token
                    $user_result = $this->getUserFromCache($this->token);
                    if (! $user_result){
                        $this->setMessage("You are sending a local token for which we ".
                            "cannot find a user.");
                        //Invalid token
                        return FALSE;
                    }
                    //So we have a possibly expired user account
                    list($not_expired, $user) = $user_result;
                    
                    if (!$not_expired && $check_expired){
                        //It is expired and we are expected to guard that
                        $this->setMessage("You are sending a local token for which we ".
                            "already know that it is expired");
                        return FALSE;
                    }
                    $this->token = $this->oid_token = $user['oid_token'];
                    $this->auth_session_setting = 'oid';
                    $this->setMessage("Validness $not_expired en ".print_r($user, 1).gettype($user).'verwacht goed token: '.$this->token);
                }
            } else {
                $this->token = str_replace('Bearer ', '', $auth_value);
                //In case the token is sent by tilestore, it will be an oidc token
                //if we send it by postman, we can choose to send a session token 
                //as well to make lif easier, but it must be send then as LocalBearer
                $this->oid_token = $this->token;
                $this->auth_session_setting = 'oid';
            }
        }
        
        return $this->token;
    }
    
    private function generateToken(){
        if (self::_CONSTANT_SESSION){
            return self::_CONSTANT_SESSION;
        } 
        return md5(time().rand(1,1000));
    }
    
    private function testUserFromCache($access_token, $soft){
        if ($result = $this->getUserFromCache($access_token)){
            //Check cache for valid user based on token
            list($not_expired, $user_record) = $result;
            if ($not_expired){
                $this->user = $user_record;
                return 'OK';
            } else {
                $this->initialize();
                $this->setMessage('Session key expired');
                return $soft ? 'OK' : 'NOT OK';
            }
        } else {
            //User sends an invalid token that is not recognised at all, so it can be that the 
            //user sends the token for the first time and it was not stored nor checked yet
            return 'TEST';
        }
    }
    
    private function getUserFromCache($token){
        $user = $this->getUserFromTable($token);
        if ($user){
            //If not expired the expire date will lay in the future
            return array(($user['session_expire'] > time()), $user);
        }
        //So we can test in if statements on ! getUserFromCache == did not exist
        return FALSE;
    }
    
    private function getUserFromTable($token='', $user_id='', $oid_id='') {
        $adapter = $this->table_gateway_user->getAdapter();
        $sql = new Sql($adapter);
        $table = 'user';
        $session_table = 'user_session';
        $where = array();
        $selectObj = $sql->select($table);
        
        $unique_selector_provided = FALSE;
        if ($user_id){
            $where["$table.user_id"] = $user_id;
            $unique_selector_provided = TRUE;
        }
        if ($oid_id) {
            $where["$table.oid_id"] = $oid_id;
            $unique_selector_provided = TRUE;
        }
        if (_STORE_USER_SESSION){
            $on_spec = "$table.user_id = $session_table.user_id";
            if ($token){
                $token_field = ($this->auth_session_setting == 'local') ?
                    "session_token" : "oid_token";
                $where["$session_table.$token_field"] = $token;
                $unique_selector_provided = TRUE;
            } elseif (! $unique_selector_provided){
                //In case we wanted to find the user based on the token, but it is not provided
                //there is nothing we can return
                $this->setMessage('We were looking for a user session, but no token was provided');
                return FALSE;
            }

            $fields = array('oid_token', 'oid_code', 'refresh_token', 
                'session_token', 'session_expire' => 'expire');
            $selectObj->join(array($session_table => $session_table), $on_spec,
                $fields, $selectObj::JOIN_LEFT);
            $selectObj->order(array("$session_table.expire" => 'DESC'));
        }
        if (! $unique_selector_provided){
            throw new \Exception($this->translate(
                    'The user could not be retrieved based on the parameters provided'));
        }
        $selectObj->where($where);
        
        try {
          //Since we use a join, we cannot use the standard tablegateway method:
          //     $rowset  = $this->tableGateway->selectWith($selectObj);
           $statement = $sql->getSqlStringForSqlObject($selectObj);
           SharedStatic::doLogging("Executed $statement");
           $rowset = $adapter->query($statement, $adapter::QUERY_MODE_EXECUTE);
        } catch (\Exception $e) {
            $statement = 'Either not defined yet or db statement failed.';
            if (_DEBUG){
                
                die($e->getMessage() . "<br/>Executed Sql: $statement");
            } else {
                throw new \Exception($this->translate('Some database error occured.'));
            }
        }
        
        $users = $rowset->toArray();
        $user = $users ? $users[0] : false;
        if ($user && !_STORE_USER_SESSION){
            $user['oid_token'] = $token;
        }
        return $user;
    }
    
    private function translate($s){
        return $s;
    }
}