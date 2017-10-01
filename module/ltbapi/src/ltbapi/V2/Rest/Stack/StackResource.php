<?php
namespace ltbapi\V2\Rest\Stack;
use Application\Listener\MyAbstractResourceListener;
use Application\Shared\SharedStatic;
class StackResource extends MyAbstractResourceListener
{
    protected $end_point = 'Stack';
    protected $log_id_name = 'stack_code';
    protected $defined_methods = array('create', 'delete', 'deleteList', 
        'fetch', 'fetchAll', 'patch', 'update'
         //,'patchList','replaceList'
    );
    
    protected $access_check_postponed = array('fetchAll' => TRUE, 'fetch' => TRUE);
    //The user is either logged in and can do some read only actions. For those actions
    //the isAuthorised will be weakly (softly) applied, for the other actions a valid
    //user id (<>0) will be necessary. In the table class we can assume the check to be done 
    //and some user id (possibly 0) be known.
    
    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = null){
        $param_array = $params ? $params->getArrayCopy() : null;
        $soft = $this->methods_access[__FUNCTION__] || _SWITCH_OFF_AUTH_CHECK;
        $unauth_msg = '';
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog(__FUNCTION__, $soft, $user_id, 0, $params, $granted);
        //TODO: perhaps remove the test parameter. It is not used anymore, but could it
        //be usefull in the future?
        $return_401 =  !isset ($param_array['test']);unset($param_array['test']);
        $return_401 = FALSE;
        return (!$return_401 && $granted) 
            ? $this->table_object->fetchSelection($user_id, $param_array) 
            : $this->account->unAuthorisedObject($unauth_msg) ;
    }
 
    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data){
       $soft = $this->methods_access[__FUNCTION__] || _SWITCH_OFF_AUTH_CHECK;
        
       $user_id = $this->isAuthorised($soft);
       $granted = (FALSE !== $user_id);
       $unauth_msg = 'You should be logged in to modify a stack';
       SharedStatic::debugLog("StackResource: 109: In patch voor $id", $data);
       $this->userLog(__FUNCTION__, $soft, $user_id, $id, $data, $granted);
        return $granted 
            ? $this->table_object->update($user_id, $id, $data) 
            : $this->account->unAuthorisedObject($unauth_msg) ;
        //patching gave problems, so we do an update instead with a special parameter
        //to check whether it was actually a patch
        //$this->table_object->patch($user_id, $id, $data);
    }
}