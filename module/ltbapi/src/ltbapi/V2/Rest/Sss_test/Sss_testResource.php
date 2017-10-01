<?php
namespace ltbapi\V2\Rest\Sss_test;

use Application\Listener\MyAbstractResourceListener;
use ZF\ContentNegotiation\ViewModel;

class Sss_testResource extends MyAbstractResourceListener
{
    protected $end_point = 'Sss_test';
    protected $defined_methods = array('fetchAll');
       
    protected $defined_methods = array(
        //'create', 'delete', 'deleteList', 'fetch',
         'fetchAll'
        //, 'patch', 'update'
        //,'patchList','replaceList'
    );
    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = array())
    {
        $param_array = $params ? $params->getArrayCopy() : array();
        if (!isset($param_array['test'])){
            return $this->returnResourceProblem(400, "No test id sent: need a param 'test'");
        }
        $test = $param_array['test'];
        if (! $this->isAuthorised()){
            return $this->returnResourceProblem(401, "For all sss tests we need a valid session key to ".
                "connect to a valid oidc token. ".
                (_DEBUG ? $this->getAccountMessages() : ""));
        }
        
        $msg = "For testing $test: ";
        $sss = $this->table_object->getOtherService('Application\Service\SocialSemanticConnector');
        $sss->setOidToken($this->account->getOpenIdToken());
        $verbose = FALSE;
        switch ($test){
            case 'addApp':
               $data = array(
                "label" => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', "The learning toolbox app containing all the stacks") ,
                "descriptionShort"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'descriptionShort', "LearningToolboxApp"),
                "descriptionFunctional"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'descriptionFunctional', "We add stacks containing tiles and they can be played here"),
                "descriptionTechnical"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'descriptionTechnical', "AwSome comment"),
                "descriptionInstall"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'descriptionInstall', "Ask Andy"),
                "downloads"=> array(),
                "downloadIOS"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'downloadIOS', "SSUri"),
                "downloadAndroid"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'downloadAndroid', "SSUri"),
                "fork"=> \Application\Shared\SharedStatic::altSubValue($param_array, 'fork', "SSUri"),
                "screenShots"=> array(),
                "videos" => array()
              );
              list($sss_result, $sss_ok, $sss_msg) =
                  $sss->callSocialSemanticServer('addApp', $data, $verbose);
              $msg .= $sss_msg;
            break;
        case 'deleteApps':
            $data = array(
                "apps" => \Application\Shared\SharedStatic::altSubValue($param_array, 'apps', array('hallo', "aap", " noot    ", "mies ")) ,
            );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('deleteApps', $data, $verbose);
            $msg .= $sss_msg;
            break;
        case 'getApps':
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getApps', null, $verbose);
            $msg .= $sss_msg;
            break;
        case 'addStack':
            $data = array(
                //'app' => \Application\Shared\SharedStatic::altSubValue($param_array,'app',''),
                'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', 'eerste stack'),
                'description' => \Application\Shared\SharedStatic::altSubValue($param_array, 'description', 'hier een beschrijving'),
                'stack' => \Application\Shared\SharedStatic::altSubValue($param_array, 'stack', 'ABBA'),
                'uuid' => \Application\Shared\SharedStatic::altSubValue($param_array, 'uuid', null),
                );
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('addStack', $data, $verbose);
            $msg .= $sss_msg;
            break;
         case 'changeStack':
            $data = array(
                //'app' => \Application\Shared\SharedStatic::altSubValue($param_array,'app','LTB-API/public/sss_test'),
                //'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', 'eerste stack'),
                'description' => \Application\Shared\SharedStatic::altSubValue($param_array, 'description', 'hier een beschrijving'),
                'stack' => \Application\Shared\SharedStatic::altSubValue($param_array, 'stack', 'ABBA'),
                );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('changeStack', $data, $verbose);
            $msg .= $sss_msg;
            break;
        case 'deleteStack':
            $data = array(
                'stack' => \Application\Shared\SharedStatic::altSubValue($param_array,'stack','ABBA')  
                );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('deleteStack', $data, $verbose);
            $msg .= $sss_msg;
            break;
        case 'getStacks':
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getStacks', null, $verbose);
            $msg .= $sss_msg;
        break;
        case 'deleteTag':
            $data = array(
                'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', ''),
                'space' => \Application\Shared\SharedStatic::altSubValue($param_array, 'space', ''),
                'stack' => \Application\Shared\SharedStatic::altSubValue($param_array, 'stack', ''),
                );
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('deleteTag', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'addTag':
            $data = array(
                'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', 'eerste stack'),
                'space' => \Application\Shared\SharedStatic::altSubValue($param_array, 'space', 'sharedSpace'),
                'stack' => \Application\Shared\SharedStatic::altSubValue($param_array, 'stack', 
                    \Application\Shared\SharedStatic::altSubValue($param_array, 'entity', 'ABBA')),
                );
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('addTag', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'changeTag':
            
            $data = array(
                //make the new label the passed label for tag id X
                'entity' => \Application\Shared\SharedStatic::altSubValue($param_array, 'stack', ''),
                'label' => \Application\Shared\SharedStatic::altSubValue($param_array, 'label', ''),
                'newlabel' => \Application\Shared\SharedStatic::altSubValue($param_array, 'newlabel', ''),
                );
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('changeTag', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'getAllTags':
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('getTags', null, $verbose);
            $msg .= $sss_msg;
        break;
        case 'getTags':
            $data = array(
                'forUser'=> \Application\Shared\SharedStatic::altSubValue($param_array, 'forUser', ''),
                'entities'=> \Application\Shared\SharedStatic::altSubValue($param_array, 'entities', ''),
                'labels' => \Application\Shared\SharedStatic::altSubValue($param_array, 'labels', ''),
                'space' => \Application\Shared\SharedStatic::altSubValue($param_array, 'space', 'sharedSpace'),
                'startTime' => \Application\Shared\SharedStatic::altSubValue($param_array, 'startTime', 0),
                );
            list($sss_result, $sss_ok, $sss_msg) = 
                $sss->callSocialSemanticServer('getTags', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'getStacksByTag':
                $data = array(
                    'tag' => \Application\Shared\SharedStatic::altSubValue($param_array, 'tag', 'test')
                );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getStacksByTag', $data, $verbose);
            $msg .= $sss_msg;
        
        break;    
        case 'searchStacks':
            $data = array(
                'search' => \Application\Shared\SharedStatic::altSubValue($param_array, 'search', 'test'),
               // 'label' => @$param_array['label'] ?:'eerste stack',
               // 'description' => @$param_array['description'] ?:'hier een beschrijving'
                'global' => \Application\Shared\SharedStatic::altSubValue($param_array, 'global', 'or'),
                'local' => \Application\Shared\SharedStatic::altSubValue($param_array, 'local', 'or'),
                'entity_type' => \Application\Shared\SharedStatic::altSubValue($param_array, 'entity_type', '')
               );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('searchStacks', $data, $verbose);
            $msg .= $sss_msg;
        
        break;
        case 'getEntityTypes':
            $data = array();
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getEntityTypes', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'getEntityById':
            $entity = \Application\Shared\SharedStatic::altSubValue($param_array, 'entity');
            $data = array(
                'entity' => $entity
            );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getEntityById', $data, $verbose);
            $msg .= $sss_msg;
        break;
        case 'getEntitiesByIds':
            $entities = \Application\Shared\SharedStatic::altSubValue($param_array, 'entities');
            $data = array(
                'entities' => $entities ? explode(",", $entities) : null
            );
            list($sss_result, $sss_ok, $sss_msg) =
                $sss->callSocialSemanticServer('getEntitiesByIds', $param_array, $verbose);
            $msg .= $sss_msg;
        break;
        default: 
            $msg .= " No such test found: $test";
            $sss_result = 'no_sss_result';
            $sss_ok = FALSE;
        }
        
        $sss_return =  array(
            'result' => $sss_result,
            'ok'=> $sss_ok,
            'msg' => $msg);
        //return new Sss_testEntity($sss_return);
         return $this->returnResourceProblem(418, 
            ($sss_ok ? "SUCCES (".print_r($sss_result, 1).") EXTRA_INFO: $msg": 
            "FAILURE ($sss_result) DEBUG_INFO: ".str_replace("\n", "NEWLINE", $msg)));
        
         
         print_r($sss_return);
        die();
        return new \Zend\View\Model\JsonModel($sss_return
        );
        //$this->getPluginManager()->get('hal')->createCollection(array(
         //   'payload' => $sss_return));
       
    }
    
    /* This function creates a tableGateway to the log table of the database and stores the action
     * the user has initiated
     */
    public function userLog($method, $soft, $user_id, $id=0, $params=null, $granted=TRUE){
        //Skip this function. We do not log actions.
    }
}
