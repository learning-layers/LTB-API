<?php
namespace ltbapi\V2\Rpc\Debug;

use Application\Shared\SharedStatic;
use Application\Controller\MyAbstractActionController;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql;

class DebugController extends MyAbstractActionController
{   
    public function __construct($account){
        $this->account = $account;
    }
    
    public function debugAction()
    {
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=>false, 
                'message'=> "There is no action specified",
                'status' => 406)
        ); 
    }
    
    private function verifyAdminUser(){
        return $this->account->isAdmin();
    }
    
    public function refactorAction(){
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           if (! $this->verifyAdminUser()){
               return $this->returnControllerProblem(403, 'Only admins can do this');
           }
        }
        $file_location = _API_HOME.'/files';
        $dir_handle = opendir($file_location);
        $continue = true;
        $m = '';
        while ((false !== ($f = readdir($dir_handle))) && $continue){
            if (is_dir($f)){
                $m .= "Looked at $f. It is a directory\n";
                continue;
            }
            $pieces = explode('_', $f);
            $code = array_shift($pieces);
            $new_name = implode('_', $pieces);
            $m .= "Looked at $f $code , $new_name\n ";
            if (!file_exists("$file_location/$code")){
                mkdir("$file_location/$code", 0700);
            }
            $continue = rename("$file_location/$f", "$file_location/$code/$new_name");
        }
        closedir($dir_handle);
        return new \Zend\View\Model\JsonModel(array(
            'result'=>$continue, 
            'message'=> "All files refactored: $m",
            'status' => 200
        ));
    }
    
    public function initialiseAction(){
        $result = $user = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        }
        
        //Check here that this user is allowed to do this
        if ($user && $this->verifyAdminUser()){
            if ($end = $this->params('end')){
                $end_ts = date_create_from_format('Y-m-d', $end)->getTimestamp();
            } else {
                $end_ts = time() + (3600 * 24 * 30);//30 days ahead
            }
            try {
                $data = array(
                    'start'=> time(),
                    'end'  => $end_ts
                );
                $tbl_gateway = $this->getTableObject('debug_verify');
                $tbl_gateway->insert($data);
                $nr = $tbl_gateway->lastInsertValue;
                $verify_code = \Application\Shared\SharedStatic::getShortCode($nr);
                $tbl_gateway->update(array('verify_code' => $verify_code), array('verify_id' => $nr));
                $result = $verify_code;
                $message = "You have initialised a debug session for $verify_code.";
                $state = 200;
            } catch (\Exception $e){
                $state = $e->getCode();
                $message = 'Storing the debug message caused an exception:'.(_DEBUG ? $e->getMessage() : '');
            }
        } else {
            $result = FALSE;
            $message = "Only logged in and permitted users can start a debug session.";
            $state = 401;
        } 
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=> $result,
                'verify_code' => (isset($verify_code) ? $verify_code : ''),
                'message'=> $message,
                'status' => $state)
        ); 
    }
    
    public function purgeAction(){
        $result = $user = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        }
        //Check here that this user is allowed to do this
        if ($user && $this->verifyAdminUser()){
            $get_params = $this->params()->fromQuery();
            $verify_code = SharedStatic::altSubValue($get_params, 'verify_code', null);
            $debug_code = SharedStatic::altSubValue($get_params, 'debug_code', null);
            $user_id = SharedStatic::altSubValue($get_params, 'user_id', null);
            
            try {
                if (!$user_id && ! $verify_code && ! $debug_code){
                    throw new \Exception('At least one of the fields user_id, verify_code or debug_code need to be passed');
                }
               
                $adapter = $this->getOtherService('Zend\Db\Adapter\Adapter');
   
                // Build the main query: a delete sql object that can be executed
                $sql = new Sql\Sql($adapter);
                $delete = $sql->delete('debug_record');

                // Create subquery
                $subSelect = $sql->select();
                $subSelect->from('debug_session')->columns(array('session_id'));
                $predSet = new Predicate\PredicateSet();
                if ($verify_code){
                    $predSet->addPredicate(new Predicate\Literal("debug_session.verify_code = '$verify_code'"), Predicate\PredicateSet::COMBINED_BY_AND);
                }
                if ($debug_code){
                    $predSet->addPredicate(new Predicate\Literal("debug_session.debug_code = '$debug_code'"), Predicate\PredicateSet::COMBINED_BY_AND);
                }
                if ($user_id){
                    $predSet->addPredicate(new Predicate\Literal("debug_session.user_id = '$user_id'"), Predicate\PredicateSet::COMBINED_BY_AND);
                }
                $subSelect->where($predSet);

                $delete->where->addPredicate(
                  new Predicate\In('debug_record.session_id',$subSelect)
                );

                // Run the delete query
                $statement = $sql->prepareStatementForSqlObject($delete);
                $m = $statement->getSql();
                $data = $statement->execute();
                
                //delete the session itself
                $tbl_gateway = $this->getTableObject('debug_session');
                $tbl_gateway->delete($predSet);
                
                $result = TRUE;
                $message = "You have deleted all debug sessions that comply with your search terms.";
                $state = 200;
            } catch (\Exception $e){
                $state = $e->getCode();
                $message = 'Purging debug messages caused an exception:'.(_DEBUG ? $e->getMessage(). " was $m" : '');
            }
        } else {
            $result = FALSE;
            $message = "Only logged in and permitted users can start a debug session.";
            $state = 401;
        } 
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=> $result,
                'verify_code' => $verify_code,
                'message'=> $message,
                'status' => $state)
        ); 
    }
    
    public function retrieveAction(){
        $user = FALSE;
        $result = TRUE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        }
        //Check here that this user is allowed to do this
        if ($user && $this->verifyAdminUser()){
            $get_params = $this->params()->fromQuery();
            $start = SharedStatic::altSubValue($get_params, 'start', null);
            $end = SharedStatic::altSubValue($get_params, 'end', null);
            $verify_code = SharedStatic::altSubValue($get_params, 'verify_code', null);
            $debug_code = SharedStatic::altSubValue($get_params, 'debug_code', null);
            $user_id = SharedStatic::altSubValue($get_params, 'user_id', null);
            if (! $verify_code && ! $debug_code && ! $user_id){
                $result = FALSE;
                $message = "Debug code or verify code or user id is required.";
                $state = 400;
            }
 
            if ($result){
                $preds = array();
                if ($verify_code){
                    $preds[] = new Predicate\Literal("debug_record.verify_code = '$verify_code'");
                }
                if ($debug_code) {
                    list($session_id, $verify_code) = $this->getSession($debug_code, FALSE);
                    if ($session_id){
                        $preds[] = new Predicate\Literal("debug_record.session_id  = '$session_id'");
                    } else {
                        $result = FALSE;
                        $message = 'Invalid debug code was sent.';
                        $state = 404;
                        //throw new \Exception('Invalid debug code was sent.');
                    }
                }
                if ($result){
                    if ($result && $user_id){
                        $preds[] = new Predicate\Literal("debug_session.user_id  = '$user_id'");
                    }
                    $where = new Predicate\PredicateSet($preds);

                    if ($end){
                        $end_ts = date_create_from_format('Y-m-d', $end)->getTimestamp();
                        $end_pred = new Predicate\Literal("debug_record.time <= $end_ts");
                        $where->addPredicate($end_pred);
                    }

                    if ($start){
                        $start_ts = date_create_from_format('Y-m-d', $start)->getTimestamp();
                        $start_pred = new Predicate\Literal("debug_record.time >= $start_ts");
                        $where->addPredicate($start_pred);
                    }

                    try {
                        //Execute query
                        $table_gateway = $this->getTableObject('debug_record');
                        $sql = $table_gateway->getSql();
                        $selectObj = $sql->select()
                            ->join('debug_session', 'debug_session.session_id = debug_record.session_id',
                                array('debug_code', ' version', 'app', 'device'))
                            ->join('user', 'debug_session.user_id = user.user_id', array('name', 'role'))
                            ->where($where);
                        $rowset = $table_gateway->selectWith($selectObj);
                        $messages = $rowset->toArray();
                        $result = array('messages'=> $messages, 'count'=> count($messages));
                        $message = "";
                        $state = 200;
                    } catch (\Exception $e){
                        $state = $e->getCode();
                        $message = 'Getting the debug messages caused an exception:'.(_DEBUG ? $e->getMessage() : '');
                    }
                } else {
                    //Appearantly not a valid debug code was sent
                }
            } else {
                $result = FALSE;
                $state = 500;
                $message = 'It should not arrive here';
            }
            
        } else {
            $result = FALSE;
            $message = "Only logged in and permitted users can start a debug session.";
            $state = 401;
        } 
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=> $result,
                'message'=> $message,
                'status' => $state
            )
        ); 
    }
    
    private function startSession($verify_code, $app, $version, $user_id, $device=''){
        $tbl_gateway = $this->getTableObject('debug_session');
        $debug_code = \Application\Shared\SharedStatic::makeShortCode();
        $data = array(
            'user_id' => $user_id,
            'start' => time(),
            'debug_code' => $debug_code,
            'version' => $version,
            'app' => $app,
            'verify_code' => $verify_code
        );
        if ($device){
            $data['device'] = $device;
        }
        $tbl_gateway->insert($data);
        $nr = $tbl_gateway->lastInsertValue;
        return array($nr, $debug_code);
    }
    
    public function startAction(){
        $message = '';
        $result = $user = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        } else {
            $message = "Only logged in users can start a debug session.";
            $state = 401;
        } 
        if ($user){
            //Use current user as the user of this session
            $user_id = $user['user_id'];
            //Get other parameters
            $get_params = $this->params()->fromQuery();
            //$debug_code = SharedStatic::altSubValue($get_params, 'debug_code');
            $verify_code = SharedStatic::altSubValue($get_params, 'verify_code');
            $app = SharedStatic::altSubValue($get_params, 'app', NULL) ? 1 : 0;
            $version = SharedStatic::altSubValue($get_params, 'version');
            $device = SharedStatic::altSubValue($get_params, 'device');
            
            if (!$verify_code || ! isset($app) || ! $version){
               $state = 400;
               $message = "We have not enough information to start the debug session."; 
            } elseif (! $this->isValidDebugStartup($verify_code)) {
                $state = 406;
                $message = "The verification code is not a recognised debug session.";
            } else {
                try {
                    list($session_id, $debug_code) = $this->startSession($verify_code, $app, $version, $user_id, $device);
                    if ($session_id && $debug_code) {
                        $data = array(
                            'session_id' => $session_id,
                            'message'=> 'Debugging session started',
                            'time' => time(),
                            'verify_code' => $verify_code
                        );
                        $tbl_gateway = $this->getTableObject('debug_record');
                        $tbl_gateway->insert($data);
                        $result = array('verify_code'=>$verify_code, 'debug_code'=>$debug_code);
                        $message .= "You have started your debugging session.";
                        $state = 200;
                    } else {
                        $state = 500;
                        $message .= 'Something went wrong storing your session';
                        $debug_code = '';
                    }
                } catch (\Exception $e){
                    $state = $e->getCode();
                    $message .= 'Starting the debug session caused an exception:'.(_DEBUG ? $e->getMessage() : '');
                }
            }
        }
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=> $result,
                'message'=> $message,
                'status' => $state)
        ); 
    }    
    
    public function stopAction(){
        $result = $user = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        } else {
            $message = "Only logged in users can start or stop debugging sessions.";
            $state = 401;
        } 
        if ($user){
            //Use current user as the user of this session
            $user_id = $user['user_id'];
            //Get other parameters
            $get_params = $this->params()->fromQuery();
            $debug_code = SharedStatic::altSubValue($get_params, 'debug_code');
            $verify_code = SharedStatic::altSubValue($get_params, 'verify_code');
            
            if (!$debug_code && !$verify_code){
               $state = 400;
               $message = "We have not enough information to stop the debug session."; 
            } else {
                try {             
                    if ($verify_code){
                        if (! $this->isValidDebugStartup($verify_code)) {
                            $state = 406;
                            $message = "The verification code is not recognised as a debug session.";
                            $sessions = false;
                        } else {
                            $sessions = $this->getSessions($verify_code);
                            $is_rowset = TRUE;
                            if (!$sessions->count()){
                                $sessions = null;
                            }
                        }

                    } else {
                        list($session_id, $verify_code_from_debug) = $this->getSession($debug_code);
                        $sessions = $session_id ? array($session_id) : null;
                        $is_rowset = FALSE;
                    }
                    //$session_id = 
                    if ($sessions === false){
                        //vars set above
                    } elseif (! $sessions){
                        $result = TRUE;
                        $message = 'No active debug session could be found for this id '.($verify_code ?: $debug_code);
                        $state = 404;
                    } else {
                        if (!$verify_code){
                            $verify_code = $verify_code_from_debug;
                        }
                        foreach ($sessions as $session){
                            $session_id = ( $is_rowset ? $session->session_id : $session);
                            $data = array(
                                'session_id' => $session_id,
                                'message'=> 'Debugging session stopped',
                                'time' => time(),
                                'verify_code' => $verify_code
                            );
                            $tbl_gateway = $this->getTableObject('debug_record');
                            $tbl_gateway->insert($data);
                            $tbl_gateway2 = $this->getTableObject('debug_session');
                            $tbl_gateway2->update(array('active' => 0), array('session_id' => $session_id));
                        }
                        $result = TRUE;
                        $message = "You have stopped the recording.";
                        $state = 200;
                    }
                } catch (\Exception $e){
                    $state = $e->getCode();
                    $message = 'Storing the debug message caused an exception:'.(_DEBUG ? $e->getMessage() : '');
                }
            }
        }
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=> $result, 
                'message'=> $message,
                'status' => $state)
        ); 
    }
    
    public function storeAction($data = ''){
        $result = $user = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
           $message = '';
        } else {
            $message = "Only logged in users can send debug messages.";
            $state = 401;
        } 
        if ($user){
            //get parameters
            //NOTE that it is handy to have a couple of value fields for the case where you quickly want to 
            //store a current value without putting it in a struct and inventing a name for it
            $user_id = $user['user_id'];
            $post_params = $this->getPostParams();
            $debug_code = SharedStatic::altSubValue($post_params, 'debug_code');
            $message = SharedStatic::altSubValue($post_params, 'message');
            $val1 = SharedStatic::altSubValue($post_params, 'val1',null);
            $val2 = SharedStatic::altSubValue($post_params, 'val2',null);
            $val3 = SharedStatic::altSubValue($post_params, 'val3',null);
            if (!$debug_code || !$message){
                $state = 400;
                $message .= "We have not enough information to store a debug message.";
            } else {
                //check whether a session exists
                try {               
                    list($session_id, $verify_code) = $this->getSession($debug_code);
                    if (! $session_id){
                        throw new \Exception('This was not a valid session for debugging'. (_DEBUG ? " ($debug_code)": ""));
                    }
                    $data = array(
                        'session_id' => $session_id,
                        'verify_code' => $verify_code,
                        'message' => $message,
                        'time' => time()
                    );
                    for ($i=1; $i<=3;$i++){
                        $value = "val$i";
                        if (isset($$value)){
                            $data[$value] = $$value;
                        }
                    }

                    $tbl_gateway = $this->getTableObject('debug_record');
                    $tbl_gateway->insert($data);
                    $x = $tbl_gateway->lastInsertValue;
                    $result = TRUE;
                    $message = "You have stored your message.";
                    $state = 200;
                } catch (\Exception $e){
                    $state = $e->getCode();
                    $message = 'Storing the debug message caused an exception'.(_DEBUG ? ': '.$e->getMessage() : '.');
                }
            }
        }
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'  => $result, 
                'message' => $message,
                'status'  => $state
            )
        ); 
    }
    
    private function getSession($debug_code, $active=true){
        $tbl_gateway = $this->getTableObject('debug_session');
        $where = array('debug_code' => $debug_code);
        if ($active){
            $where['active'] = $active;
        }
        $rowset = $tbl_gateway->select($where);
        $sess = $rowset->current();
        return $sess ? array($sess->session_id, $sess->verify_code) : array(false, false);
    }
    
    private function getSessions($verify_id){
        $tbl_gateway = $this->getTableObject('debug_session');
        $rowset = $tbl_gateway->select(array('verify_code' => $verify_id, 'active' => true));
        return $rowset;
    }
    
    private function isValidDebugStartup($verify_code){
        $tbl_gateway = $this->getTableObject('debug_verify');
        //TODO in the future we might want to check on the begin and start of the 
        //verification validness
        $rowset = $tbl_gateway->select(array('verify_code' => $verify_code));
        $sess = $rowset->current();
        return $sess ? true : false; 
    }   

}