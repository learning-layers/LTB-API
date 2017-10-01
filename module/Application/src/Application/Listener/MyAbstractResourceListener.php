<?php
namespace Application\Listener;

use Application\Shared\SharedStatic;
use ZF\ApiProblem\ApiProblem;
use ZF\Rest\AbstractResourceListener;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class MyAbstractResourceListener extends AbstractResourceListener implements ServiceLocatorAwareInterface
{
    const _DEFAULT_SOFT_AUTH = FALSE;
    
    protected $table_object;
    protected $account;
    
    //If you want to disable methods, place this property in your subclass and
    //uncomment the ones you do not need
    protected $defined_methods = array(
        'create', 
        'delete', 
        'deleteList', 
        'fetch', 
        'fetchAll', 
        'patch',
        'patchList',
        'replaceList',
        'update'
    );
    protected $methods_access = null;//defined in constructor 
    protected $access_check_postponed = null;//Can be an array of methods in subclass
    public $unauth_messages = array(//default messages for unauthorised actions
        'create'     => 'You should be logged in to create an item',
        'delete'     => 'You should be logged in to delete an item.',
        'fetch'      => 'You should be logged in to retrieve an item',
        'fetchAll'   => 'You should be logged in to retrieve items',
        'patch'      => 'You should be logged in to adapt an item.',
        'update'     => 'You should be logged in to update an item.',
        'deleteList' => 'You should be logged in to delete a list of items',
        'patchList'  => 'You should be logged in to update a list of items'
    );
    
    public function __construct($table_object='', $account=''){
        
       $this->account = $account;
       if ($table_object){
           $this->table_object = $table_object;
           $this->table_object->setAccount($this->account);
       }
       $this->methods_access = array_fill_keys($this->defined_methods, self::_DEFAULT_SOFT_AUTH);
       if ($this->access_check_postponed){
           foreach ($this->access_check_postponed as $method => $access){
               $this->methods_access[$method] = $access;
           }
       }
    }
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    public function getServiceLocator()
    {
        return $this->services;
    }
    
    protected function getOtherService($service_factory_key){
        return $this->getServiceLocator()->get($service_factory_key);
    }
    
    public function notAuthorised($soft=FALSE, $msg=''){
       if ($this->account->getAuth($this->getEvent(), $soft)){
            return FALSE;
       } else {
           return $this->account->unAuthorisedObject($msg);
       } 
    }
    //TODO bettter naming would be getAuthenticatedUserId
    public function isAuthorised($soft=FALSE, $token=_TEST_TOKEN){
        //If _TEST_TOKEN is provided and not empty, it can act as if the user sent
        //LocalBearer Token. In that case this token is either looked up for the 
        //corresponding oid token (non lazy) or it just checks for expiredness
        //in case of the lazy method. In the latter case, if it is expired, results
        //can still be returned if soft is true.
       if ($this->account->getAuth($this->getEvent(), $soft, $token)){
           return $this->account->getCurrentUserid();
       } else {
           return FALSE;
       } 
    }
    
    public function getAccountMessages(){
        return $this->account->getMessage();
    }
    
    public function returnResourceProblem($status = 500, $detail = '', $title = '', $params=null, $type = null) {
        return \Application\Shared\SharedStatic::returnApiProblem($status, $detail, $title, $params, $type);
    }
    
    /**
     * This function creates a tableGateway to the log table of the database and
     * stores the action the user has initiated
     */
    public function userLog($method, $soft, $user_id, $id=0, $params=null, $granted=TRUE){
        $gateway = $this->getOtherService('user_log');
        SharedStatic::userLogStore($gateway, $this->end_point, $method, $soft,
            $user_id, $id, $params, $granted);
    }
    
    //API functions
    /*
     * Note the translations in the dispatch are
     *          entity          collection
     * POST     X                   create
     * GET      fetch (fetchOne)    fetchAll (fetchSelection)
     * PUT      update              replaceList
     * PATCH    patch               patchList
     * DELETE   delete              deleteList
     * 
     */
    
    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        if ($granted) {
            $result = $this->table_object->create($user_id, $data);
            $id_name = isset($this->log_id_name) ? $this->log_id_name : '';
            if ($result instanceof ApiProblem){
                SharedStatic::doLogging("There was a problem with $method for ".
                    "user $user_id with data:".print_r($data, 1), $result->toArray());
            } else {
                $this->userLog($method, $soft, $user_id, ($id_name ? $result['result']->$id_name : ''),
                    $result['result']);
            }
            return $result;
            
        } else {
           return $this->account->unAuthorisedObject($this->unauth_messages[$method]);
        }
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($id);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;       
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $params = $this->getMyParams();
        $this->userLog($method, $soft, $user_id, $id, $params, $granted);
        return $granted
            ? $this->table_object->delete($user_id, $id, $params) 
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog($method, $soft, $user_id, $id, NULL, $granted);
        return $granted 
            ? $this->table_object->fetchOne($id, $user_id)
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = array()){ 
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $param_array = $params ? $params->getArrayCopy() : null;
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog($method, $soft, $user_id, null, $params, $granted);
        return $granted  
            ? $this->table_object->fetchSelection($user_id, $param_array) 
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data){
       $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog($method, $soft, $user_id, $id, $data, $granted);
        return $granted 
            ?  $this->table_object->patch($user_id, $id, $data)
            :  $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function update($id, $data){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog($method, $soft, $user_id, $id, $data, $granted);
        return $granted 
            ? $this->table_object->update($user_id, $id, $data) 
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }
    
    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function deleteList($data){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }

        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        //It seems that the deleteList function in the AbstractResourcesListener is not
        //working with respect to the data sent along.
        $params = $this->getOtherParams();
        $this->userLog($method, $soft, $user_id, null, $params, $granted);
        return $granted 
            ? $this->table_object->deleteList($user_id, $params)
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);
    }
    
        /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function patchList($data){
        $method = __FUNCTION__;
        if (! in_array($method, $this->defined_methods)){
            return parent::$method($data);
        }
        $soft = $this->methods_access[$method] || _SWITCH_OFF_AUTH_CHECK;
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog($method, $soft, $user_id, null, $data, $granted);
        return $granted 
            ? $this->table_object->patchList($user_id, $data)
            : $this->account->unAuthorisedObject($this->unauth_messages[$method]);  
    }
    
    protected function getMyParams(){
        $event = $this->getEvent();
        $request = $event->getRequest();
        $content_type = $request->getHeader('Content-Type');
        $return = array();

        if ($content_type && $content_type->match('application/json')){
            $content_body = $request->getContent();
            $return = $content_body ? json_decode($content_body, true): array();
        } else {
            if ($request->isPost()){
                $return = $request->getPost()->toArray();
            } elseif ($request->isGet()){
                $return = $request->getQuery()->toArray();
            } else {
                $return = $this->getOtherParams($request);
            }
        }
        return $return;
    }

    //This function retrieves the get parameters but not like in getMyQueryParams
    //the request needn't be a GET.
    protected function getOtherParams($request=null){
        if (!$request){
            $event = $this->getEvent();
            $request = $event->getRequest();
        }
        $query = $request->getQuery();
        if ($query){
            return $query->toArray();
        } else {
            return false;
        }
    }
}