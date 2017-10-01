<?php
namespace ltbapi\V2\Rest\Favourite;

//use Application\Model\ModelTable;
use Application\Model\ModelTableCapableSSS;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Expression;
use ZF\ApiProblem\ApiProblem;
//use Zend\Db\Sql\Select;
//use Zend\Paginator\Adapter\DbSelect;

class FavouriteTable extends ModelTableCapableSSS {    
   
    public function fetchSelection($user_id=0, $selection_params = array()) {
        $where = $this->ownerCondition($user_id);
        
        try {
            //this can be nicer: we know the select fields
            $field_set = $this->getCollectionFields('*');
            $field_set['stack_code'] = 'entity_code';
            $join_spec = array(
                array('stack',
                    new Expression(" stack.stack_id = favourite.entity_id AND favourite.user_id = $user_id"),
                    array(
                        'exists' => new Expression('stack.stack_id IS NOT NULL'),
                        'name' => 'name')
                )
            );
            
//            getItemsJoin($cond = array(), $to_array=FALSE, $field_set='',
//        $order=NULL, $joins=NULL, $group=NULL, $single_record=FALSE, $show=FALSE,
//        $offset=0, $page_size=NULL) {
            $result = $this->getItemsJoin($where, true, $field_set, 
                'stack.name',
                $join_spec,
                NULL,
                FALSE,
                FALSE//, //output sql code for debugging purposes (will result in corrupt json response)
//                $offset,
//                $page_size
            );
            
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'List of favourites cannot be returned');
        }
        
        return $result;
    }

    /* TODO: the distinction between arrays and objects seems irrelevant: we only call
     * this function from the corresponding resource and that class calls this function
     * with a data object.
     */
    public function create($user_id, $data, $data_type = self::DATA_TYPE_OBJECT) {
       $id_name = $this->id_name;
       $err_msg = 'Creation of new favourite without '.
          'entity field is not meaningfull.';
       $entity_id = 0;
       if ($data_type == self::DATA_TYPE_OBJECT) {
            if (isset($data->entity_id) || isset($data->entity_code)) {
                if (isset($data->entity_id)) {
                    if (!is_numeric($data->entity_id)){
                        return $this->returnProblem(406, "the parameter entity id should be a number");
                    }
                    //We want the code to be unique and not some random corresponding code
                    $stack = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItem(
                        $data->entity_id, null, true);
                    if ($stack){
                        $data->entity_code = $stack['stack_code'];
                    } else {
                        $data->entity_code = $this->convertIdToCode($data->entity_id);
                    }
                } else {
                    $data->entity_id = $this->convertCodeToId($data->entity_code);
                }
                $entity_id = $data->entity_id;
            } else {
                return $this->returnProblem(406, $err_msg);
            }
            //just in case an id field is sent along, we ignore it
            unset($data->$id_name);
        } elseif ($data_type == self::DATA_TYPE_ARRAY) {
            if (isset($data['entity_id']) || isset($data['entity_code'])) {
                if (isset($data['entity_id'])) {
                    if (!is_numeric($data['entity_id'])){
                        return $this->returnProblem(406,
                            "the parameter entity id should be a number");
                    }
                    //We want the code to be unique and not some random corresponding code
                    $stack = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItem(
                        $data['entity_id'], null, true);
                    if ($stack){
                        $data['entity_code'] = $stack['stack_code'];
                    } else {
                        $data['entity_code'] = $this->convertIdToCode($data['entity_id']);
                    }
                    
                    $data['entity_code'] = $this->convertIdToCode($data['entity_id']);
                } else {
                    $data['entity_id'] = $this->convertCodeToId($data['entity_code']);
                }
                $entity_id = $data['entity_id'];
            } else {
               return $this->returnProblem(406, $err_msg);
            }
            unset($data[$this->id_name]);
        } else {
            return $this->returnProblem(406,
                'Creation of new favourite with wrong data somehow.');
        }
        if (! $this->entityExists($entity_id, 'stack')){
            return $this->returnProblem(406, 'Creation of new favourite stack ('
                .$entity_id. ') failed because stack was not found.');
        
        }
        $favourite = $this->getModel($data, $data_type);
        $favourite->user_id = $user_id;
        $result = FALSE;
        $sss_ok = FALSE;
        $msg = '';
        try {
            $result_id = $this->saveItem($favourite);
            if ($result_id){
               // $code = \Application\Shared\SharedStatic::getShortCode($result_id);
                $result = TRUE;//(bool) $this->updateItem($result_id, '', array('favourite_code' => $code));
            }
        } catch (\Exception $ex) {
            $status = $ex->getCode();
            if (501 == $status){
               return $this->returnProblem(406, $ex->getMessage(). ' You already saved this as a favourite.'); 
            }
            return $this->returnProblem(500, (_DEBUG ? $ex: 'We could not save the favourite. '));
        }
        try {
            if ($result){
                if (! $result = $this->getItem($result_id, 0, false, true)) {
                    throw new \Exception('The favourite seems to be saved, but could not '.
                        'be retrieved afterwards.');
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, (_DEBUG ? $ex: $ex->getMessage()));
        }
        return array('result' => $result);
    }

    /**
     *  Since this resource is called from within the Rest Resource.php class,
     * it has to return a boolean only.
     */

    public function delete($user_id, $entity_id) {
        $result = $msg = '';
        //id is most likely a favourite code but if not we convert it to one
        $entity_id = $this->convertCodeToId($entity_id);
        $favourites = $this->getItems(array('entity_id' => $entity_id,
            'user_id' => $this->account->getCurrentUserId()));
        
        if ($favourites->count() == 1) {
            $favourite = $favourites->current();
            $id_name = $this->id_name;
            $favourite_id = $favourite->$id_name;
        } else {
            return $this->returnProblem(404, 'The favourite did not exist or on the contrary was not unique.');
        }
        try {
            $nr = $this->deleteItems($favourite_id);
            if ($nr != 0){
               /* 
               if (FALSE && _CONNECT_SSS){
                    $data = array('favourite' => $favourite->favourite_code);
                    $sss_result = $this->callSocSemServer('deleteFavourite', $data);
                    if ($this->isProblem($sss_result)){
                        return $sss_result;
                    }          
                    $sss_result = $this->callSocSemServer('deleteTag', $data);
                    if ($this->isProblem($sss_result)){
                        return $sss_result;
                    }
                } else {
                    if ($tag_table = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')){
                        $tag_table->deleteTagsAttached($favourite_id);
                    }
                }*/
            }
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the favourite returned an error'));
        }
    }
    
    public function deleteList($user_id=0, $data=null) {
        $result = $msg = '';
        $where = $data ?: array();
        
        $where['user_id'] = \Application\Shared\SharedStatic::altValue($user_id,
            $this->account->getCurrentUserId());
        if (!$where['user_id']){
            //When _DEBUG is on and the authentication check was omitted, we might get 0 here
            return $this->returnProblem(401, 'You should be logged in to delete a collection of favourite');
        }
        if (! _DEBUG){//so we can delete a whole serie of test data from our own hand
            return $this->returnProblem(405, 
                ('The deletion of a list of favourites is possible but at the moment not allowed'));
        }
        try {
            //TODO for the moment we assume favourites concern stacks
           $nr = $this->deleteItems(null, $where);
           return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the collection returned an error'));
        }
    }

    //This mehtod is called when a stack is deleted,
    function deleteFavouritesAttached($entity_id, $type='stack') {
        $where = array('entity_id' => $entity_id, 'fav_type' => $type);

        try {
            $nr = $this->deleteItems(null, $where);
            if (($nr !== FALSE) && FALSE && _CONNECT_SSS){//TODO when we store the favourites in SSS, remove FALSE
                $data = array(
                //'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', ''),
                //'space' => \Application\Shared\SharedStatic::altSubValue($param_array, 'space', ''),
                'stack' => $entity_id,
                );
                if ($this->isProblem($result = $this->callSocSemServer('deleteFavourite', $data,
                        $this->account->getOpenIdToken()))){
                    return $result;
                }
                return $result || _IGNORE_SSS_RESULT;
            } else {
                return TRUE;
            }
                
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 'We could not delete the favourites of this stack');
        }
    }
    
    private function ownerCondition($user_id, $includepublic = TRUE){
        $owner_pred = new Predicate\Predicate();
        $owner_pred->equalTo("favourite.user_id", $user_id);
        return $owner_pred;
    }    
}