<?php
namespace ltbapi\V2\Rest\Message;

use Application\Model\ModelTable;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Expression;
use Application\Shared\SharedStatic;

class MessageTable extends ModelTable {

    public function fetchSelection($current_user_id=0, $selection_params = array()) {
        $join_on_status = TRUE;
        $retrieve_mixed = FALSE;
        $default_status = 'all';
        $default_period = 'valid';
        $default_type   = 'stack';//'all', if we allow this option we should just omit the mess_type
        $default_aggregate = FALSE;
        
        if ($user_code = SharedStatic::altSubValue($selection_params, 'user_code', '')){
            //One is not allowed to ask for other peoples messages
            $user_id = $this->convertCodeToId($user_code);
            if (($user_id != $current_user_id)){
                return $this->returnProblem (406, "At the moment you cannot ask for other peoples's messages.");
            }
        } else {
            $user_id = $current_user_id;
        }
        if ($user_id === 0){
            //Anonymous access, now we want to return messages that are for all
            //users of this stack independent of read status
            $join_on_status = FALSE;
            //return array();
        }
        $type = SharedStatic::altSubValue(
            $selection_params, 'mess_type', $default_type);
        $aggregate = SharedStatic::altSubValue(
            $selection_params, 'aggregate', $default_aggregate);
        $status = SharedStatic::altSubValue($selection_params, 'status',
            $default_status);
        $period = SharedStatic::altSubValue($selection_params, 'period',
            $default_period);
        
        unset($selection_params['period'],$selection_params['status'], $selection_params['aggregate']);
        
        $added_predicates = 0;
        $pred_set = new Predicate\PredicateSet();

        if ($type == 'user') {
            $selection_params['user_id'] = $current_user_id;
            $selection_params['mess_type'] = 'user';
        } elseif ($type == 'stack'){
            $selection_params['mess_type'] = 'stack';
        } else {//type == all
            $retrieve_mixed = TRUE;
            unset($selection_params['mess_type']);
            if (!$join_on_status){
                //status == all, type=all retrieve messages for current user
                if (isset($selection_params['entity_code'])){
                    $stack_pred = new Predicate\Literal("entity_code = '${selection_params['entity_code']}'");
                } elseif (isset($selection_params['entity_id'])){
                    $stack_pred = new Predicate\Literal("entity_id = '${selection_params['entity_id']}'");
                } else {
                    $stack_pred = new Predicate\Literal("mess_type = 'stack'");
                }
                $user_pred = new Predicate\Literal("mess_type = 'user' AND user_id = $user_id");
                $all_pred_set = new Predicate\PredicateSet(array($user_pred, $stack_pred), Predicate\PredicateSet::COMBINED_BY_OR);
                $pred_set->addPredicate($all_pred_set);
                $added_predicates++;
            }
        }

        if ($join_on_status && ($status !== 'all')){
            $pred = ($status == 'new') ? new Predicate\IsNull('message_read.mess_id') :
               new Predicate\IsNotNull('message_read.mess_id');
            $pred_set->addPredicate($pred);
                $added_predicates++;
        }
        if ($selection_params){
            foreach ($selection_params as $fld => $val){
                if ($val){
                    if (($fld == 'content') || ($fld == 'subject')){
                        $pred = new Predicate\Like("$fld", "%$val%");
                        $pred_set->addPredicate($pred, Predicate\PredicateSet::COMBINED_BY_AND);
                        $added_predicates++;
                    } elseif (
                        (($fld !== 'user_code') || ($type == 'user')) &&
                        (($fld !== 'entity_code') || ($type == 'stack'))
                       ){
                        $pred = new Predicate\Operator("$fld", '=', $val);
                        $pred_set->addPredicate($pred, Predicate\PredicateSet::COMBINED_BY_AND);
                        $added_predicates++;
                    }
                }
            }
            
        }
        $time_cond = ($period == 'valid') ? $this->timeCondition() : null;
        
        if ($added_predicates){
            $conditions = array($pred_set);
        } else {
            $conditions = array();
        }
        if ($time_cond){
            $conditions[] = $time_cond;
        }
        if ($join_on_status){
            if (!$aggregate){
                $extra_on_condition = " AND message_read.user_id = $user_id";
                $fields_join = ($status == 'all')? 
                    array('read'=>new Expression("(0 < Count(DISTINCT(message_read.user_id)))")):
                    array('mess_id');


            } else {
                $extra_on_condition = "";
                //TODO add more interesting fields for the owner of the message(s)
                $fields_join = ($status == 'all')? 
                    array('nr_read'=>new Expression("COUNT(DISTINCT(message_read.user_id))")): 
                    array('message_read.mess_id');
                $conditions[] = new Predicate\Literal('message.owner_id = '.$user_id);
            }
            $joins = array(
                array('message_read', new Expression('message.mess_id = message_read.mess_id '.$extra_on_condition), $fields_join)
            );
        }
        try {
            $where = new Predicate\PredicateSet($conditions);
            if ($join_on_status){
                $result = $this->getItemsJoin($where, TRUE, null, 'message.start', $joins,
                     'message.mess_code', FALSE, FALSE);
            } else {
                $field_set = $this->getFields();
                $field_set['read'] = new Expression('FALSE');
                $result = $this->getItems($where, TRUE, $field_set, 'message.start');
            }
        } catch (\Exception $e) {
            return $this->returnProblem(500, $e, null, 'List of messages cannot be returned');
        }
        return $result;
    }
    
    public function fetchOne($message_id, $user_id=0) {
        try {
            //message_id is most likely a message code, so we convert it
            $message_id = $this->convertCodeToId($message_id);
            $where = $this->ownerCondition($user_id);
            //Note that the result can be False (no items) if there are either no
            //items or the item is not public and the user forgot to login
            //The result will be the same: a 404 entity not found apiproblem.
            $result = $this->getItem($message_id, $where, FALSE, TRUE);
        } catch (\Exception $e) {
            return $this->returnProblem(500, $e, null, 'Message cannot be returned');
        }
        return array('result' => $result);
    }

    /* TODO: the distinction between arrays and objects seems irrelevant: we only call
     * this function from the corresponding resource and that class calls this function
     * with a data object.
     * @param user_id: the current user
     * @param data: a list of parameters for creation: [content, mess_type, entity_id,
     *      user_id]
     */
    public function create($user_id, $data, $data_type = self::DATA_TYPE_OBJECT) {
       $id_name = $this->id_name;
       if (!isset($data->content)) {
            return $this->returnProblem(406, 'Creation of new message without '.
                'text field is not meaningfull');
        }
        unset($data->$id_name);
        $type = $data->mess_type;
        if (
            (($type == 'user') && (!isset($data->user_code))) ||
            (($type == 'stack') && (!isset($data->entity_code)))
            ){
            return $this->returnProblem(406, "Parameters are missing for message type ".
                "$type: (entity_code or user_code resp.). ");
        }
        if (isset($data->entity_code) && ($type == 'stack')){
            if (!($c = $this->convertIdToCode($data->entity_code))){
                return $this->returnProblem(406, "This entity code could not be transformed in a valid id. (".
                    $data->entity_code.")");
            }
            $data->entity_id = is_numeric($data->entity_code) ? $data->entity_code : $this->convertCodeToId($data->entity_code);
            $data->entity_code = $c;
        }
        
        if (isset($data->user_code) && ($type == 'user')){
            if (!($c = $this->convertIdToCode($data->user_code))){
                return $this->returnProblem(406, "This user code could not be transformed in a valid id. (".
                    $data->user_code.")");
            }
            $data->user_id = is_numeric($data->user_code) ? $data->user_code : $this->convertCodeToId($data->user_code);
            $data->user_code = $c;
        }
        if (!isset($data->start) || !$data->start){
            $data->start = date('Y-m-d H:i:s');
        } else {
//            $data->start = $this->convertDateToTimestamp($data->start);
        }

        $message = $this->getModel($data, $data_type);
        $message->owner_id = $user_id;
        $message->status = ($type == 'user') ? 'new' : 'irrelevant';
        
        $result = FALSE;
        $sss_ok = FALSE;
        $msg = '';
        try {
            $result_id = $this->saveItem($message);
            if ($result_id){
                $code = SharedStatic::getShortCode($result_id);
                $result = (bool) $this->updateItem($result_id, '', array('mess_code' => $code), TRUE);
                
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'We could not save the message');
        }
        try {
            if ($result){
                if (! $result = $this->getItem($result_id, 0, false, true)) {
                    throw new \Exception('The message seems to be saved, but could not '.
                        'be retrieved afterwards.');
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex);
        }
               
        return array('result' => $result);
    }

    /*
     * Will be the result from a PUT /message/[message_id] call. 
     */

    public function update($user_id, $id, $data) {
        if (isset($data->is_patch) && $data->is_patch){
            return $this->patch($user_id, $id, $data);
        }
        
        //Check here the required params content and mess_type which could not be checked 
        //globally by apigility since we do not want patches to follow the same
        //restrictions
        if (!SharedStatic::checkSubset(array('content', 'mess_type'), $data)){
            return $this->returnProblem(422, 'Some parameters are missing');
        }
        //id is most likely a message code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($message = $this->getItem($id)) {
            if (isset($data->status)){
                return $this->setMessageStatus($message, $id, $data->status);
            }
            
            if (!$this->account->checkOwner($message, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this message.');
            }
        } else {
            return $this->returnProblem(404, 'The message did not exist');
        }
        
        //Keep non-default fixed values that we do not want to send via the api
        $data->owner_id = $message->owner_id;
        $data->mess_code = $message->mess_code;
        //$data->status = $message->status;
        $data->entity_code = $message->entity_code;
        $data->user_code = $message->user_code;
        $data->entity_id = $message->entity_id;
        $data->user_id = $message->user_id;
        
        if (isset($data->entity_code)){
            $data->entity_id = $this->convertCodeToId($data->entity_code);
        }
        if (isset($data->user_code)){
            $data->user_id = $this->convertCodeToId($data->user_code);
        }
        
        if (self::StrictUpdate) {
            //This will update the whole object. So all keys not set, will be replaced
            //by their default value
            $message->exchangeObject($data);
        } else {
            $message->setObjectValues($data);
        }
        $message->mess_id = $id;

        try {
            $result = (bool) $this->saveItem($message, TRUE);
        } catch (\Exception $ex) {
            return $this->returnProblem($ex->getCode(), 
                (_DEBUG ? $ex: 'The message cannot be updated')); 
        }
        return array('result' => (bool) $result);
    }

    /*
     * Will be the result from a PATCH /message/[message_id] call. 
     */
    private function setMessageStatus($message, $id, $status){
        if (!in_array($status, array('new', 'read'))){
            return array('result' =>false, 'message'=> 'Status can only be "new" or "read".');
        }
        if ($message->mess_type == 'user'){
            $result = (bool) $this->updateItem($id, '', array('status' =>$status), TRUE);
        } elseif ($message->mess_type == 'stack') {
            $data = array(
                'mess_id' => $id,
                'user_id' => $this->account->getCurrentUserId(),
                'timestamp' => time(),
            );
            //The status must be new or read
            if ($status == 'read'){
                $tbl_gateway = $this->getOtherTable('message_read');
                $tbl_gateway->insert($data);
                $x = $tbl_gateway->lastInsertValue;
                $result = (bool) $x;
            } elseif ($status == 'new'){
                
                unset ($data['timestamp']);
                $nr_deleted = $this->getOtherTable('message_read')->delete($data);
                $result = TRUE;
            } else {
                $result = FALSE;//non existing status, should never arrive here
            }
        }
        return array('result' => $result);
    }
    
    public function patch($user_id, $id, $data) {
        //id is most likely a message code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($message = $this->getItem($id)) {
            if (isset($data->status)){
                return $this->setMessageStatus($message, $id, $data->status);
            }
            if (!$this->account->checkOwner($message, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this message.');
            }
        } else {
            return $this->returnProblem(404, 'The message did not exist');
        }
        if (isset($data->entity_code)){
            $data->entity_id = $this->convertCodeToId($data->entity_code);
        }
        if (isset($data->user_code)){
            $data->user_id = $this->convertCodeToId($data->user_code);
        }
        try {
            $result = (bool) $this->updateItem($id, '', $data, TRUE);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'The message cannot be patched'); 
        }
        return array('result' => (bool) $result);
    }

    /* Since this resource is called from within the Rest Resource.php class,
     * it has to return a boolean only.
     */

    public function delete($user_id, $message_id) {
        $result = $msg = '';
        //id is most likely a message code but if not we convert it to one
        $message_id = $this->convertCodeToId($message_id);
        if ($message = $this->getItem($message_id)) {
            if (!$this->account->checkOwner($message, 'owner_id', $user_id)) {
                return $this->returnProblem(403, 'You are not the owner of this message.');
            }
        } else {
            return $this->returnProblem(404, 'The message did not exist.');
        }
        try {
            $nr = $this->deleteItems($message_id);
            if ($nr){
                $this->getOtherTable('message_read')->delete(array('mess_id'=>$message_id ));
            }
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'The deletion of the message returned an error');
        }
    }
    
    public function deleteList($user_id=0, $data=null) {
        return $this->returnProblem(405, 
                ('The deletion of a list of messages is possible but at the moment not allowed'));
        
        
        $result = $msg = '';
        $where = $data ?: array();
        
        $where['owner_id'] = SharedStatic::altValue($user_id,
            $this->account->getCurrentUserId());
        if (!$where['owner_id']){
            //When _DEBUG is on and the authentication check was omitted, we might get 0 here
            return $this->returnProblem(401, 'You should be logged in to delete a collection of message');
        }
        if (! _DEBUG){//so we can delete a whole serie of test data from our own hand
            return $this->returnProblem(405, 
                ('The deletion of a list of messages is possible but at the moment not allowed'));
        }
        try {
            $nr = $this->deleteItems(null, $where);
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, $ex, 'The deletion of the collection returned an error');
        }
    }

    private function timeCondition(){
        $now = date(DATE_W3C);
        $time1_pred = new Predicate\Predicate();
        $time1_pred->lessThanOrEqualTo("start", $now);
        $time_end_pred = new Predicate\Predicate();
        $time_end_pred->greaterThanOrEqualTo('end', $now);
        //Mysql considers a NULL timestamp field as 0 (equal to 1970-01-01: 00
        //but that value never occurs and is reserved for the 'null' value which
        //equals 0 in mysql
        $null_pred = new Predicate\Literal("end = 0");
        $time2_pred = new Predicate\PredicateSet(array($time_end_pred, $null_pred),
            Predicate\PredicateSet::COMBINED_BY_OR
        );
        return new Predicate\PredicateSet(array($time1_pred, $time2_pred),
            Predicate\PredicateSet::COMBINED_BY_AND
        );
    }
    
    private function ownerCondition($user_id){
        $owner_pred = new Predicate\Predicate();
        $owner_pred->equalTo("owner_id", $user_id);
        return $owner_pred;
    }
    
    //This mehtod is called when a stack is deleted,deleteMessagesAttached
    public function deleteMessagesAttached($entity_id, $type='stack') {
        $where = array('entity_id' => $entity_id, 'mess_type' => $type);
        $messages = $this->getItems($where, TRUE, 'mess_id');
        $mess_ids = $this->getColumnFromResult('mess_id', $messages);
        
        if ($mess_ids){
            try {
                $msg_id_lst = implode(',', $mess_ids );
                $m_read = $this->getOtherTable('message_read');
                $m_read->delete(" mess_id IN ($msg_id_lst)");
                $nr = $this->deleteItems(null, $where);
                return ($nr !== FALSE);
            } catch (\Exception $ex) {
                return $this->returnProblem(500, $ex, 
                    'We could not delete the messages of this stack. ');
            }
        } else {
            //There were no messages to delete
            return TRUE;
        }
    }
}
