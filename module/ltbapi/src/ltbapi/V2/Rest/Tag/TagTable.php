<?php

namespace ltbapi\V2\Rest\Tag;

use Application\Model\ModelTableCapableSSS;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;

class TagTable extends ModelTableCapableSSS {
    public function fetchAll() {
        try {
            $result = $this->getAllItems(TRUE);
        } catch (\Exception $e) {
            return $this->returnProblem(500, $ex);
        }
        return $result;
    }

    public function fetchSelection($selection_params = array(), $to_array = FALSE) {
        if (isset($selection_params['tag_txt']) && $selection_params['tag_txt']) {
            $selection_params['tag_txt'] = 
                \Application\Shared\SharedStatic::getTrimmedList($selection_params['tag_txt']);
        }

        try {
            if (isset($selection_params['return']) && $selection_params['return'] == 'stacks') {
                unset($selection_params['return']);
                $result = $this->fetchStacksSelection($selection_params);
            } else {
                $result = $this->getItems($selection_params, $to_array);
            }
        } catch (\Exception $e) {
            return $this->returnProblem(500, $ex);
        }
        return ($result);
    }

    public function fetchStacksSelection($selection_params = array()) {
        try {
            $result = $this->getItemPair(0, 'stack', array('entity_id' => 'stack_id'),
                array('stack' => array('stack_id', 'name', 'details'), 'tag' => ''),
                TRUE, $selection_params, 'timestamp');
        } catch (\Exception $e) {
            return $this->returnProblem(500, $ex);
        }
        return $result;
    }

    public function fetchOne($tag_id) {
        try {
            return $this->getItem($tag_id);
        } catch (\Exception $e) {
            return $this->returnProblem(500, $ex);
        }
    }

    public function create($data, $data_type = self::DATA_TYPE_OBJECT, $entity_check_needed=TRUE) {
        $user_id = $this->account->getCurrentUserid();
        $entity_code = $data->entity_id;

        try {
            if ($data_type == self::DATA_TYPE_OBJECT) {
                $id_name = $this->id_name;
                $tag_type = isset($data->tag_type) ? $data->tag_type : 'stack';
                $data->entity_id = $entity_id = $this->convertCodeToId($data->entity_id);
                unset($data->$id_name);
            } elseif ($data_type == self::DATA_TYPE_ARRAY) {
                $data['entity_id'] = $entity_id = $this->convertCodeToId($data['entity_id']);
                unset($data[$this->id_name]);
                $tag_type = isset($data['tag_type']) ? $data['tag_type'] : 'stack';
            } else {
                return $this->returnProblem(406, 'Creation of new tag with wrong data somehow.');
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex);
        }
        if ($entity_check_needed){
            if (!$this->entityExists($entity_id, $tag_type)) {
                return $this->returnProblem(404, "This $tag_type does not exist.");
            }
        }
        $local_tag_only = (isset($data->local_tag) && $data->local_tag);
        $tags = \Application\Shared\SharedStatic::getTrimmedList($data->tag_txt);
        unset($data->tag_txt, $data->local_tag);
        $tag = $this->getModel($data, $data_type);
        $tag->owner_id = $user_id;
        $tag->owner_code = $this->account->getCurrentUserCode();
        $tag->timestamp = time();        
        $count = count($tags);
        $warnings = '';
        try {
            $result = TRUE;
            $msg = $msgs = '';
            $added = array();
            //The new tags are perhaps passed on with save Stack, and so the 
            //array diff used there causes the keyss of the array to be mingled.
            //For that reason use foreach and not for here.
            foreach ($tags as $new_tag) {
                if (!$new_tag){
                    continue;
                }
                $tag->tag_txt = $new_tag;
                try {
                    if ($this->saveItem($tag)){
                        $added[] = $new_tag;
                        if (! $local_tag_only && _CONNECT_SSS) {
                            $data = array('label' => $tag->tag_txt, 'entity' => $entity_code);
                            $result = $this->callSocSemServer('addTag', $data,
                                $this->account->getOpenIdToken());
                            if ($this->isProblem($result)){
                                //return $result;
                                $msgs .= $this->composeWarnings($result);
                            } else {
                                if (_DEBUG){
                                    $msgs .= " Adding to the Social Semantic Server gave: ".$result[1];
                                }
                            }
                        }
                    } else {
                        \Application\Shared\SharedStatic::doLogging('tagtable create failed '.print_r( $tag, 1));
                    }
                } catch (\Exception $ex) {
                    $result = FALSE;
                    $msgs .= ("We could not add the tag: $new_tag");
                    list($c, $m) = \Application\Shared\SharedStatic::getDbError($ex->getMessage());
                    if (in_array($c, array(1062, 1169, 1022))) {
                        $msgs .= ". This was a duplicate entry. ".(_DEBUG ? $m :'');
                    } else {
                        $msgs .= (_DEBUG ? " Got: $m" : '');
                    }
                }
            }
            if (!$result) {
                $msg = "We could not add all of your tags: $msgs";
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex);
        }
        return array('result' => $result, 'msg' => $msg, 'added' => $added);
    }
    
    public function update($user_id, $id, $data) {
       if ($data->is_patch){
           return $this->patch($user_id, $id, $data);
       } else {
           return $this->returnResourceProblem(415, 'There is currently no update function defined. Use '.
               "put with the option is_patch: true.");
       }
    }
    
    public function patch($id, $data) {
        if ($tag = $this->getItem($id)) {
            if (!$this->account->checkOwner($tag, 'owner_id')) {
                return $this->returnProblem(403, 'You are not the owner of this tag.');
            }
        } else {
            return $this->returnProblem(404, 'The tag did not exist.');
        }
        $msg = '';
        $data->timestamp = time();
        try {
            $result = (bool) $this->updateItem($id, '', $data, TRUE);
            if ($result && _CONNECT_SSS){
                $sss_data = array(
                    'tag' => $tag->tag_txt, 
                    'entity_id' => $tag->entity_id, 
                    'label' => $data->tag_txt
                );
                $sss_result = $result = $this->callSocSemServer('changeTag', $sss_data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    $msg .= $this->composeWarnings($sss_result);
                } else {
                    $msg .= (_DEBUG ? "We got from SSS: ".$sss_result[1] : "");
                }
            }
        } catch (\Exception $ex) {
            $result = FALSE;
            $msg .= "We could not update your tag " . (_DEBUG ? $ex->getMessage() : '');
            list($c, $m) = \Application\Shared\SharedStatic::getDbError($ex->getMessage());
            if (in_array($c, array(1062, 1169, 1022))) {
                $msgs .= ". This was a duplicate entry. ".(_DEBUG ? $m :'');
            } else {
                $msgs .= (_DEBUG ? " Got: $m" : '');
            }
        }
        return array($result, $msg);
    }

    //TODO the signature of this delete function differs from the other table objects
    //It is called only directly from ReferenceTable->setTags with a where object
    public function delete($tag_id=0, $where=null, $respond_other_resource=FALSE) {
        if (!$this->account->checkAuthentication() || ! ($user_id = $this->account->getCurrentUserid())) {
            return $this->returnProblem(401, $this->account->getMessage());
        }
        $local_tag_only = FALSE;//initialisation. Can be set in $where
        if ($tag_id){
            if ($tag = $this->getItem($tag_id)) {
                if (!$this->account->checkOwner($tag, 'owner_id')) {
                    return $this->returnProblem(403, 'You are not the owner of this tag.');
                }
            } else {
                return $this->returnProblem(404, 'The tag did not exist.');
            }
        } elseif ($where){
            //we can pass either the int id or the encoded entity_code
            if (isset($where->entity_id)) {
                $where->entity_id = $this->convertCodeToId($where->entity_id);
            }
            $local_tag_only = (isset($where->local_tag) && $where->local_tag);
            unset($where->local_tag);
            $where->owner_id = $user_id;
        } else {
            return $this->returnProblem(404, 'Parameters missing for tag deletion.');
        }
        $msg = '';
        if ($tag_id){
            $id_name = $this->id_name;
            $where->$id_name = $tag_id;
        }
            
        $deleted = $this->getItems($where, TRUE, array('tag_txt'));
        try {
            $result = (bool) $this->deleteItems('', $where);
            
            if ($result){
                if (! $local_tag_only && _CONNECT_SSS){
                    $data = array(
                    'label' => $tag->tag_txt,
                    'space' => $tag->space,
                    'stack' => $tag->entity_id,
                    );
                    $sss_result = $this->callSocSemServer('deleteTag', $data,
                        $this->account->getOpenIdToken());
                    if ($this->isProblem($sss_result)){
                        //return $sss_result;
                        $msg .= $this->composeWarnings($sss_result);
                    }
                }
                if ($respond_other_resource){
                    $result = array('deleted' => $deleted, 'msg' => $msg);
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex);
        }
        return $result;
        //Before we returned TRUE/FALSE (see somment herafter), but now we catch 
        //this array in the resourceListener of Stacks for example when indicated by third param
        //For some reason the Resource class retrieving this result
        //only accepts ApiProblem objects and booleans for deletion. Otherwise it returns simply
        //false. The solution is to return a Response object, but that is caller responsibility.
    }

    //This mehtod is called when a stack is deleted,
    function deleteTagsAttached($entity_id, $type='stack') {
        $where = array('entity_id' => $entity_id, 'tag_type' => $type);
        $warnings = '';
        try {
            $nr = $this->deleteItems(null, $where);
            $do_delete_at_sss = _CONNECT_SSS && ($type === 'stack');
            if (($nr !== FALSE) && $do_delete_at_sss){
                $data = array(
                //'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', ''),
                //'space' => \Application\Shared\SharedStatic::altSubValue($param_array, 'space', ''),
                'stack' => $entity_id,
                );
                $sss_result = $this->callSocSemServer('deleteTag', $data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    $warnings = $this->composeWarnings($sss_result);
                }
                //We no longer return a FALSE result upon sss failure always
                $result = $sss_result || _IGNORE_SSS_RESULT;
            } else {
                $result = ($nr !== FALSE);//! $do_delete_at_sss;//So we know $nr === FALSE
            }
            return array($result, $warnings);
                
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 'We could not delete the tags of this stack');
        }
    }
}
