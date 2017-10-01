<?php
namespace ltbapi\V2\Rest\Reference;

use Application\Model\ModelTable;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Expression;
use Application\Shared\SharedStatic;

class ReferenceTable extends ModelTable {
    const FILE_LOCATION = _SFS_FILE_LOCATION;
    const API_ADDRESS = _API_URI;
    
    public function fetchSelection($user_id=0, $selection_params = array()) {
        $return_labels = TRUE;
        $filter_labels = FALSE;
        $and = TRUE;
        if (isset($selection_params['offset']) && $selection_params['offset']){
            $offset = $selection_params['offset'];
        } else {
            $offset = 0;
        }
        
        if (isset($selection_params['page_size']) && $selection_params['page_size']){
            $page_size = $selection_params['page_size'];
        } else {
            $page_size = 0;
        }
        unset($selection_params['page_size'], $selection_params['offset']);      
        $param_msg = '';
        $label_params_cond = null;
        if ($selection_params){
            if (!isset($selection_params['entity_code'])){
                $param_msg = 'The stack code is obligatory.';
            } else {
                //Get stack -> details->labels but only if you are not the owner
                $stack = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItem($selection_params['entity_code']);
                if ($stack->owner_id !== $user_id) {
                    $details = json_decode($stack->details);
                    $labels = isset($details->labels) ? $details->labels : [];
                    if ($labels){
                        //Resulting in : AND (`tag`.`tag_txt` IN ('XXX') OR `reference`.`owner_id` = 112)
                        $public_labels = [];
                        foreach ($labels as $lab_key => $lab_val){
                            if (!isset($lab_val->private) || ! $lab_val->private){
                                $public_labels[] = $lab_key;
                            }
                        }
                        $label_owner_cond = new Predicate\Literal("reference.owner_id = $user_id" );
                        $label_public_cond = new Predicate\In('tag.tag_txt', $public_labels);
                        $label_params_cond = new Predicate\PredicateSet(array($label_owner_cond, $label_public_cond), Predicate\PredicateSet::OP_OR);
                    }                    
                }
            }
            //some params for what to return
            if (isset($selection_params['and'])) {
                 $and = $selection_params['and'];
            }
            
            if (isset($selection_params['show_labels'])){
                $return_labels = $selection_params['show_labels'];
            }
            if (isset($selection_params['labels'])){
                $filter_labels = $selection_params['labels'] ? 
                    SharedStatic::getTrimmedList($selection_params['labels']) : null;
            }
 
            unset($selection_params['labels'], $selection_params['show_labels'], $selection_params['and']);
        } else {
            $param_msg = 'There are no parameters sent along. At least the stack code is required.';
        }
        
        if ($param_msg){
            return $this->returnProblem(400, $param_msg);
        }
        
        /* TODO: see that only references are delivered to people who may see it
        $stack = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItem($selection_params['entity_code']);
        $is_owner_stack = $stack->owner_id === $this->account->getCurrentUserId();
        if (!$is_owner_stack && ! $filter_labels){
            //For the case that the user does not send a folder in the params
            $param_msg .= 'Since you are not the owner of the stack, a folder is required in the parameters.';
            $result = new \stdClass();
            $result->_embedded = array("labels" => [], "reference" => [], "error" =>$param_msg) ;
        
            return $result;
        }
        */
        if ($param_msg){
            return $this->returnProblem(400, $param_msg);
        }
        $other_params_cond = new Predicate\Literal('archived = 0');
        if ($filter_labels && ! $return_labels){
            $label_cond = new Predicate\In('tag.tag_txt', $filter_labels);
            $other_params_cond = new Predicate\PredicateSet(array($label_cond, $other_params_cond));
        }
        if($label_params_cond){
            $other_params_cond = new Predicate\PredicateSet(array($label_params_cond, $other_params_cond));
        }

        if ($selection_params){
            $pred_set = new Predicate\PredicateSet();
            //TODO make the descr and name in one OR statement if they both exist
            $combination = $and ? Predicate\PredicateSet::COMBINED_BY_AND : Predicate\PredicateSet::COMBINED_BY_OR;
            foreach ($selection_params as $fld => $val){
               if (($fld == 'name') || ($fld == 'description') || ($fld == 'terms') ||
                   ($fld == 'url') || ($fld == 'external_url')){
                   if ($fld == 'terms'){
                       $terms = $this->getSearchArray($val);
                       $name_predicate = $this->createSearchPredicate($terms, "reference.name");
                       $descr_predicate = $this->createSearchPredicate($terms, "reference.description");
                       $term_condition = new Predicate\PredicateSet();
                       $term_condition->addPredicate($name_predicate,
                             Predicate\PredicateSet::COMBINED_BY_OR);
                       $term_condition->addPredicate($descr_predicate,
                             Predicate\PredicateSet::COMBINED_BY_OR);
                       $pred_set->addPredicate($term_condition, $combination);
                   } else {
                        $pred_set->addPredicate(new Predicate\Like("reference.$fld", "%$val%"),
                             $combination);
                   }
               } else {
                    $pred_set->addPredicate(new Predicate\Operator("reference.$fld", '=', $val),
                        Predicate\PredicateSet::COMBINED_BY_AND);
               }
            }
            $where = new Predicate\PredicateSet(array($pred_set, $other_params_cond));
        } else {
            $where = $other_params_cond;
        }

        try {
            //this can be nicer: we know the select fields
            $field_set = $this->getCollectionFields('*');
            //$grouping = array('reference.reference_code');
            
            $join_spec = 
                array(//other tables to join with
                    array('user', " reference.owner_id = user.user_id ", 
                        array('name', 'user_id', 'user_code', 
                            'is_owner' => new Expression('user.user_id = '.$user_id)))
                );
            
            if ($filter_labels || $return_labels){
                if ($return_labels){
                    //$grouping[] = 'tag.tag_txt';
                    $fields = array('tag_txt');
                } else {
                    $fields = array();
                    $predicate = new Predicate\IsNotNull('tag_txt');
                    $where->addPredicate($predicate);
//                    if ($private && !$is_owner_stack){
//                        $where->addPredicate(new Predicate\Literal("user.user_id = $user_id"));
//                    }
                }
                $join_spec[] =
                    array('tag',
                      new Expression(" reference.reference_id = tag.entity_id AND tag.tag_type = 'reference'"),
                      $fields     
                    );
            }
            $result = $this->getItemsJoin($where, true, $field_set, 
                //Used to be the sorting array('reference.name','reference.reference_code','tag.tag_txt'),//order
                array('reference.reference_code'),//order
                $join_spec,
                null,
                FALSE,
                FALSE, //output sql code for debugging purposes
                $offset,
                $page_size
            );
            if (is_string($result)){
                //If _debug is on, the exception might be caught and returned
                //as error string
                return $this->returnProblem(500,
                    'List of references cannot be returned. '.$result);
            }
            if ($result && $return_labels){
                $result = $this->gatherTags($result, $and, $filter_labels);
            }
            
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'List of references cannot be returned');
        }
        
        return $result;
    }
    
    public function fetchOne($reference_id, $user_id=0) {
        if (!$user_id || !is_numeric($user_id)) $user_id = 0;
        $return_labels = TRUE;
        $return_favourite = TRUE;
        
        $selection_params = $_GET;
        if ($selection_params){
            //some params for what to return
            $return_labels = $return_labels || 
                (isset($selection_params['show_labels']) && $selection_params['show_labels']);
        }

        try {
            //referenceId is most likely a reference code but if not we convert it to one
            $reference_id = $this->convertCodeToId($reference_id);
            $where = $this->ownerCondition($user_id);
            
            //$where = new Predicate\Predicate();// $where->literal("1 = 1");
            //Note that the result can be False (no items) if there are either no
            //items or the item is not public and the user forgot to login
            //The result will be the same: a 404 Entity not found apiproblem.
            //If the result of the getItemsJoin is an error string, this should
            //be shown as the debug is on in that case. Otherwise it is false 
            //and the mentioned 404 will be returned.
            
            if ($return_labels){
                //$favourite_cond = $this->favouriteCondition($user_id);
                $id_pred = new Predicate\Predicate();
                $id_pred->equalTo($this->table.".".$this->id_name, $reference_id);
                $where = new Predicate\PredicateSet(array($id_pred, $where));//, $favourite_cond));
                $field_set = $this->getEntityFields();
                
                //joins specs: [table_str, on_spec_str, [<alias> => expr_str|fld_str]]
                $joins = array();
                $labels = array();
                $joins[] = array(
                    'tag', 
                    'reference.reference_id = tag.entity_id',
                    array('tag_txt')
                );
                $order = array('reference.name','reference.reference_code');
                $grouping = array('reference.reference_code', 'tag.tag_txt');
                $tag_type_pred = new Predicate\Predicate();
                $tag_type_pred->equalTo("tag.tag_type", 'reference');
                $null_pred = new Predicate\IsNull('tag.tag_txt');
                $tag_type_pred_set = new Predicate\PredicateSet(
                    array($tag_type_pred, $null_pred), Predicate\PredicateSet::COMBINED_BY_OR);
                $where->addPredicate($tag_type_pred_set);
                
                //Get the owner of the reference. Note that the is_owner field expression only makes sense if we are searching for 
                // public references too
                $joins[] = array(
                        'user', 
                        'reference.owner_id = user.user_id',
                        array('owner_name' => 'name', 'is_owner' => new Expression('user.user_id = '.$user_id))
                    );
                //7th arg is saying whether it is a singleRecord or not.
                //8th arg should be false: showing the query for debugging purposes
                $result = $this->getItemsJoin($where, TRUE, $field_set, $order, $joins,
                    $grouping, FALSE, FALSE);
                if (is_string($result)){
                    return $this->returnProblem(500, 'Reference cannot be returned: '.$result);
                } elseif ($result) {
                    //return the first of the singleton as we fetch only one
                    $result = SharedStatic::first($this->gatherTags($result)->_embedded['reference']);
                }
            } else {
                /* This is how it used to be: no return labels only one join needed.  7th arg for single record */
                $result = $this->getItemPair($reference_id, 'user', array('owner_id'=>'user_id'),
                array('user' => array('name', 'user_code', 'is_owner' => new Expression('user.user_id = '.$user_id)), $this->table => ''),
                TRUE, $where, NULL, TRUE);
            }
        } catch (\Exception $e) {
            return $this->returnProblem(500, $e, 'Reference cannot be returned');
        }
 
        //If the reference was not found and the user_id was 0, this is because the 
        //reference was not public and the 'current user' is not the owner or not 
        //logged in. In that case we return a 403 iff getItem returns a result
        if (! $result){
            $item = $this->getItem($reference_id);
            if ($item){
                return $this->returnProblem(403, 'You must be logged in to see this reference '
                    . 'as it is not publicly available');
            }
        }
        return $result;
    }
    
    /* TODO remove tags_deleted as it makes no sense for newly created references....*/
    public function create($user_id, $data) {
       $id_name = $this->id_name;
       $tags_added = (isset($data->labels) && $data->labels) ? $data->labels : [];
       unset($data->labels, $data->$id_name);
       if (!$tags_added){
           $tags_added = array('resources');
       }
       if (!isset($data->entity_code)){
           return $this->returnProblem(400, 'The code of the entity this reference belongs to, is obligatory. ', 'Parameter Problem', $data);
       }
        $reference = $this->getModel($data, self::DATA_TYPE_OBJECT);
        $reference->owner_id = $user_id;
        //$reference->owner_code = $this->account->getCurrentUserCode();
        $ts = time();
        $reference->created = $ts;
        $result = FALSE;
        $warnings = '';
        //TODO msgs can go as soon as we do not use the msgs returned by setTags.
        //This seems the case already
        $msg = '';
       
        try {
            
            $file_uploaded_correctly = (isset($data->file) && !(isset($data->file['error']) && $data->file['error'] != 0));
            if ($data->ref_type === 'file'){
                $reference->ref_type = 'file';
                
                //We expect: $data {params: {name, description, ref_type, entity_code, labels},
                //fileKey: "file", fileName, mimeType
                if ($link = $this->getExternalLinkParam($data)){
                    $reference->external_url = $link;
                }
                //TODO: do here something with external files ie when an external_url is passed
                if (!$file_uploaded_correctly){
                    throw new \Exception('File reference needs a valid file. '.
                        'No file supplied or upload failed. '. 
                        (isset($data->file['error']) ? $data->file['error'] : ''),
                        400);
                }
                
                if (! (isset($data->name) && $data->name)){
                    $reference->name = $data->file['name'];
                } else {
                    $reference->name = $data->name;
                }
                
                list($reference, $tmp_name) = $this->deriveFromFile($reference, $data->file);
                unset($data->file);
            } elseif ($data->ref_type === 'link'){
                $reference->ref_type = 'link';

                if (!isset($data->link) || ! $data->link){
                    throw new \Exception('Link reference needs a valid url. '.
                        'No url supplied. ', 400);
                }
                if ($file_uploaded_correctly){
                    list($reference, $tmp_name) = $this->deriveFromFile($reference, $data->file);
                }
                $reference->url = $data->link;
                
            } else {
                throw new \Exception('Reference type should be either link or file', 400);
            }
            if (isset($reference->details) && $reference->details){
                $reference->details = json_encode($reference->details);
            }

            $result_id = $this->saveItem($reference, false, false, true);
            if ($result_id){
                $code = SharedStatic::getShortCode($result_id);
                $reference->reference_code = $code;
                $file_ref_code = str_rot13($code);
                $update_data = array(
                    'reference_code' => $code,
                    'file_ref_code' => $file_ref_code
                );
                
                $image_details = false;
                if ($file_uploaded_correctly){
                    
                    $update_data = $this->storeLocalFile($update_data, $reference, null, $tmp_name, false);
    
                    if ($data->ref_type === 'file' && !_URL_FIELD_DEPRECATED){
                        //We store for a while the internal url in the url like it used to be
                        $update_data['url'] = $update_data['internal_url'];
                    }

                    //files are not generally accompanied by a details field, but we might want to
                    //store a image_url for image files
                    if (($data->ref_type === 'file') && $this->isImageType($reference->file_type)){
                        $image_details = array(
                            'image_url' => $update_data['url'],
                            'file_name' => $reference->file_name,
                            'file_type' => $reference->file_type,
                            'file_size' => $reference->file_size
                        );
                    }
                }
                
                //see if there is an external_url provided that points to an image
                if(!$image_details && 
                        isset($data->external_url) && 
                        $data->external_url){
                    
                    //if this is a link to an image, store it locally
                    $image_details = $this->storeLocalImage($data->external_url, $reference);
                    
                }
                
                
                if($image_details !== false){
                    if($data->ref_type === 'link'){
                        $details = json_decode($reference->details);
                    }elseif($data->ref_type == 'file'){
                        $details = (object) array();
                    }
 
                    if(!$reference->name){
                        $update_data["name"] = $image_details["file_name"];
                    }
                    
                    $details->image_details = $image_details;
                    $update_data['details'] = json_encode($details);
                }
                
                
                $result = (bool) $this->updateItem($result_id, '', $update_data);
                $continue = TRUE;
                if ($result && $continue){
                    $tag_result = $this->setTags($result, $tags_added, null, $result_id);    
                    if ($this->isProblem($tag_result)){
                        return $tag_result;
                    } else {
                        //there are of course no tags_deleted
                        list($tags_added) = $tag_result[0];
                        if ($tag_result[1] && _DEBUG){
                            $warnings .= trim($tag_result[1]);
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'We could not save the reference.');
        }
        $result_as_array= FALSE;
        try {
            if ($result){
                if (! $result = $this->getItem($result_id, 0, $result_as_array, true)) {
                    throw new \Exception('The reference seems to be saved, but could not '.
                        'be retrieved afterwards.');
                } else {
                    if ($warnings && _DEBUG){
                        if ($result_as_array){
                            $result['warnings'] .= $warnings;
                        } else {
                            $result->warnings .= $warnings;
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, $ex->getMessage());
        }
        
        $user_name_fld = 'user.name';
        $user_code_fld = 'user.user_code';
        $label_array = $tags_added ? array_combine($tags_added, array_fill(0, count($tags_added), 1)) : array();
        if ($result_as_array){
            $result[$user_name_fld] = $this->account->getCurrentUserName();
            $result[$user_code_fld] = $this->account->getCurrentUserCode();
            $result['labels'] = $label_array;
        } else {
            $result->$user_name_fld = $this->account->getCurrentUserName();
            $result->$user_code_fld = $this->account->getCurrentUserCode();
            $result->labels = $label_array;
        }

        return array('result' => $result);
    }
    
   /* Currently we use the update function to link to the patch function as patching
    * gives empty result somehow by apigility presumably
    */
    public function update($user_id, $id, $data) {
       if ($data->is_patch){
           return $this->patch($user_id, $id, $data);
       } else {
           return $this->returnResourceProblem(415, 'There is currently no update function defined. Use '.
               "put with the option is_patch: true.");
       }
    }
    
    /*
     * Will be the result from a PATCH /reference/[reference_id] call. 
     */
    public function patch($user_id, $id, $data) {
        //id is most likely a reference code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($reference = $this->getItem($id)) {
            if (!$this->account->checkOwner($reference, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this reference.');
            }
        } else {
            return $this->returnProblem(404, 'The reference did not exist');
        }
        
        //TODO !!! I think this never occurs: a reference is added to a collection and gets its label
        //and afterwards this cannot be changed from the current LTB UI.
        $old_tags_list = $this->getColumnFromResult('tag_txt', 
                $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')->getItems(
                array('entity_id' => $id, 'tag_type'=> 'reference'), TRUE, 'tag_txt'));
        
        if (isset($data->labels) && $data->labels){
            $current_tags = \Application\Shared\SharedStatic::getTrimmedList($data->labels);

            $old_tags = ($old_tags_list ?
                \Application\Shared\SharedStatic::getTrimmedList($old_tags_list) : []);

            $tags_added = array_diff($current_tags, $old_tags);
            $tags_deleted = array_diff($old_tags, $current_tags);

            unset($data->labels);
        } else {
            $tags_added = [];
            $tags_deleted = [];
            $current_tags = $old_tags_list;
        }
        
//        $warnings = '';
//        if (isset($data->public)){
//            $data->public = ($data->public ? 1 : 0);
//        }
        try {
            $data_arr = (array) $data;
           
            //Alowed params to change: name, description and public
            $allowed_params = array('name', 'description', 'external_url');
//            if ($reference->ref_type === 'file'){
//                $allowed_params[] = 'external_url';
//            }
            $update_data = SharedStatic::returnApprovedParams($data_arr, $allowed_params);
            
            //if this is a link to an image, store it locally
            $image_details = $this->storeLocalImage($update_data['external_url'], $reference);
            
            if ($data->details){
                $details = (object) $data->details;
                unset($data->details);
            } elseif ($image_details) {
                $details = ($reference->details)?json_decode($reference->details):(object) array();
            }

            if($image_details){
                $details->image_details = $image_details;
            }
            
            $update_data['file_ref_code'] = str_rot13($reference->reference_code);
        
            
            if($details){
                $update_data['details'] = json_encode($details);
            }
            
            $update_result = (bool) $this->updateItem($id, '', $update_data, TRUE);
            $tag_result = $this->setTags($update_result, $tags_added, $tags_deleted, $reference->reference_code);
            if ($this->isProblem($tag_result)){
                return $tag_result;
            } else {
                list($tags, $msg) = $tag_result;
            }
            //TODO This could be done differently
            //Since we want to add the labels to it, we cannot use the referenceEntity. Labels will be 
            //erased from the object somewhere in the HalJson object construction
            $result = $this->getItem($id, null, true);
            $label_array = $current_tags ? array_combine($current_tags, array_fill(0, count($current_tags), 1)) : array();
            $result['labels'] = $label_array;
            
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex: 'The reference cannot be patched')); 
        }
        //We could just resurn the result. that would result in the HalJSON _links key to appear at the
        //same level as the object keys. HalJson converts this result only into an _embedded in case
        //of collections and in case of an object. So by returning the array, we win twice ;)
        return array('result'=> $result);
        //For some reason the $http module in the Tilestore truncates the reponse for patches to 
        //empty data. I could not find where that is done. So instead of sending the warnings back to
        //the Tilestore, we store them here in a log.
    }

    /**
     * Deletes one reference and its associated files.
     * @return  Since this resource is called from within the Rest Resource.php class,
    * it has to return a boolean only.
    */
    public function delete($user_id, $reference_id) {
        $result = $msg = '';
        
        //id is most likely a reference code but if not we convert it to one
        $reference_id = $this->convertCodeToId($reference_id);
        if ($reference = $this->getItem($reference_id)) {
            if (!$this->account->checkOwner($reference, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this reference.');
            }
        } else {
            return $this->returnProblem(404, 'The reference did not exist.');
        }
        try {
            //We have already checked the owner/moderator property, so just delete here
            $nr = $this->deleteItems($reference_id);
            if ($nr != 0){
                if ($tag_table = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')){
                    //this will delete the local tags
                    $result = $tag_table->deleteTagsAttached($reference_id, 'reference');
                    if ($this->isProblem($result)){
                        \Application\Shared\SharedStatic::doLogging($this->composeWarnings($result));
                        return $result;
                    }
                }
                //Note that some link references have also an image file attached 
                //that can be associated with the link
                $this->removeFilesFromReference($reference);
            }
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the reference (or related data) returned an error.'));
        }
    }
    
    public function deleteList($user_id=0, $data=null) {
        $stack_code = SharedStatic::altSubValue($data, 'entity_code');
        if (! $stack_code){
            return $this->returnProblem(400, 
                'The deletion of a list of references is only possible when the '.
                'stack code is provided in the parameter entity_code.'
            );
        }
        $params_dummy = null;
        $filter_labels = SharedStatic::getTrimmedArg($params_dummy, $data, 'labels', TRUE);
        $filter_refs = SharedStatic::getTrimmedArg($params_dummy, $data, 'ref_list', TRUE);
        unset($data['labels'], $data['ref_list']);
        $result = $msg = '';
        $where = $data ?: array();
        
        $user_id = SharedStatic::altValue($user_id, $this->account->getCurrentUserId());
        if (!$user_id){
            //When _DEBUG is on and the authentication check was omitted, we might get 0 here
            return $this->returnProblem(401, 'You should be logged in to delete a collection of references.');
        }
        //Is the current user the owner of the stack? Then allow deletion of all items.
        $stack = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItem($data['entity_code']);
        $is_owner_stack = $stack->owner_id === $user_id;
        $delete_all = (count($data) === 1) && ! $filter_labels && !$filter_refs && $is_owner_stack;      
        
        //So where is at least entity_code = 'XXX' now: if not the owner of the stack, one should be 
        //owner of the reference
        if (! $is_owner_stack && !$this->account->isModerator()){
            $where['owner_id'] = $user_id;
        }
        if ($filter_refs){
            $where['reference_code'] = $filter_refs;
        }
        if ($filter_labels){
            $where['tag.tag_txt'] = $filter_labels;
            $tag_join = array('tag', new Expression(" reference.reference_id = tag.entity_id AND tag.tag_type = 'reference'"));
            $references = $this->getItemsJoin($where, TRUE, '*', NULL, array($tag_join),
                "reference.reference_code", 0 , FALSE);
        } else {
            $references = $this->getItems($where, TRUE);
        }
        if (!$references) {
            return TRUE;
        }
        foreach($references as $ref){
            $this->removeFilesFromReference($ref);
        }
        $ref_ids = $this->getColumnFromResult('reference_id', $references, TRUE);
        $where = array(
            'reference_id' => $ref_ids
        );
        try {
            $nr = $this->deleteItems(null, $where);
            $tag_where = array(
                'entity_id' => $ref_ids,
                'tag_type' => 'reference'
            );
            //remove all links from this reference to any collection
            $nr_in_collections_tagged = $this->getOtherTable(
                'ltbapi\V2\Rest\Tag\TagTable')->deleteItems(null, $tag_where);
            return ($nr > 0);
        } catch (\Exception $ex) {
            SharedStatic::doLogging($ex->getMessage());
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the collection returned an error'));
        }
    }
    
    /* Gives the file location of a reference.
     * We need this function to be public to be able to call it in the file rpc call
     * 
     */
    public function getFileLocation($file_ref_code, $file_name){
        return self::FILE_LOCATION."${file_ref_code}/$file_name";
    }
    
    /**
     * Sets the size and name parameters and derives the temporal name the server gave
     * to the uploaded file. Precondition is that there was a file uploaded without errors.
     * 
     * @param type $reference: the existing reference object
     * @param type $data; the data that was sent to be stored
     */
    private function deriveFromFile($reference, $file){
        $tmp_name = $file['tmp_name'];
        $reference->file_size = (isset($file['size'])) ?
            $file['size'] : filesize($tmp_name);
        $reference->file_name = $file['name'];
        $reference->file_type = $file['type'] ?: 
            $this->deriveType($reference->file_name, $tmp_name);
        return array($reference, $tmp_name);
    }
    
    private function removeFilesFromReference($reference, $file_name = '') {
        if ($reference->file_ref_code && ($reference->file_name || $file_name)) {
            if(!$file_name){
                $file_name = $reference->file_name;
            }
            $file_loc = $this->getFileLocation($reference->file_ref_code, $file_name);
            
            if (file_exists($file_loc)){
                SharedStatic::rrmdir(dirname($file_loc));
            } elseif(is_dir($file_loc)){
                SharedStatic::rrmdir($file_loc);
            } else {
                //TODO; should we report this to the user?
            }
        } else {
            //No files to remove
        }
    }
    private function isImageType($type){
        $type_parts = explode('/', $type);
        \Application\Shared\SharedStatic::doLogging(' waarom bewaart ie niet een plaatje goed'. $type, $type_parts);
        return ($type_parts[0] === 'image');
    }
    
    private function deriveType ($filename, $file_absolute=''){
        if (!function_exists('mime_content_type')) {
           function mime_content_type($filename) {

               $mime_types = array(
                   'txt' => 'text/plain',
                   'htm' => 'text/html',
                   'html' => 'text/html',
                   'php' => 'text/html',
                   'css' => 'text/css',
                   'js' => 'application/javascript',
                   'json' => 'application/json',
                   'xml' => 'application/xml',
                   'swf' => 'application/x-shockwave-flash',
                   'flv' => 'video/x-flv',

                   // images
                   'png' => 'image/png',
                   'jpe' => 'image/jpeg',
                   'jpeg' => 'image/jpeg',
                   'jpg' => 'image/jpeg',
                   'gif' => 'image/gif',
                   'bmp' => 'image/bmp',
                   'ico' => 'image/vnd.microsoft.icon',
                   'tiff' => 'image/tiff',
                   'tif' => 'image/tiff',
                   'svg' => 'image/svg+xml',
                   'svgz' => 'image/svg+xml',

                   // archives
                   'zip' => 'application/zip',
                   'rar' => 'application/x-rar-compressed',
                   'exe' => 'application/x-msdownload',
                   'msi' => 'application/x-msdownload',
                   'cab' => 'application/vnd.ms-cab-compressed',

                   // audio/video
                   'mp3' => 'audio/mpeg',
                   'qt' => 'video/quicktime',
                   'mov' => 'video/quicktime',

                   // adobe
                   'pdf' => 'application/pdf',
                   'psd' => 'image/vnd.adobe.photoshop',
                   'ai' => 'application/postscript',
                   'eps' => 'application/postscript',
                   'ps' => 'application/postscript',

                   // ms office
                   'doc' => 'application/msword',
                   'rtf' => 'application/rtf',
                   'xls' => 'application/vnd.ms-excel',
                   'ppt' => 'application/vnd.ms-powerpoint',

                   // open office
                   'odt' => 'application/vnd.oasis.opendocument.text',
                   'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
               );

               $ext = strtolower(array_pop(explode('.',$filename)));
               if (array_key_exists($ext, $mime_types)) {
                   return $mime_types[$ext];
               }
               elseif (function_exists('finfo_open')) {
                   $finfo = finfo_open(FILEINFO_MIME);
                   $mimetype = finfo_file($finfo, $filename);
                   finfo_close($finfo);
                   return $mimetype;
               }
               else {
                   return 'application/octet-stream';
               }
           }
           return mime_content_type($filename);
       } else {
           return mime_content_type($file_absolute);
       }
   }
    
    private function getFileUrl($ref_code, $file_name){
        return self::API_ADDRESS."file/${ref_code}/$file_name";
    }
    
    private function ownerCondition($user_id){
        $owner_pred = new Predicate\Predicate();
        $owner_pred->equalTo("reference.owner_id", $user_id);
        return $owner_pred;
    }
    
    private function setTags($result, $tags_added, $tags_deleted, $entity_id){
        if ((!$tags_added && !$tags_deleted) || !$result){
            return array(array(array(), array()), '');//Nothing added or deleted here
        }
        $msg = '';
        $deleted = null;
        $added = null;
        $tag_table_obj = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable');
        if ($tags_added){
            $data = (object) array('entity_id'=> $entity_id, 'tag_type'=> 'reference',
                'local_tag' => TRUE);
            $data->tag_txt = $tags_added;
            \Application\Shared\SharedStatic::doLogging('setTags 1 '.print_r($data, 1));
            
            //Tag result will be: array('result' => bool, 'msg' => str, 'added' => []]) or a Problem
            $tag_result = $tag_table_obj->create($data, self::DATA_TYPE_OBJECT, FALSE);
            
            \Application\Shared\SharedStatic::doLogging('setTags 1 '.print_r($tag_result, 1));
            if ($this->isProblem($tag_result)) {
                return $tag_result;
            } else {
                $added = $tag_result['added'];
                $msg .= $tag_result['msg'];
            }
        }
        if ($tags_deleted) {
            $data = (object) array('entity_id'=> $entity_id, 'tag_type'=> 'reference',
                 'local_tag' => TRUE);
            $data->tag_txt = $tags_deleted;
           
            $tag_result = $tag_table_obj->delete(0, $data, true);
            \Application\Shared\SharedStatic::doLogging('hier in setTags '.print_r($tag_result, 1));
            
            if ($this->isProblem($tag_result)) {
                return $tag_result;
            } else {
                $deleted = $tag_result['deleted'];
                $msg .= $tag_result['msg'];
            }
        }
        return array(array($added, $deleted), $msg);
    }
    
    private function gatherTags($result, $and=FALSE, $filter_tags=array()){
        $tag_results = array();
        $tag_labels = array();
        $curr_id = '';
        $curr_key = -1;
        $found_labels = array();
        foreach($result as $reference){
            if ($curr_id != $reference['reference_code']){
                //If the entity has tags: order maintaining keys
                if ($curr_key >-1 && isset($tag_results[$curr_key]) && $tag_results[$curr_key]['labels']){
                    /*
                     * If there is no filtering on tags, deliver all tags found
                     * If all tags should be found and indeed all found tags coincide with the tags searched
                     * for, it is all ok and return these tags sorted
                     * If some of tags should be found and the intersection of the asked and found tags 
                     * is, non-empty, it is ok too.
                     * Finally, if none of these hold, there was a filtering on tags that is
                     * not obeyed. Likely this case does not occur, but in that case we do not want 
                     * the entity included in the result
                     */                    
                    if ($filter_tags){
                        if(($and && (0 !== count(array_diff($filter_tags, array_keys($tag_results[$curr_key]['labels'])))))
                            ||
                            (!$and && (0 == count(array_intersect($filter_tags, array_keys($tag_results[$curr_key]['labels'])))))
                            ){
                            unset($tag_results[$curr_key]);
                        }
                    }
                }
                $curr_id = $reference['reference_code'];
                $curr_key++;
                if (isset($reference['tag.tag_txt']) && $reference['tag.tag_txt']){
                    $found_labels[] = $reference['tag.tag_txt'];
                    $reference['labels'] = array($reference['tag.tag_txt'] => 1); 
                } else {
                    //This is a dirty trick. The JsonHal converts empty strings to an array 
                    //in json format. We end up with a mixed array of objects and (empty) arrays
                    //Filters in angular will fail on that
                    $reference['labels'] = array('dummy' => 0); 
                }
                unset($reference['tag.tag_txt']);
                $tag_results[$curr_key] = $reference;
            } else {
                if (isset($reference['tag.tag_txt']) && $reference['tag.tag_txt']){
                    $found_labels[] = $reference['tag.tag_txt'];
                    $tag_results[$curr_key]['labels'][$reference['tag.tag_txt']] = 1;
                }
            }
        }
        //We have arrays of (tag, weight) tuples per found reference. Now if we wanted to filter on tags too
        //Some tests are performed depending on conditions whether all tags or only at least one should
        //be present. Check also last reference if not already done
        if ($filter_tags){
            if (($and && (0 !== count(array_diff($filter_tags, array_keys($tag_results[$curr_key]['labels'])))))
                ||
                (!$and && (0 == count(array_intersect($filter_tags, array_keys($tag_results[$curr_key]['labels'])))))
                ){
                unset($tag_results[$curr_key]);
            }
        }
        $tags = $filter_tags ?: array_values(array_unique($found_labels));
        $result = new \stdClass();
        $result->_embedded = array("labels" => $tags, "reference" => array_values($tag_results));
        return $result;
    }
    
    //When the terms to search for contain separators to group terms with respect to 
    //and/or operators, this function creates the correct array of arguments for that.
    private function getSearchArray($str){
        $put_back_subcomma = function($str){
            return str_replace('%2C', ',',  $str);
        };

        $str2 = preg_replace('/([\'\"])(.*?),(.*?)([\'\"])/', '$1$2%2C$3$4',  str_replace("'", '"', $str));
        $strs = explode(",", $str2);

        $new_strs = array_map($put_back_subcomma, $strs);

        $divide_or = function ($str){
            $search = trim($str);
            $quoted = explode("\"", $search);
            if (count($quoted) > 1){
                return str_getcsv($search, " ", '"');
            } else {
                return str_getcsv($search, " ");
            }
        };
        return array_map($divide_or, $new_strs);
    }
    
    private function createSearchPredicate($search_arr, $field_name){
         $term_condition = new Predicate\PredicateSet();
         foreach($search_arr as $or_arr){
             $or_set = new Predicate\PredicateSet();
             foreach ($or_arr as $or_term){
                 $or_set->addPredicate(new Predicate\Like($field_name, "%$or_term%"),
                   Predicate\PredicateSet::COMBINED_BY_OR);
             }
             $term_condition->addPredicate($or_set, Predicate\PredicateSet::COMBINED_BY_AND);
         }
         return $term_condition;
    }
    
    private function getExternalLinkParam($data){
        if (! _URL_FIELD_DEPRECATED && isset($data->url) && $data->url){
            return $data->url;
        }
        if (isset($data->external_url) && $data->external_url){
            return $data->external_url;
        }
        return null;
    }
    
    private function getImageFromUrl($url){
        //set base filename
        $file_base = pathinfo($url, PATHINFO_FILENAME);
        $file_base = preg_replace("([^\w\s\d\-_~,;\.\[\]\(\).])", '_', $file_base);
        $file_base = preg_replace("([\.]{1,})", '_', $file_base);
        
        if(!$file_base){
            //just in case
            $file_base = 'image';
        }
        
        //create temp filename
        $temp_file = tempnam(sys_get_temp_dir(), $file_base);
        
        //download file content and write to temp file
        $file_content = file_get_contents($url);
        file_put_contents($temp_file, $file_content);
        
        //determine image type
        $image_type = exif_imagetype($temp_file);
        if(!$image_type){
            //file is not an image
            unlink($temp_file);
            return false;
        }
        
        //create filename
        $file_ext = image_type_to_extension($image_type);        
        $file_name = $file_base . $file_ext;
        
        //determine mimeype
        $mime_type = image_type_to_mime_type($image_type);
                
        $file = array(
            "tmp_name" => $temp_file,
            "size" => filesize($temp_file),
            "name" => $file_name,
            "type" => $mime_type
        );

        return $file;
    }
    
    private function storeLocalImage($image_url, $reference){
        $image = array();
        $image_dir = 'image/';
        $details = json_decode($reference->details);
        if(isset($details->image_details)){
            $image_details = $details->image_details;
        }else{
            $image_details = (object) array();
        }
        
        if(!$image_url || 
             (isset($image_details->external_url) && $image_url === $image_details->external_url)){
            //image not changed
            return $image_details;
        }
        
        
        if(isset($image_details->file_name)){
            $this->removeFilesFromReference($reference, $image_dir.$image_details->file_name);
        }
        //retrieve image from remote url
        $file = $this->getImageFromUrl($image_url);
        
        if(!$file){
            //image retrieval failed
            return $image_details;
        }
        $image_details->external_url = $image_url;
        
        list($image_details, $tmp_name) = $this->deriveFromFile($image_details, $file);
        
        $image_details = $this->storeLocalFile((array) $image_details, $reference, $image_dir.$image_details->file_name, $tmp_name, true);
        
        return $image_details;
    }
    
    private function storeLocalFile($update_data, $reference, $file_name = false, $tmp_name, $external_file = false){
      	//Since the code will be unique over all references, the rotation 13 will be unique too
        $file_ref_code = str_rot13($reference->reference_code);
        
        if(!$file_name){
            $file_name = $reference->file_name;
            $set_name = true;
        }else{
            $set_name = false;
        }
        
        $real_file_name = $this->getFileLocation($file_ref_code, $file_name);
  
        //Here we try to store the actual file, i.e. move the temp file to the
        //desired location
        $target_dir = dirname($real_file_name);
        if (!file_exists($target_dir)){
            //just in case we're talking about a subdir:
            $parent_dir = dirname($target_dir);
            if (!file_exists($parent_dir)){
                mkdir($parent_dir, 0770, TRUE);
            }
            mkdir($target_dir, 0770, TRUE);
        }
        
        if($external_file){
            //file was not uploaded via PHP's HTTP POST upload mechanism
            rename($tmp_name, $real_file_name);
        }elseif (! move_uploaded_file($tmp_name, $real_file_name)){
            throw new \Exception("We could not store the file well.", 500);
        }
        //File will be created with web user as owner. Make it not readable for others 
        //who guess the file name
        chmod($real_file_name, 0660);
        
        $update_data['file_ref_code'] = $file_ref_code;
        $internal_url = $this->getFileUrl($reference->reference_code, $file_name);
        $update_data['internal_url'] = $internal_url;
        $update_data['image_url'] = $internal_url;
        
        if($set_name && !$reference->name){
            $update_data["name"] = $reference->file_name;
        }
        
        return $update_data;
    }
}