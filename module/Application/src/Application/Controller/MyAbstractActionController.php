<?php
namespace Application\Controller;

use Application\Shared\SharedStatic;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Http\Header\HeaderInterface;
class MyAbstractActionController extends AbstractActionController implements ServiceLocatorAwareInterface
{
    
    public function __construct($account=null){
        $this->account = $account;
    }
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator){
        $this->services = $serviceLocator;
    }

    public function getServiceLocator(){
        return $this->services;
    }
    
    //TODO introduce this everywhere
    public function getAuthenticatedUser($soft=FALSE){
        return ($this->account->getAuth($this->getEvent(), $soft)) ?
            $this->account->getCurrentUserInfo() :
            FALSE;
    }
    
    /* This function creates a tableGateway to the log table of the database and stores the action
     * the user has initiated
     */
    public function userLog($method, $soft, $user_id, $id=0, $params=null, $granted=TRUE){
        $gateway = $this->getOtherService('user_log');
        SharedStatic::userLogStore($gateway, $this->end_point, $method, $soft, $user_id, $id, $params, $granted);
    }
    
    public function returnControllerProblem($status_code='', $message=''){
        return new \Zend\View\Model\JsonModel(
                array(
                    'result'=>false, 
                    'message'=> ($message ?: 'Some error occurred.'),
                    'status' => $status_code ?: 500)
            ); 
    }
    
    public function returnControllerResult($result, $message='', $fields='', $status_code=''){
        $return = array(
                    'result' => $result, 
                    'message'=> $message,
                    'status' => $status_code ?: 200);
        if ($fields && is_array($fields)){
            $return = array_merge($return, $fields);
        }
        return new \Zend\View\Model\JsonModel($return); 
    }
    
    protected function getMyBodyParams(){
        $request = $this->getRequest();
        $type = $request->getHeader('Content-Type')->getFieldValue();
        $content_type = $request->getHeader('Content-Type');

        if ($content_type->match('application/json')){
            $content_body = $request->getContent();
            return $content_body ? json_decode($content_body, true): array();
        } else {
            $is_multi_part = $content_type->match('multipart/form-data');
            if ($request->isPost()){
                $post = $request->getPost();
                $return = $post->toArray();
                if ($is_multi_part && $_FILES){
                    //Add to the $return the files
                    $return = array_merge($_FILES, $return);
                }
                return $return;
            } else {
                if ($is_multi_part){
                    return $this->parseMultidataHttpRequest($request, $content_type);
                } elseif ($content_type->match('application/x-www-form-urlencoded')) {
                    parse_str($request->getContent(), $params);
                    return $params;
                } else {
                    throw new Exception('Cannot retrieve body params from a body in unknown format'.
                        ' Expecting urlencoded, data form or json');
                }
            }
        }
    }

    //This function retrieves the get parameters that can be queried after with the
    //method ' get(name, default) as the resulting list is a Parameters list
    protected function getMyQueryParams(){
        $r = $this->getRequest();
        if ($r->isGet()){
            return $r->getQuery()->toArray();
        } else {
            return false;
        }
        //This also works as the data is read from a php input stream
        //But reading like this can only be done once, hence I consider that less clean
        //return json_decode(file_get_contents('php://input'), true);
    }
    
    public function getPostParams(){
        $r = $this->getRequest();
        if ($r->isPost()){
            return json_decode($r->getContent(), true);
        } else {
            return false;
        }
        //This also works as the data is read from a php input stream
        //But reading like this can only be done once, hence I consider that less clean
        //return json_decode(file_get_contents('php://input'), true);
    }
    
    //Just an alias
    protected function getTableObject($table_factory_key){
        return $this->getOtherTable($table_factory_key);
    }
    
    protected function getOtherTable($table_factory_key){
        return $this->getOtherService($table_factory_key);
    }
    
    protected function getOtherService($service_factory_key){
        return $this->getServiceLocator()->get($service_factory_key);
    }
    
    /* This function parses multipart form data that was not sent with a POST method
     * It might contain file args in the raw request content or an octet stream that must be 
     * handled differently
     * @return: An array of name/value pairs. Note that if a file is encountered, the value is 
     * an array itself containing: name,type and content or tmp_name (in case of a POST)
     * If media were sent via POST the files are uploaded to some tempory folder, otherwise the data
     * is present as raw file data in content.
     */ 
    private function parseMultidataHttpRequest($request, HeaderInterface $content_type_header)
    {
        // read incoming data
        $content_type_val = $content_type_header->getFieldValue();
        $matches = null;
        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $content_type_val, $matches);
        $boundary = $matches[1];
        $input = $request->getContent();
        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/", $input);
        array_pop($a_blocks);
        $a_data = array();
        // loop data blocks
        foreach ($a_blocks as $block) {
            if (empty($block)) {
                continue;
            }
            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== FALSE) {
                // match "name", then everything after "stream" (optional) except for prepending newlines 
                preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                if ($matches) {
                    $a_data[$matches[1]] = $matches[2];
                }
            } elseif (strpos($block, 'filename=') !== FALSE) {
                preg_match('/name=\"([^\"]*)\";\s*filename="([^\"]*)"\s*[\n\r]+Content-Type:\s*(\S+)\s*[\n\r]*(.*)?$/s', $block, $matches);
                if ($matches) {
                    $name = $matches[1];
                    $filename = $matches[2];
                    $file_type = $matches[3];
                    $content = $matches[4];
                    $file_val = array('file_name' => $filename, 'file_type' => $file_type, 'file_content' => $content);
                    $a_data[$name] = $file_val;
                }
            } else {// parse all other fields
                preg_match('/name=\"([^\"]*)\"[\n\r]+([^\n\r].*)?\r$/s', $block, $matches);
                if ($matches) {
                    $a_data[$matches[1]] = $matches[2];
                }
            }
        }
        return $a_data;
    }
}
