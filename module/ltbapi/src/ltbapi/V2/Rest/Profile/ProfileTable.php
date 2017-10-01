<?php
namespace ltbapi\V2\Rest\Profile;

use Application\Model\ModelTableCapableSSS;
use Zend\Db\Sql\Predicate;

class ProfileTable extends ModelTableCapableSSS {    
   
    public function fetchOne($passed_user_id, $user_id=0) {
        $return_details = TRUE;
       
        $selection_params = $_GET;
        if ($selection_params){
            //some params for what to return
            $return_details = isset($selection_params['show_details']) && $selection_params['show_details'];
        }

        try {
            //profileId is most likely a profile code but if not we convert it to one
            $requested_user_id = $this->convertCodeToId($passed_user_id);
            if ($this->account->isAdmin()){
                $where = new Predicate\Predicate();
                $where->equalTo($this->table.".user_id", $requested_user_id);
            } elseif ($user_id != $requested_user_id){
                return $this->returnProblem(406, 'You can only retrieve your own profile');
            } else {
                $where = $this->ownerCondition($user_id);
            }
            
            //Note that the result can be False (no items) if there are either no
            //items or the item is not public and the user forgot to login
            //The result will be the same: a 404 Entity not found apiproblem.
            //If the result of the getItemsJoin is an error string, this should
            //be shown as the debug is on in that case. Otherwise it is false 
            //and the mentioned 404 will be returned.
            
                /* This is how it used to be: only one join needed */
            $result = $this->getItemPair($profile_id, 'user', array('owner_id'=>'user_id'),
                array('user' => array('name', 'user_code'), $this->table => ''),
                TRUE, $where, NULL, TRUE);
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'Stack cannot be returned');
        }
 
        return $result;
    }
    
    public function fetchSelection($user_id=0, $selection_params = array()) {
        $owner_cond = $this->ownerCondition($user_id);
        
        if ($selection_params){
            $pred_set = new Predicate\PredicateSet();
            //TODO make the descr and name in one OR statement if they both exist
            foreach ($selection_params as $fld => $val){
               if (($fld == 'name') || ($fld == 'surname')){
                   $pred_set->addPredicate(new Predicate\Like("profile.$fld", "%$val%"),
                        Predicate\PredicateSet::COMBINED_BY_AND);
               } else {
                    $pred_set->addPredicate(new Predicate\Operator("profile.$fld", '=', $val),
                        Predicate\PredicateSet::COMBINED_BY_AND);
               }
            }
            $where = new Predicate\PredicateSet(array($pred_set, $owner_cond));
        } else {
            $where = $owner_cond;
        }
        try {
            //this can be nicer: we know the select fields
            $field_set = $this->getCollectionFields('*');
            $result = $this->getItems($where, false, $field_set);
        } catch (\Exception $e) {
            return $this->returnProblem(500, 'List of profiles cannot be returned');
        }
        
        return $result;
    }

    /* TODO: the distinction between arrays and objects seems irrelevant: we only call
     * this function from the corresponding resource and that class calls this function
     * with a data object.
     */
    public function create($user_id, $data, $data_type = self::DATA_TYPE_OBJECT) {
       $id_name = $this->id_name;
       $err_msg = 'Creation of new profile without '.
          'user fields (user_id/user_code) is not meaningfull';
       $user = null;
       if ($data_type == self::DATA_TYPE_OBJECT) {
            if ($this->account->isAdmin()) {
                if ((isset($data->user_id) || isset($data->user_code))) {
                    if (isset($data->user_id)) {
                        if (!is_numeric($data->user_id)){
                            return $this->returnProblem(406, "The parameter user id should be a number");
                        }
                        if ($user = $this->account->getUser($user_id)){
                            $data->user_code =  $user['user_code'];
                        } else {
                            $data->user_code = $this->convertIdToCode($data->user_id);
                        }
                    } else {
                        $data->user_id = $this->convertCodeToId($data->user_code);
                    }
                    $user_id = $data->user_id;
                    
                    
                } elseif ($user_id) {
                    $data->user_id = $user_id;
                    if ($user = $this->account->getUser($user_id)){
                        $data->user_code =  $user['user_code'];
                    }
                } else {
                    return $this->returnProblem(406, $err_msg);
                }
                
                //If the admin makes a new profile and the user did not exist yet in the
                //user table where users are stored, we can create one that will be updated 
                //as soon as the user logs in the first time.
                if (!($user || ($user = $this->account->getUser($user_id)))) {
                    $user_data = array(
                       'user_code' => $data->user_id ? $data->user_code : '',
                       'oid_id' => $data->oid_id,
                       'name' => $data->name. ' '. $data->surname,
                       'email' => $data->email,
                       'expire' => time(),
                    );
                    $user_id = $this->account->storeUserInfo($user_data, $data->user_id);
                }
            } else {
               $data->user_id = $this->account->getCurrentUserId();
               $data->user_code = $this->account->getCurrentUserCode();
               $user_id = $data->user_id;
            }
            //just in case an id field is sent along, we ignore it
            unset($data->$id_name);
       } else {
            return $this->returnProblem(406, 'Creation of new profile with wrong data somehow.');
        }
        
        $profile = $this->getModel($data, $data_type);
        $profile->user_id = $user_id;
        $result = FALSE;
        $sss_ok = FALSE;
        $msg = '';
        try {
            $result_id = $this->saveItem($profile);
            if ($result_id){
                $code = \Application\Shared\SharedStatic::getShortCode($result_id);
                $result = (bool) $this->updateItem($result_id, '', array('profile_code' => $code));
            }
        } catch (\Exception $ex) {
            $status = $ex->getCode();
            if (501 == $status){
               return $this->returnProblem(406, $ex->getMessage(). ' You already saved this as a profile.'); 
            }
            return $this->returnProblem(500, (_DEBUG ? $ex: 'We could not save the profile. '));
        }
        try {
            if ($result){
                if (! $result = $this->getItem($result_id, 0, false, true)) {
                    throw new \Exception('The profile seems to be saved, but could not '.
                        'be retrieved afterwards.');
                }
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, (_DEBUG ? $ex: $ex->getMessage()));
        }
        return array('result' => $result);
    }

    
    public function update($user_id, $id, $data) {
        if (isset($data->is_patch) && $data->is_patch){
            return $this->patch($user_id, $id, $data);
        }
        //id is most likely a profile code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($profile = $this->getItem($id)) {
            if (!$this->account->checkOwner($profile, 'user_id', $user_id) &&
                !$this->account->isAdmin()) {
                return $this->returnProblem(403, 'You are not the owner of this profile nor admin.');
            }
        } else {
            return $this->returnProblem(404, 'The profile did not exist');
        }
        $sss_affected = FALSE;
            //(isset($data->name) && ($profile->name != $data->name) ) ||
            //(isset($data->description) && ($profile->description != $data->description) );
        
        //Keep non-default fixed values that we do not want to send via the api
        $data->user_id = $profile->user_id;
        $data->user_code = $profile->user_code;
        $data->profile_code = $profile->profile_code;
        
        if (self::StrictUpdate) {
            //This will update the whole object. So all keys not set, will be replaced
            //by their default value
            $profile->exchangeObject($data);
        } else {
            $profile->setObjectValues($data);
        }
        //To make sure that saveItem does an update and not an insert
        $id_name = $this->id_name;
        $profile->$id_name = $id;
        try {
            $result = (bool) $this->saveItem($profile, TRUE);
            if ($result && _CONNECT_SSS && $sss_affected){
               /*
                *  $data = array(
                //    'app' => 'Tilestore',
                'label' => $profile->name,
                'description' => $profile->description,
                'profile' => $profile->profile_code);
                $sss_result = $this->callSocSemServer('changeStack', $data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    return $sss_result;
                }
                */
            }
        } catch (\Exception $ex) {
            return $this->returnProblem($ex->getCode(), 
                (_DEBUG ? $ex: 'The profile cannot be updated')); 
        }
        return array('result' => (bool) $result);
    }

    /*
     * Will be the result from a PATCH /profile/[profile_id] call. 
     */

    public function patch($user_id, $id, $data) {
        //id is most likely a profile code but if not we convert it to one
        $id = $this->convertCodeToId($id);
        if ($profile = $this->getItem($id)) {
            if (!$this->account->checkOwner($profile, 'user_id', $user_id) &&
                !$this->account->isAdmin()) {
                return $this->returnProblem(403, 'You are not the owner of this profile nor admin.');
            }
        } else {
            return $this->returnProblem(404, 'The profile did not exist');
        }
        $sss_affected = FALSE;
        
        try {
            $result = (bool) $this->updateItem($id, '', $data, TRUE);
            if ($result && $sss_affected && _CONNECT_SSS){
                /*$sss_data = array(
                    'label' => isset($data->name) ? $data->name : '',
                    'description' => isset($data->description) ? $data->description : '',
                    'profile' => $profile->profile_code
                );
                $sss_result = $this->callSocSemServer('changeStack', $data,
                    $this->account->getOpenIdToken());
                if ($this->isProblem($sss_result)){
                    return $sss_result;
                }*/
            }
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex: 'The profile cannot be patched')); 
        }
        return array('result' => $result);
    }

    /* Since this resource is called from within the Rest Resource.php class,
     * it has to return a boolean only.
     */

    public function delete($user_id, $profile_id='') {
        $result = $msg = '';
        //id is most likely a profile code but if not we convert it to one
        $profile_id = $this->convertCodeToId($profile_id);
        $where = array();
        if ($profile_id && $this->account->isAdmin()){
            $profile_id = $this->convertCodeToId($profile_id);
            $where['profid'] = $profile_id;
        } else {
            $where['user_id'] = $this->account->getCurrentUserId();
        }
        $profiles = $this->getItems($where);
        
        if ($profiles->count() == 1) {
            //$profile = $profiles->current();
            //$id_name = $this->id_name;
            //$profile_id = $profile->$id_name;
        } else {
            return $this->returnProblem(404, 'There was no unique profile with this id.');
        }
        try {
            $nr = $this->deleteItems(0, $where);
            if ($nr != 0){
               if (FALSE && _CONNECT_SSS){
                    $data = array('profile' => $profile->profile_code);
                    $sss_result = $this->callSocSemServer('deleteProfile', $data);
                    if ($this->isProblem($sss_result)){
                        return $sss_result;
                    } 
                }
            }
            return ($nr > 0);
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the profile returned an error'));
        }
    }
    
    public function deleteList($user_id=0, $data=null) {
        $result = $msg = '';
        $where = $data ?: array();
        
        $where['user_id'] = \Application\Shared\SharedStatic::altValue($user_id,
            $this->account->getCurrentUserId());
        if (!$where['user_id']){
            //When _DEBUG is on and the authentication check was omitted, we might get 0 here
            return $this->returnProblem(401, 'You should be logged in to delete a collection of profile');
        }
        if (! _DEBUG){//so we can delete a whole serie of test data from our own hand
            return $this->returnProblem(405, 
                ('The deletion of a list of profiles is possible but at the moment not allowed'));
        }
        try {
            //TODO for the moment we assume profiles concern profiles
//            $entities = $this->getOtherTable('ltbapi\V2\Rest\Stack\StackTable')->getItems($data);
//            $entity_list = $this->getColumnFromResult('profile_id', $entities, FALSE);
            if (TRUE ){//|| $entity_list){
                
                $nr = $this->deleteItems(null, $where);
                return ($nr > 0);
            }
            return TRUE;
        } catch (\Exception $ex) {
            return $this->returnProblem(500, 
                (_DEBUG ? $ex : 'The deletion of the collection returned an error'));
        }
    }

    private function ownerCondition($user_id){
        $owner_pred = new Predicate\Predicate();
        $owner_pred->equalTo("profile.user_id", $user_id);
        return $owner_pred;
    }    
}