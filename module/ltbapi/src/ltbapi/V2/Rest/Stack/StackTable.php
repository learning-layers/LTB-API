<?php
namespace ltbapi\V2\Rest\Stack;

use Application\Model\ModelTableCapableSSS;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Expression;
use Application\Shared\SharedStatic;

class StackTable extends ModelTableCapableSSS {    
    public function fetchSelection($user_id=0, $selection_params = array()) {
        $return_details = FALSE;
        $return_tags    = TRUE;
        $filter_tags    = FALSE;
        $myfavourites   = FALSE;
        $author         = FALSE;
        $and            = TRUE;
        $public         = TRUE;
        $mine           = FALSE;
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
        
        if ($selection_params){
            //some params for what to return
            if (isset($selection_params['and'])) {
                 $and = $selection_params['and'];
            }
            if (isset($selection_params['show_details'])){
                $return_details = $selection_params['show_details'];
            }
            if (isset($selection_params['show_tags'])){
                $return_tags = $selection_params['show_tags'];
            }
            if (isset($selection_params['tags'])){
                $filter_tags = $selection_params['tags'] ? 
                    SharedStatic::getTrimmedList($selection_params['tags']) : null;
            }
            if (isset($selection_params['public'])){
                $public = $selection_params['public'];
            }
            if (isset($selection_params['mine'])){
                $mine = $selection_params['mine'];
            }
            if (isset($selection_params['favourites'])){
                $myfavourites = $selection_params['favourites'];
            }
            unset($selection_params['public'], $selection_params['mine'], $selection_params['favourites'], $selection_params['show_details'],
                $selection_params['tags'], $selection_params['show_tags'], $selection_params['and']
            );
        }
         
        if ($myfavourites){
            //If we want to have the favourites of this user, it makes no sense to
            //test on ownership. The stack was set to favourite at a moment the user
            //had access. We want users to be able to see all their favourites even
            //if the stack is not longer accessible. To make sure we do not return sensitive 
            //data, we set $return_details to false
            $other_params_cond = $this->favouriteCondition($user_id);
            $return_details = FALSE;
        } else {
            $other_params_cond = $this->ownerCondition($user_id, $public, TRUE, $mine);
        }
        $archived_cond = new Predicate\Literal('archived = 0');
        $other_params_cond = new Predicate\PredicateSet(array($archived_cond, $other_params_cond));
        if ($filter_tags){
            if (_CONNECT_SSS){
                //get all stack ids from Social Semantic Server for the tags specified
                //We ignore the other parameters space and forUser, as we want the maximum resultset
                $stack_ids_result = $this->callSocSemServer('getEntitiesByTag',
                    array('labels'=> $filter_tags), $this->account->getOpenIdToken());
                //Make a condition for that
                if ($this->isProblem($stack_ids_result)){
                    return $stack_ids_result;
                }
                $tag_cond = new Predicate\In('stack.stack_code', $stack_ids_result[0]);
            } else {
                //TODO: find out whether SSS tag searching is case sensitive. If it is not, 
                //we can move the line here to the line where we trim the list above
                $filter_tags = array_map('strtolower', $filter_tags);
                $tag_cond = new Predicate\In('tag.tag_txt', $filter_tags);
            }
            
            $other_params_cond = new Predicate\PredicateSet(array($tag_cond, $other_params_cond));
        }

        if ($selection_params){
            $pred_set = new Predicate\PredicateSet();
            //TODO make the descr and name in one OR statement if they both exist
            $combination = $and ? Predicate\PredicateSet::COMBINED_BY_AND : Predicate\PredicateSet::COMBINED_BY_OR;
            foreach ($selection_params as $fld => $val){
               if (($fld == 'name') || ($fld == 'description') || ($fld == 'terms') || ($fld = 'author')){
                   if ($fld == 'terms'){
                       /* This is how it used to be;
                        * So X, Y B => X && (Y || B) => Name( X && (Y || B)) || Description(X && (Y || B))
                        * Whereas it should be: Name(X) || Description(X) && (Name(Y || B) || Description(Y || B))
                        * $terms = $this->getSearchArray($val);
                       $name_predicate = $this->createSearchPredicate($terms, "stack.name");
                       $descr_predicate = $this->createSearchPredicate($terms, "stack.description");
                       $term_condition = new Predicate\PredicateSet();
                       $term_condition->addPredicate($name_predicate,
                             Predicate\PredicateSet::COMBINED_BY_OR);
                       $term_condition->addPredicate($descr_predicate,
                             Predicate\PredicateSet::COMBINED_BY_OR);
                       $pred_set->addPredicate($term_condition, $combination);
                        */
                       
                        /*
                        * getSearchArray will split the search string on the delimiter (,) and split the resulting
                        * subterms into an array of OR-terms (split on space). Note that this can also give empty terms
                        */
                       $terms = $this->getSearchArray($val);
                       if ($terms){
                            $term_condition = new Predicate\PredicateSet();
                            foreach ($terms as $or_terms){
                                 if ($or_terms){
                                    $name_predicate = $this->createSearchPredicateOr($or_terms, "stack.name", FALSE);
                                    $descr_predicate = $this->createSearchPredicateOr($or_terms, "stack.description", FALSE);
                                    $tag_predicate = $this->createSearchPredicateOr($or_terms, "tag.tag_txt", TRUE, TRUE);
                                    $author_predicate = $this->createSearchPredicateOr($or_terms, "user.name", FALSE);
                                    $or_condition = new Predicate\PredicateSet(array($name_predicate,
                                        $descr_predicate, $tag_predicate, $author_predicate), 
                                        Predicate\PredicateSet::COMBINED_BY_OR);
                                    $term_condition->addPredicate($or_condition,
                                        Predicate\PredicateSet::COMBINED_BY_AND);
                                }
                            }
                            $pred_set->addPredicate($term_condition, $combination);
                       }
                   } else {
                        $compare = ($fld === 'author') ? 'user.name' : "stack.$fld";
                        $pred_set->addPredicate(new Predicate\Like($compare, "%$val%"),
                            $combination);
                   }
               } else {
                    $pred_set->addPredicate(new Predicate\Operator("stack.$fld", '=', $val),
                        $combination);
               }
            }
            if ($pred_set->count()){
                $where = new Predicate\PredicateSet(array($pred_set, $other_params_cond));
            } else {
                $where = $other_params_cond;
            }
        } else {
            $where = $other_params_cond;
        }
        try {
            //this can be nicer: we know the select fields
            $field_set = $this->getCollectionFields('*');
            if ($return_details){
                //Add details field to result
                $field_set[] = 'details';
            }
            $join_spec = 
                array(//other tables to join with
                    array('user', " stack.owner_id = user.user_id ", 
                        array('name', 'user_id', 'user_code', 
                            'is_owner' => new Expression('user.user_id = '.$user_id)))
                );
            //There is a need to join with the table tags when we did not get the tags from 
            //Social semantic server and when there is some need (e.g. returning tags or filtering tags
            $local_tags_join = (! _CONNECT_SSS && ($return_tags || $filter_tags));
            if ($local_tags_join){
                //so we join on tags table
                $join_spec[] = 
                    array('tag', new Expression("stack.stack_id = tag.entity_id AND tag.tag_type = 'stack'"),
                        array(
                            'tag_txt',
                            'weight' => new Expression('COUNT(tag.tag_txt)'))
                    );
                if ($filter_tags){
                    $predicate = new Predicate\IsNotNull('tag_txt');
                    $where->addPredicate($predicate);
                }
            }
            $join_spec[] =
                array('favourite',
                        new Expression(" stack.stack_id = favourite.entity_id AND favourite.user_id = $user_id"),
                        array(
                            'favourite' => new Expression('favourite.favourite_id IS NOT NULL'))
                );
            $grouping = ($local_tags_join ? array('stack.stack_code', 'tag.tag_txt') : array('stack.stack_id'));
            
            $result = $this->getItemsJoin($where, true, $field_set, 
                array('stack.name','stack.stack_code'),//order
                $join_spec,
                $grouping,
                FALSE,
                FALSE, //output sql code for debugging purposes (will result in corrupt json response)
                $offset,
                $page_size
            );
            if (! $local_tags_join){
                if ($return_tags){ //implying _CONNECT_SSS
                    return $this->gatherSoSemTags($result, TRUE);
                }
            } else {
                if (is_string($result)){
                    //If _debug is on, the exception might be caught and returned
                    //as error string
                    return $this->returnProblem(500,
                        'List of stacks cannot be returned. '.$result);
                }
                if ($result && $return_tags){
                    $result = $this->gatherTags($result, $and, $filter_tags);
                }
            }
            //If we do not need the user, we can deliver the item like below
            //$result = $this->getItems($where, false, $field_set);
            
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'List of stacks cannot be returned');
        }
        
        return $result;
    }
    
    public function fetchOne($stack_id, $user_id=0) {
        if (!$user_id || !is_numeric($user_id)){
            $user_id = 0;
        }
        $return_details = TRUE;
        $return_tags = TRUE;
        $return_favourite = TRUE;
        
        $selection_params = $_GET;
        if ($selection_params){
            //some params for what to return
            $return_details = $return_details ||
                (isset($selection_params['show_details']) && $selection_params['show_details']);
            $return_tags = $return_tags || 
                (isset($selection_params['show_tags']) && $selection_params['show_tags']);
        }

        try {
            //stackId is most likely a stack code but if not we convert it to one
            $stack_id = $this->convertCodeToId($stack_id);
            $owner_cond = $this->ownerCondition($user_id, TRUE, FALSE);
            $no_archived_cond = new Predicate\Literal('archived = 0');
            //For now we do not deliver stacks that were deleted (in archived modus)
            
            //Note that the result can be False (no items) if there are either no
            //items or the item is not public and the user forgot to login
            //The result will be the same: a 404 Entity not found apiproblem.
            //If the result of the getItemsJoin is an error string, this should
            //be shown as the debug is on in that case. Otherwise it is false 
            //and the mentioned 404 will be returned.
            
            if ($return_favourite || $return_tags || ! $return_details){
                //$favourite_cond = $this->favouriteCondition($user_id);
                $id_pred = new Predicate\Predicate();
                $id_pred->equalTo($this->table.".".$this->id_name, $stack_id);
                $where = new Predicate\PredicateSet(array($id_pred, $no_archived_cond, $owner_cond));//, $favourite_cond));
                $field_set = $this->getEntityFields();
                $order = NULL;
                $grouping = NULL;
                if (! $return_details){
                    unset($field_set['details']);
                }
                $join_on_tags = $return_tags && ! _CONNECT_SSS;

                //joins specs: [table_str, on_spec_str, [<alias> => expr_str|fld_str]]
                $joins = array();
                $tags = array();
                if ($join_on_tags){
                    $joins[] = array(
                        'tag', 
                        new Expression("stack.stack_id = tag.entity_id AND tag.tag_type = 'stack'"),
                        array(
                            'tag_txt',
                            'weight' => new Expression('COUNT(tag.tag_txt)'))
                    );
                    $order = array('stack.name','stack.stack_code');
                    $grouping = array('stack.stack_code', 'tag.tag_txt');
                }
                if ($return_favourite){
                    $joins[] = array(
                        'favourite', 
                        new Expression(" stack.stack_id = favourite.entity_id AND favourite.user_id = $user_id"),
                        array(
                          'favourite' => new Expression('favourite.favourite_id IS NOT NULL'))
                    );
                }
                //Get the owner of the stack
                $joins[] = array(
                        'user', 
                        'stack.owner_id = user.user_id',
                        array('owner_name' => 'name', 'is_owner' => new Expression('user.user_id = '.$user_id))
                    );
                //7th arg is saying whether it is a singleRecord or not.
                //8th arg should be false: showing the query for debugging purposes
                $result = $this->getItemsJoin($where, TRUE, $field_set, $order,
                    $joins, $grouping, (!$join_on_tags), ('show_query' === 'no'));
                if (is_string($result)){
                    return $this->returnProblem(500, 'Stack cannot be returned: '.$result);
                } elseif ($result){
                    if ($join_on_tags){
                        if ($result){
                            $result = SharedStatic::first($this->gatherTags($result));
                        }
                    } elseif ($return_tags){
                        $result = $this->gatherSoSemTags($result);
                    }
                    $my_tags_results = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')->
                        fetchSelection(array(
                            'entity_id' => $stack_id,
                            'owner_id'  => $user_id,
                            'tag_type'  => 'stack'), true);
                    $my_tags = array();
                    foreach ($my_tags_results as $tag_result){
                        $my_tags[] = $tag_result['tag_txt'];
                    }
                    $result['my_tags'] = $my_tags;
                }
            } else {
                /* This is how it used to be: only one join needed.  8th arg for single record */
                $where = new Predicate\PredicateSet(array($no_archived_cond, $owner_cond));
                $result = $this->getItemPair($stack_id, 'user', array('owner_id' => 'user_id'),
                    array('user' => array('owner_name' => 'name', 'user_code', 'is_owner' => new Expression('user.user_id = '.$user_id)), $this->table => ''),
                    TRUE, $where, NULL, TRUE, ('show_query' === 'no'));
            }
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'Stack cannot be returned');
        }
        //If the stack was not found and the user_id was 0, this is because the 
        //stack was not public and the 'current user' is not the owner or not 
        //logged in. In that case we return a 403 iff getItem returns a result
        if (! $result){
            $item = $this->getItem($stack_id);
            if ($item){
                //Note that the int fields from the database are strings, so we cannot do a strong typed compare
                if ($item->owner_id != $user_id){
                    return $this->returnProblem(403, "To see this stack, You must be logged in and be the owner '
                        . 'as it is not publicly available.");
                } elseif ($item->archived == 1){
                    return $this->returnProblem(404, 'This stack has been deleted in archive modus and is not available.');
                } else {
                    return $this->returnProblem(500, "You are not allowed to see this stack.");
                }
            }
        }
        return $result;
    }
    
    /* TODO: the distinction between arrays and objects seems irrelevant: we only call
     * this function from the corresponding resource and that class calls this function
     * with a data object.
     */
    
    /* TODO remove tags_deleted as it makes no sense for newly created stacks....*/
    public function create($user_id, $data, $data_type = self::DATA_TYPE_OBJECT) {
       $id_name = $this->id_name;
       $tags_set = false;
       if ($data_type == self::DATA_TYPE_OBJECT) {
            if (isset($data->details)) {
                if (!is_array($data->details)) {
                   $data->details =  json_decode($data->details);
                }
                $current_tags = (isset($data->current_tags) && $data->current_tags) ? 
                    $data->current_tags : [];
                $old_tags = (isset($data->my_tags) && $data->my_tags) ? 
                    $data->my_tags : [];
                
                $tags_added = array_diff($current_tags, $old_tags);
                $tags_deleted = array_diff($old_tags, $current_tags);
                unset($data->current_tags);
                unset($data->my_tags);
                
                $data->details = json_encode($data->details);
            } else {
                return $this->returnProblem(406, 'Creation of new stack without '.
                    'details field is not meaningfull');
            }
            unset($data->$id_name);
        } elseif ($data_type == self::DATA_TYPE_ARRAY) {
            unset($data[$this->id_name]);
            if (isset($data['details'])) {
                if (! is_array($data['details'])){
                    $data['details'] = json_decode($data['details']);
                }
                $current_tags = (isset($data['current_tags']) && $data['current_tags']) ? 
                    $data['current_tags'] : [];
                $old_tags = (isset($data['my_tags']) && $data['my_tags']) ? 
                    $data['my_tags'] : [];
                
                $tags_added = array_diff($current_tags, $old_tags);
                $tags_deleted = array_diff($old_tags, $current_tags);
                unset($data['current_tags']);
                unset($data['my_tags']);
              
                $data['details'] = json_encode($data['details']);
            } else {
               return $this->returnProblem(406, 'Creation of new stack without'.
                    'details field is not meaningfull');
            }
        } else {
            return $this->returnProblem(406, 'Creation of new stack with wrong data somehow.');
        }
             
        $stack = $this->getModel($data, $data_type);
        $stack->owner_id = $user_id;
        $stack->owner_code = $this->account->getCurrentUserCode();
        $ts = time();
        $stack->create_ts = $ts;
        $stack->update_ts = $ts;
        if (isset($data->public)){//public has a default set in the StackEntity
            $stack->public = ($data->public && in_array($data->public, array(0,1,2))? $data->public : 0);
            if (isset($data->access_level)){//access_level has a default in the database
                if (!in_array($data->access_level, array(0,1,2))){
                    return $this->returnProblem(406, 'Creation of new stack with access level that is not allowed.');
                }
                $stack->access_level = $data->access_level;
            }
        }
        $result = FALSE;
        $sss_result = FALSE;
        $warnings = '';
        //TODO msgs can go as soon as we do not use the msgs returned by setTags. This seems the case already
        $msg = '';
        try {
            $result_id = $this->saveItem($stack, false, false, true);
            if ($result_id){
                $code = SharedStatic::getShortCode($result_id);
                $result = (bool) $this->updateItem($result_id, '', array('stack_code' => $code));
                $continue = TRUE;
                //Save to Social Semantic Server too
                if ($result && _CONNECT_SSS){
                    $data = array(
                        'app' => SharedStatic::altProperty($stack,'app', $code),
                        'label' => SharedStatic::altProperty($stack, 'name', 'Stack without title'),
                        'description' => SharedStatic::altProperty($stack, 'description', 'No description'),
                        'stack' => $code
                     );
                    $sss_result = $this->callSocSemServer('addStack', $data, $this->account->getOpenIdToken());
                    if ($this->isProblem($sss_result)){
                        $continue = FALSE;
                        $warnings = $this->composeWarnings($sss_result);
                    }
                }
                if ($result && $continue){
                    $tag_result = $this->setTags($result, $tags_added, $tags_deleted, $code);
                    $tags_set = TRUE;
                    if ($this->isProblem($tag_result)){
                        //return $tag_result;
                        $warnings = $this->composeWarnings($tag_result);
                    } else {
                        list($result, $msg) = $tag_result;
                    }
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'We could not save the stack.');
        }
        try {
            if ($result){
                if (! $result = $this->getItem($result_id, false, false, true)) {
                    throw new \Exception('The stack seems to be saved, but could not '.
                        'be retrieved afterwards.');
                } else {
                    if ($warnings && _DEBUG){
                        $result->warnings = $warnings;
                    }
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, $ex->getMessage());
        }
        
        //We do not want all the details returned in the result
        unset($result->details);
        $user_name_fld = 'user.name';
        $result->$user_name_fld = $this->account->getCurrentUserName();
        $result->tags = $tags_added;
        //TODO: the next line is for debugging only. To see what social Semantic Server returns
        if ($tags_set && _DEBUG) {
            $result->temporal_tags_message = $msg;
        }
        if (_CONNECT_SSS && _DEBUG) {
            $result->temporal_sss_message = $sss_result[1];
        }
        
        return array('result' => $result);
    }

    /*
     * Will be the result from a PUT /stack/[stack_id] call. 
     */

    public function update($user_id, $id, $data) {
        if (isset($data->is_patch) && $data->is_patch){
            return $this->patch($user_id, $id, $data);
        }
        
        //id is most likely a stack code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($stack = $this->getItem($id)) {
            if (!$this->account->checkOwner($stack, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this stack.');
            }
        } else {
            return $this->returnProblem(404, 'The stack did not exist');
        }
        $sss_affected = (isset($data->name) && ($stack->name != $data->name) ) ||
            (isset($data->description) && ($stack->description != $data->description) );
        if (!isset($data->version)) {
            $data->version = SharedStatic::increaseVersion($stack->version);
        }
        //Keep non-default fixed values that we do not want to send via the api
        $data->owner_id = $stack->owner_id;
        $data->owner_code = $stack->owner_code;
        $data->stack_code = $stack->stack_code;
        $data->create_ts = $stack->create_ts;
        $tags_set = FALSE;
        $tags = [];
        if (isset($data->details)) {
            if (is_string($data->details && $data->details == 'IGNORE')){
                unset($data->details);
            }
            $current_tags = (isset($data->current_tags) && $data->current_tags) ? 
               \Application\Shared\SharedStatic::getTrimmedList($data->current_tags) : 
               [];
            $old_tags = (isset($data->my_tags) && $data->my_tags) ? 
                \Application\Shared\SharedStatic::getTrimmedList($data->my_tags) : [];

            $tags_added = array_diff($current_tags, $old_tags);
            $tags_deleted = array_diff($old_tags, $current_tags);
            unset($data->current_tags);
            unset($data->my_tags);
            $data->details = json_encode($data->details);
        }
        if (isset($data->public)){
            $stack->public = ($data->public && in_array($data->public, array(0,1,2))? $data->public : 0);
            if (isset($data->access_level)){//access_level has a default in the database
                if (!in_array($data->access_level, array(0,1,2))){
                    return $this->returnProblem(406, 'Update of stack with access level that is not allowed.');
                }
                $stack->access_level = $data->access_level;
            }
        }
        if (self::StrictUpdate) {
            //This will update the whole object. So all keys not set, will be replaced
            //by their default value
            $stack->exchangeObject($data);
        } else {
            $stack->setObjectValues($data);
        }
        $stack->stack_id = $id;
        $ts = time();
        $stack->update_ts = $ts;
        $warning = '';
        try {
            $result = (bool) $this->saveItem($stack, TRUE);
            $tag_result = $this->setTags($result, $tags_added, $tags_deleted, $stack->stack_code);    
            if ($this->isProblem($tag_result)){
                return $tag_result;
            } else {
                list($result, $msg) = $tag_result;
            }
            if ($result && _CONNECT_SSS && $sss_affected){
                $data = array(
                'label' => $stack->name,
                'description' => $stack->description,
                'stack' => $stack->stack_code);
                $sss_result = $this->callSocSemServer('changeStack', $data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    $warning = $sss_result->getTitle(). $sss_result->getDetail();
                        //return $sss_result;
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem($ex->getCode(), 
                (_DEBUG ? $ex: 'The stack cannot be updated')); 
        }
        $return = array('result' => (bool) $result);
        if ($warning){
            $return['warning'] = $warning;
        }
        return $return;
    }

    /*
     * Will be the result from a PATCH /stack/[stack_id] call. 
     */
    public function patch($user_id, $id, $data) {
        
        //id is most likely a stack code but if not we convert it to one
        \Application\Shared\SharedStatic::debugLog("StackTable: 574: In patch voor $user_id:", $data);
        $id = $this->convertCodeToId($id);
        if ($stack = $this->getItem($id)) {
            if (!$this->account->checkOwner($stack, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this stack.');
            }
        } else {
            return $this->returnProblem(404, 'The stack did not exist');
        }
        $sss_affected = (isset($data->name) && ($stack->name != $data->name) ) ||
            (isset($data->description) && ($stack->description != $data->description));
        if (!isset($data->version)) {
            $data->version = SharedStatic::increaseVersion($stack->version);
        }
        $tags_set = FALSE;
        $tags = [];
        if (isset($data->details)) {
            if (is_string($data->details && $data->details == 'IGNORE')){
                unset($data->details);
            }
           $current_tags = (isset($data->current_tags) && $data->current_tags) ? 
               \Application\Shared\SharedStatic::getTrimmedList($data->current_tags) : 
               [];
            $old_tags = (isset($data->my_tags) && $data->my_tags) ? 
                \Application\Shared\SharedStatic::getTrimmedList($data->my_tags) : [];
            
            $tags_added = array_diff($current_tags, $old_tags);
            $tags_deleted = array_diff($old_tags, $current_tags);
            unset($data->current_tags);
            unset($data->my_tags);
            
            $data->details = json_encode($data->details);
        } else {
            $tags_added = null;
            $tags_deleted = null;
        }
        $warnings = '';
        $ts = time();
        $data->update_ts = $ts;
        if (isset($data->public)){
            $stack->public = ($data->public && in_array($data->public, array(0,1,2))? $data->public : 0);
            if (isset($data->access_level)){//access_level has a default in the database
                if (!in_array($data->access_level, array(0,1,2))){
                    return $this->returnProblem(406, 'Updating stack with access level that is not allowed.');
                }
                $stack->access_level = $data->access_level;
            }
        }
        try {
            $result = (bool) $this->updateItem($id, '', $data, TRUE);
            \Application\Shared\SharedStatic::debugLog('StackTable: 620: hier resultaar van updateItem '.print_r($result, 1));
            
            $tag_result = $this->setTags($result, $tags_added, $tags_deleted, $stack->stack_code);
            \Application\Shared\SharedStatic::debugLog('StackTable: 623: hier resultaar van setTags '.print_r($tag_result, 1));
            if ($this->isProblem($tag_result)){
                \Application\Shared\SharedStatic::debugLog("StackTable: 625: In patch voor $id:", $result);
                return $tag_result;
            }
            list($result, $msg) = $tag_result;
            if ($result && $sss_affected && _CONNECT_SSS){
                $sss_data = array(
                    'label' => isset($data->name) ? $data->name : '',
                    'description' => isset($data->description) ? $data->description : '',
                    'stack' => $stack->stack_code
                );
                $sss_result = $this->callSocSemServer('changeStack', $data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    $warnings = $this->composeWarnings($sss_result);
                    SharedStatic::doLogging('We could not store the modifications to the stack well in SSS.'.
                        " We got: [$warnings]");
                }
            }
        } catch (\Exception $ex) {
            \Application\Shared\SharedStatic::debugLog("StackTable: 643: In patch voor $id:", $ex);
            return $this->returnProblem(500, 
                (_DEBUG ? $ex: 'The stack cannot be patched')); 
        }
        \Application\Shared\SharedStatic::debugLog("StackTable: 647: In patch voor $id:", array('result'=>$result, 'warnings' => $warnings));
        $return = array('result' => (bool) $result);
        if ($warnings){
            $return['warning'] = $warnings;
        }
        return $return;
    }

    public function delete($user_id, $stack_id, $data=null){
        
        //id is most likely a stack id but if not we convert it to one
        $stack_id = $this->convertCodeToId($stack_id);
        if ($stack = $this->getItem($stack_id)) {
            if (!$this->account->checkOwner($stack, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this stack.');
            }
        } else {
            return $this->returnProblem(404, 'The stack did not exist.');
        }
        
        if ($data && isset($data['real']) && $data['real']){
            return $this->realDelete($user_id, $stack_id, TRUE, $stack);
        }
        
        //So it is a soft delete, which only updates the archived property
        $result = $msg = '';
        try {
            $result = $msg = '';
            // public function updateItem($id='', $where='', $data='', $existing=FALSE, $log_update=FALSE) {
            $nr = $this->updateItem($stack_id, null, array('archived' => 1), TRUE, TRUE);
            if ($nr != 0){
                $result = TRUE;
                $warnings = '';
                if (_CONNECT_SSS){
                    $data = array('stack' => $stack->stack_code);
                    $sss_result = $this->callSocSemServer('deleteStack', $data,
                        $this->account->getOpenIdToken());
                    if ($this->isProblem($sss_result)){
                        $warnings = $this->composeWarnings($sss_result);
                    }                    
                    //We specify no payload (stack will end in the uri of the call
                    //DELETE entities/tags/<stack_code>) which will delete all tags 
                    //attached to the stack
                    /*
                     * NB: At the moment there is a bug in SSS so we can not send empty
                     * payload to remove all tags attached to the stack to be deleted.
                     * When Jira issue no 1064 is solved, we can uncomment this
                     * //TODO: test again after Dieter finished LL-1064
                     * 1064 is closed: test again
                     */
                }
                
            }
            return $this->returnResponse($result, $warnings);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the stack returned an error.'));
        }
    }
    
    /* Since this resource is called from within the Rest Resource.php class,
     * it has to return a boolean only.
     */
    private function realDelete($user_id, $stack_id, $checked=FALSE, $stack=NULL){
        $result = $msg = '';
        if (!$checked){
            //id is most likely a stack code but if not we convert it to one
            $stack_id = $this->convertCodeToId($stack_id);
            if ($stack = $this->getItem($stack_id)) {
                if (!$this->account->checkOwner($stack, 'owner_id', $user_id)) {
                    return $this->returnProblem(403, 'You are not the owner of this stack.');
                }
            } else {
                return $this->returnProblem(404, 'The stack did not exist.');
            }
        }
        try {
            $nr = $this->deleteItems($stack_id);
            if ($nr != 0){
                $result = TRUE;
                if (_CONNECT_SSS){
                    $data = array('stack' => $stack->stack_code);
                    $sss_result = $this->callSocSemServer('deleteStack', $data,
                    $this->account->getOpenIdToken());
                    if ($this->isProblem($sss_result)){
                        $warnings = $this->composeWarnings($sss_result);
                    }                    
                    //We specify no payload (stack will end in the uri of the call
                    //DELETE entities/tags/<stack_code>) which will delete all tags 
                    //attached to the stack
                    /*
                     * NB: At the moment there is a bug in SSS so we can not send empty
                     * payload to remove all tags attached to the stack to be deleted.
                     * When Jira issue no 1064 is solved, we can uncomment this
                     * //TODO: test again after Dieter finished LL-1064
                     * 1064 is closed: test again
                     */
                }
                if ($tag_table = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')){
                    //this will delete the local tags and possibly also the SSS tags when connected
                    $result = $tag_table->deleteTagsAttached($stack_id);
                    if ($this->isProblem($result)){
                        \Application\Shared\SharedStatic::doLogging($this->composeWarnings($result));
                        $warnings .= $this->composeWarnings($result);
                        return $result;
                    } else {
                        list($result, $warnings) = $result;
                    }
                }
                //Handling favourites (which are not in SSS but in API db only)
                if ($result && $favourite_table = $this->getOtherTable('ltbapi\V2\Rest\Favourite\FavouriteTable')){
                    $result = $favourite_table->deleteFavouritesAttached($stack_id);
                    if ($this->isProblem($result)){
                        return $result;
                    }  
                }
                if ($result && $message_table = $this->getOtherTable('ltbapi\V2\Rest\Message\MessageTable')){
                    $result = $message_table->deleteMessagesAttached($stack_id);
                    if ($this->isProblem($result)){
                        return $result;
                    }  
                }
            }
            return $this->returnResponse($result, $warnings);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the stack (or related data) returned an error.'));
        }
    }
    
    public function deleteList($user_id=0, $data=null) {
        $result = $msg = '';
        $where = $data ?: array();
        
        $where['owner_id'] = SharedStatic::altValue($user_id,
            $this->account->getCurrentUserId());
        if (!$where['owner_id']){
            //When _DEBUG is on and the authentication check was omitted, we might get 0 here
            return $this->returnProblem(401, 'You should be logged in to delete a collection of stack');
        }
        if (! _DEBUG){//so we can delete a whole serie of test data from our own hand
            return $this->returnProblem(405, 
                ('The deletion of a list of stacks is possible but at the moment not allowed'));
        }
        try {
            $nr = $this->deleteItems(null, $where);
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the collection returned an error'));
        }
    }

    /** 
     * Gives the predicate to determine the access to a stack in either a search or
     * a fetch action.
     * 
     * Should return true according following scheme for a stack s and access level
     * al:
     * if s.public = 0: it is a private stack, check owner
     * if s.public = 1: owner OR al = 0 OR (user logged in AND (al = 1 OR (al = 2 AND s.domain in
     * domains of user)))
     * if s.public = 2: owner OR (it is not a search AND (owner OR al = 0 OR (user is logged in AND
     * (al=1 OR (al =2 AND s.domain in domains of user))     * 
     * 
     * @param int $user_id
     * @param bool $include_public
     * @param bool $is_search
     * @return \Zend\Db\Sql\Predicate\Predicate|\Zend\Db\Sql\Predicate\PredicateSet 
     */
    private function ownerCondition($user_id, $include_public = TRUE, $is_search=TRUE, $force_owner = FALSE){
        if ($user_id != 0){
            if ($this->account->isModerator() && !$force_owner){
                $owner_pred = new Predicate\Literal("'moderator' = 'moderator'");
            } else {
                $owner_pred = new Predicate\Predicate();
                $owner_pred->equalTo("stack.owner_id", $user_id);
            }
        } else {
            $owner_pred = new Predicate\Literal('1 = 2');
        }
        
        if ($include_public){
            $public_cond = new Predicate\PredicateSet();
            $public_pred = new Predicate\Predicate();
            if ($is_search){
                //Stack should be public or owned
                $public_pred->equalTo('stack.public', 1);
            } else {
                //should be 1 (public) or 2 (public but hidden in search), So not private (0).
                $public_pred->greaterThan('stack.public', 0);
            }
            
            if ($user_id != 0){
                $access_pred0 = new Predicate\Predicate();
                $access_pred0->equalTo('stack.access_level', 0);
                $access_pred1 = new Predicate\Predicate();
                $access_pred1->equalTo('stack.access_level', 1);
                $access_pred2 = new Predicate\Predicate();
                $access_pred2->equalTo('stack.access_level', 2);
                
                $user_domains = $this->account->getUserDomains($user_id);
                $in_domains_pred = new Predicate\In('stack.domain', $user_domains);
                $access_cond2 = new Predicate\PredicateSet(array($access_pred2, $in_domains_pred));
                $user_cond = new Predicate\PredicateSet(array($access_pred0, $access_pred1,
                    $access_cond2), Predicate\PredicateSet::COMBINED_BY_OR);
            } else {
                $user_cond = new Predicate\Predicate();
                $user_cond->equalTo('stack.access_level', 0);
            }
            $public_cond->addPredicates(array($public_pred, $user_cond));
            $public_owner_cond = new Predicate\PredicateSet();
            $public_owner_cond->addPredicate($owner_pred)->addPredicate($public_cond,
               Predicate\PredicateSet::COMBINED_BY_OR);            
            return $public_owner_cond;
        } else {
            //include_public == false meaning show only my own stacks
            return $owner_pred;
        }
    }
    
    private function favouriteCondition($user_id){
        $owner_pred = new Predicate\Predicate();
        $owner_pred->equalTo("favourite.user_id", $user_id);
        return $owner_pred;
    }
    
    //When the terms to search for contain separators to group terms with respect to 
    //and/or operators, this function creates the correct array of arguments for that.
    private function getSearchArray($str){
        if (!$str) return array();
        $put_back_subcomma = function($str){
            return str_replace('%2C', ',',  $str);
        };

        //Save the commas we encounter in literal strings ("xxx, yy" and 'xxx, yy, zz' etc.)
        //Split thereafter on comma which separates the terms that should be combined by the
        //and-specification combination so that we have 
        $str2 = preg_replace('/([\'\"])(.*?),(.*?)([\'\"])/', '$1$2%2C$3$4',  str_replace("'", '"', $str));
        $strs = explode(",", $str2);
        $new_strs = array_map($put_back_subcomma, $strs);

        //str_getcsv splits on the delimiter (,) and respects strings enclosed in " as one literal
        $divide_or = function ($str){
            $search = trim($str);
            $quoted = explode("\"", $search);
            if (count($quoted) > 1){
                return str_getcsv($search, " ", '"');
            } else {
                return str_getcsv($search, " ");
            }
        };
        $x = array_map($divide_or, $new_strs);
        return array_map($divide_or, $new_strs);
    }
    
    private function createSearchPredicateOr($or_arr, $field_name, $exact=FALSE, $in=FALSE){
        if ($in){
            return new Predicate\In($field_name, $or_arr);
        }
        
        $or_condition = new Predicate\PredicateSet();
        //Note: A split is made on space. Where the user types more than one space, the 
        //resulting empty string or_terms should be ignored of course
        if ($exact){
            foreach ($or_arr as $or_term){
                if ($or_term){
                    $or_condition->addPredicate(new Predicate\Operator($field_name, 
                        Predicate\Operator::OP_EQ, $or_term),
                        Predicate\PredicateSet::COMBINED_BY_OR);
                }
            }        
        } else {
            foreach ($or_arr as $or_term){
                if ($or_term){
                    $or_condition->addPredicate(new Predicate\Like($field_name, "%$or_term%"),
                      Predicate\PredicateSet::COMBINED_BY_OR);
                }
            }
        }
        return $or_condition;
    }
    
    private function createSearchPredicate($search_arr, $field_name){
         $term_condition = new Predicate\PredicateSet();
         foreach($search_arr as $or_arr){
             $or_set = new Predicate\PredicateSet();
             foreach ($or_arr as $or_term){
                 if ($or_term){
                    //A split is made on space. Where the user types more than one space, the 
                    //resulting empty string or_terms should be ignored of course
                    $or_set->addPredicate(new Predicate\Like($field_name, "%$or_term%"),
                      Predicate\PredicateSet::COMBINED_BY_OR);
                 }
             }
             $term_condition->addPredicate($or_set, Predicate\PredicateSet::COMBINED_BY_AND);
         }
         return $term_condition;
    }
    
    private function setTags($result, $tags_added, $tags_deleted, $code){
        if ((!$tags_added && !$tags_deleted) || !$result){
            return array($result, '');
        }
        if ($tags_added){
            $data = (object) array('entity_id'=> $code, 'tag_type'=> 'stack', 'tag_txt'=>
                $tags_added);
            \Application\Shared\SharedStatic::doLogging('setTags 1 '.print_r($data, 1));
            
            $tag_result = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')->
                create($data, self::DATA_TYPE_OBJECT);
            
            \Application\Shared\SharedStatic::doLogging('setTags 1 '.print_r($tag_result, 1));
            if ($this->isProblem($tag_result)) {
                return $tag_result;
            }
        }
        if ($tags_deleted) {
            $data = (object) array('entity_id'=> $code, 'tag_type'=> 'stack', 'tag_txt'=>
                $tags_deleted);
            $tag_result = $this->getOtherTable('ltbapi\V2\Rest\Tag\TagTable')->
                delete(0, $data);
            \Application\Shared\SharedStatic::doLogging('hier in setTags '.print_r($tag_result, 1));
            
            if ($this->isProblem($tag_result)) {
                return $tag_result;
            } 
        }
        return array(TRUE, '');
    }
    
    private function gatherSoSemTags($result, $is_collection=FALSE){
        $entity_list = $is_collection ? $this->getColumnFromResult('stack_code', $result):
            array($result['stack_code']);
        $sss_result = $this->callSocSemServer('getTags',
            array('entities' => $entity_list),
            $this->account->getOpenIdToken()
        );
        if ($this->isProblem($sss_result)){
            //TODO: what should we do with social semantic server failure
            //return $sss_result;
            SharedStatic::doLogging('FAIL: The SSS tried to retrieve tags. '.
                "That failed. Result of this call: ".
                print_r($sss_result, 1));
            return $result;
        }
        if ($sss_result[0]){
            //tags are found
            $tag_results = array();
            foreach ($sss_result[0] as $tag_struct){
                if (isset($tag_results[$tag_struct['entity']])){
                    $tag_results[$tag_struct['entity']][] = $tag_struct['tagLabel'];
                } else {
                    $tag_results[$tag_struct['entity']] = array($tag_struct['tagLabel']);
                }
            }
            //Walk through original results and add tags field if the corresponding 
            //key ("http://sss.eu/" + stack_code) is existent in the sss result
            if ($is_collection){
                foreach ($result as $k => $stack){
                    if (isset($tag_results["http://sss.eu/".$stack['stack_code']])){
                        $result[$k]['tags'] = $tag_results["http://sss.eu/".$stack['stack_code']];
                    } else {
                        $result[$k]['tags'] = array();
                    }
                }
            } else {
                if (isset($tag_results["http://sss.eu/".$result['stack_code']])){
                    $result['tags'] = $tag_results["http://sss.eu/".$result['stack_code']];
                } else {
                    $result['tags'] = array();
                }
            }
            return $result;
        } else {
            if ($is_collection){
                foreach ($result as $k => $stack){
                    $result[$k]['tags'] = array();
                }
            } else {
                $result['tags'] = array();
            }
            return $result;
        }
    }
    
    private function gatherTags($result, $and=FALSE, $filter_tags=array()){
        $tag_results = array();
        $curr_id = '';
        $curr_key = -1;
        $sorted = FALSE;
        
        foreach($result as $stack){
            if ($curr_id != $stack['stack_code']){
                //if stack has tags: order with key maintained
                if ($curr_key >-1 && $tag_results[$curr_key]['tags']){
                    if (!$filter_tags){
                         arsort($tag_results[$curr_key]['tags']);
                         $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);
                    } elseif ($and && !count(array_dif($filter_tags, array_keys($tag_results[$curr_key]['tags'])))){
                         arsort($tag_results[$curr_key]['tags']);
                        $tag_results[$curr_key]['tags'] =  array_keys($tag_results[$curr_key]['tags']);
                    } elseif (!$and && count(array_intersect($filter_tags, array_keys($tag_results[$curr_key]['tags'])))){
                         arsort($tag_results[$curr_key]['tags']);
                         $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);
                    } else {
                        //not valid with respect to filter
                         unset($tag_results[$curr_key]);
                    }
                    $sorted = TRUE;
                }
                $curr_id = $stack['stack_code'];
                $curr_key++;
                $stack['tags'] = (isset($stack['tag.tag_txt']) &&  $stack['tag.tag_txt'] ? 
                    array($stack['tag.tag_txt'] => $stack['weight']):
                    array());
                unset($stack['tag.tag_txt'], $stack['weight']);
                $tag_results[$curr_key] = $stack;
            } else {
                $tag_results[$curr_key]['tags'][$stack['tag.tag_txt']]
                    = $stack['weight'];
                $sorted = FALSE;
            }
        }
        //We have arrays of (tag, weight) tuples per found stack. Now if we wanted to filter on tags too
        //Some tests are performed depending on conditions whether all tags or only minimal one should
        //be present.
        //Sort also last stack if not already done
        if (!$sorted){
            if (!$filter_tags){
                arsort($tag_results[$curr_key]['tags']);
                $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);
            } elseif ($and && !count(array_diff($filter_tags, array_keys($tag_results[$curr_key]['tags'])))){
                arsort($tag_results[$curr_key]['tags']);
                $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);
            } elseif (!$and && count(array_intersect($filter_tags, array_keys($tag_results[$curr_key]['tags'])))){
                arsort($tag_results[$curr_key]['tags']);
                $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);
            } else {
                //not valid with respect to filter
                 unset($tag_results[$curr_key]);
            }
        } else {
            //The last stack record can have a key tags => [tag_txt => weight] we do not have
            //to sort in that case, but we need to omit the weight in the result
            $tag_results[$curr_key]['tags'] = array_keys($tag_results[$curr_key]['tags']);            
        }
        return $tag_results;
    }
}