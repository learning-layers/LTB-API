<?php
namespace ltbapi\V2\Rest\SocialSemanticServer;

use ZF\ApiProblem\ApiProblem;
//use ZF\Rest\AbstractResourceListener;

class SocialSemanticServerResource extends \Application\Listener\MyAbstractResourceListener
{
    var $account = null;
    var $sss = null;
    protected $end_point = 'SocialSemanticServer';
    protected $defined_methods = array(
        //'create', 'delete', 'deleteList', 
        'fetch', 'fetchAll'
        //, 'patch', 'update'
        //,'patchList','replaceList'
    );
    public function __construct($account='', $sss='')
    {
       if (!$account || ! $sss ){
           throw new \Exception('To connect to the Social Semantic Server we need a valid connector and a valid account object');
       }
       $this->account = $account;
       $this->sss = $sss;
    }
    
    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        $soft = FALSE || _SWITCH_OFF_AUTH_CHECK;
        $unauth_msg = 'You should be logged in to retrieve items from Social Semantic Server.';
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog('fetch', $soft, $user_id, $id, null, $granted);
        if (!$granted) {
            return $this->account->unAuthorisedObject($unauth_msg);
        }
        //Only after checking the authorisation is it that we have also stored the tokens
        //Pass these tokens on to sss connector
        $this->sss->setOidToken($this->account->getOpenIdToken());
        $data= array('entity' => $id);
        list($sss_result, $sss_ok, $sss_msg) =
            $this->sss->callSocialSemanticServer('getEntityById', $data, true);
        if ($sss_ok){
            if ($sss_result){
                //There is no unified call for getting an entity in SSS so we 
                //get the collection based on a singleton array and take the first one
                //if any result is found, cardinality should be 1.
                return $sss_result['entities'][0];
            } else {
                return null;
            }
        } else {
            return new ApiProblem($sss_result, 'We could not retrieve the individual resource from SSS:'.
                $sss_msg, null, 'Problems with SSS');
        }
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = array()) {
        $soft = FALSE || _SWITCH_OFF_AUTH_CHECK;
        $unauth_msg = 'You should be logged in to retrieve items from Social Semantic Server.';
        
        $user_id = $this->isAuthorised($soft);
        $granted = (FALSE !== $user_id);
        $this->userLog('fetchAll', $soft, $user_id, null, $params, $granted);
        if (!$granted) {
            return $this->account->unAuthorisedObject($unauth_msg);
        }
        //After checking the authorization of the current user, a new token is put in the
        //account object. We HAVE to pass that on to the SSS object. The isAuthorised function
        //needs a MyAbstractResourceListener object, so we cannot check the authorisation in
        //the factory already, also, because authorisation might be dependent on the resource
        // method called
        $this->sss->setOidToken($this->account->getOpenIdToken());
        $msg = '';
        $param_array = $params ? $params->getArrayCopy() : array();
        $data = array(
            'search' => \Application\Shared\SharedStatic::altSubValue($param_array, 'terms', ''),
            'tags' => \Application\Shared\SharedStatic::altSubValue($param_array, 'tags', ''),
            'global' => \Application\Shared\SharedStatic::altSubValue($param_array, 'and', 'or'),
            'local' => \Application\Shared\SharedStatic::altSubValue($param_array, 'local', 'or'),
            'entity_types' => \Application\Shared\SharedStatic::altSubValue($param_array, 'type', '')
        );
        if ($data['tags']){
            $data['includeTags'] = TRUE;
        }
        //possibly the following check throws an exception
        try {
            $this->sss->checkEntityType($data['entity_types']);
            $show_details = \Application\Shared\SharedStatic::altSubValue($param_array, 'show_details', true);
            $show_tags = \Application\Shared\SharedStatic::altSubValue($param_array, 'show_tags', false);
            $subset = $this->getSubsetPerType($data['entity_types']);
            //We need an alternative search as SSS cannot search with no search 
            //parameters for entities of a certain type
            if (!$data['search'] && ! $data['tags']){
                list($sss_result, $sss_ok, $sss_msg) = $this->getAllEntitiesOfType($data['entity_types']);
                if ($sss_ok){
                    return $show_details ? $sss_result : 
                        $this->extractSubsetFromSocSemResult($sss_result, $subset);
                } else {
                    return new ApiProblem(500, "The SSS failed trying to get all entities ($sss_msg) with parameters:", null, 'Social Semantic Server Problem on entities', $data);
                }
            } else {
                list($sss_result, $sss_ok, $sss_msg) =
                    $this->sss->callSocialSemanticServer('search', $data, true);
                $msg .= $sss_msg;
                if ($sss_ok){
                    if ($show_details){
                        $ids = $this->extractSubsetFromSocSemResult($sss_result, null, true);

                        if ($ids) {
                            $data = array('entities' => $ids);
                            list($sss_result, $sss_ok, $sss_msg) =
                                $this->sss->callSocialSemanticServer('getEntitiesByIds', $data, true);
                            if ($sss_ok){
                                return $sss_result;
                            } else {
                                return new ApiProblem(500, 'The fetch on SSS failed with message: '.$sss_msg, null, 'Social Semantic Server Problem on search', $data);
                            }
                        } else {
                            return array();
                        }
                    } else {
                        return $this->extractSubsetFromSocSemResult($sss_result,
                           $subset);
                    }                
                } else {
                    return new ApiProblem(500, 'The fetch on SSS failed with message: '.$sss_msg, null, 'Social Semantic Server did not return a result', $data);
                }
            }
        } catch (\Exception $e){
            return new ApiProblem($e->getCode(), 'The fetch on SSS failed with an exception. '. (_DEBUG ? $e->getMessage(): ''));
        }
    }
    
    private function extractIdFromSocSemId($id){
        //Ids in SS have the form http:\/\/sss.eu\/QWERTY
        $arr = explode('/', $id);
        return array_pop($arr);
    }
    
    private function getSubsetPerType($type){
        switch ($type){
            case 'video': return array('link', 'genre', 'label', 'description');
            default: return array('label', 'description', 'type');
        }
    }
    
    /* Get all values of the fields in subset from the result we got back from SSS. This way we
     * can prevent passing on fields we will never use. If ids only is on, all other fields are ignored
     */
    private function extractSubsetFromSocSemResult($result, $subset=array(), $ids_only=false){
        //Ids in SS have the form http:\/\/sss.eu\/QWERTY
        //we always extract at least the key 'id'
        $collection = array();
        $i = 0;
        if ($result){
            foreach ($result as $entity){
                $arr = explode('/', $entity['id']);
                $id = array_pop($arr);
                $collection[$i] = $ids_only ? $id : array('id' => $id);
                if ($subset && ! $ids_only){
                    foreach ($subset as $key){
                        $collection[$i][$key] = $entity[$key];
                    }
                }
                $i++;
            }
        }
        return $collection;
    }
    
    private function getAllEntitiesOfType($entity_type){
        //allowed are: videos, tags, users, livingdocs, learneps, friends, apps, appStackLayouts
        $entity_type = strtolower($entity_type);
        if (strpos($entity_type, ',') !== FALSE){
            return array(array(), FALSE, "We can only retrieve one entity type at a time.");
        }
        $get_entity_operation_available = array("video", "tag", "user", "livingdoc",
            "learnep", "friend", "app", "appstacklayout");
        if (in_array($entity_type, $get_entity_operation_available)) {
            switch ($entity_type){
                case 'activity': $operation = 'activities';
                break;
                default: $operation = "${entity_type}s";
            }
            $abstract_operation = 'getCertainTypeEntities';
            return $this->sss->callSocialSemanticServer($abstract_operation, array('type'=>$operation), true);
        } else {
            list($sss_result, $result, $sss_msg) = $this->sss->callSocialSemanticServer(
                'search', array('entity_types'=>array($entity_type)), true);
            if ($result){
                $id_list = $this->extractSubsetFromSocSemResult($sss_result, null, true);
                
                return $id_list ? 
                    $this->sss->callSocialSemanticServer('getEntitiesByIds',array('entities'=>$id_list), true) :
                    array(array(), TRUE, "There are no ${entity_type}s found.");
            } else {
                return array(array(), TRUE, "We could not retrieve ${entity_type}s.");
            }
        }
        
    }
}
