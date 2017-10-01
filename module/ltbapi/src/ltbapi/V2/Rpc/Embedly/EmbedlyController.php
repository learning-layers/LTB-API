<?php
namespace ltbapi\V2\Rpc\Embedly;
//use Zend\Mvc\Controller\AbstractActionController;
use Application\Controller\MyAbstractActionController;

class EmbedlyController extends MyAbstractActionController
{
    private $apikey;
    
    public function __construct($apikey, $account='')
    {
      $this->apikey = $apikey;
      parent::__construct($account);
    }
      
    /**
     * Fetch the embed code from Embedly. This method is called by/via the RestController when 
     * the url /embed/ is called with the http method GET. All the other methods are not implemented
     * ;the parent will return an ApiProblem in those cases.
     * 
     * We expect only one url to be requested every time although emmbedly is able to do a couple
     * of urls in one request
     *
     * @param  array $params: width, height and url (just one)
     * @return the array that Embedly returns
     */
    public function embedAction()
    {
        $soft = TRUE || _SWITCH_OFF_AUTH_CHECK;
        if ($this->account->getAuth($this->getEvent(), $soft)){
            $pro = new \Embedly\Embedly(array(
                'key' => $this->apikey
            ));
            try {
                $params = $this->getMyQueryParams();
                $objs = $pro->oembed(array(
                    "urls" => $params['urls'],
                    "maxwidth" => isset($params['width']) ? $params['width'] : null,
                    "maxheight" => isset($params['height']) ? $params['height'] : null,
                    "scheme" => (isset($params['scheme']) && in_array($params['scheme'], array('https', 'http'))) ? $params['scheme'] : 'http',
                  ));
            } catch (\Exception $e){
                return $this->returnControllerProblem($e->getCode(),
                    (_DEBUG ? $e->getMessage() : 'Embedly did not return a valid result'));
            }
            
            if (! $objs){
                return $this->returnControllerProblem(500, 'No results returned');
            }
            $return = $objs[0];
            //If an error occurs an array is returned [status, msg, 'error']
            //But also, in other cases an error object is returned
            if ((is_int($return) && $objs[2] == 'error')){
                return $this->returnControllerProblem($return,
                    "Embed code could not be retrieved. ".$objs[1] );
            }
            if (is_object($return) && ($return->type == 'error')){
                return $this->returnControllerProblem($return->error_code, "Embed code could not be retrieved. ".
                    $return->error_message );
            }
            return $this->returnControllerResult($objs);
        } else {
            return $this->returnControllerProblem(401, 'You cannot get embed code if you are not logged in');
            //$this->account->unAuthorisedObject('You cannot get embed code if you are not logged in');
        }
    }
    
    public function extractAction()
    {
        $soft = TRUE || _SWITCH_OFF_AUTH_CHECK;
        if ($this->account->getAuth($this->getEvent(), $soft)){
            $pro = new \Embedly\Embedly(array(
                'key' => $this->apikey
            ));
            try {
                $params = $this->getMyQueryParams();
                $objs = $pro->extract(array(
                    "urls" => $params['urls'],
                    "maxwidth" => isset($params['width']) ? $params['width'] : null,
                    "maxheight" => isset($params['height']) ? $params['height'] : null,
                    "scheme" => (isset($params['scheme']) && in_array($params['scheme'], array('https', 'http'))) ? $params['scheme'] : 'http',
                  ));
            } catch (\Exception $e){
                return $this->returnControllerProblem($e->getCode(),
                    (_DEBUG ? $e->getMessage() : 'Embedly did not return a valid result'));
            }
            
            if (! $objs){
                return $this->returnControllerProblem(500, 'No results returned');
            }
            $return = $objs[0];
            //If an error occurs an array is returned [status, msg, 'error']
            //But also, in other cases an error object is returned
            if ((is_int($return) && $objs[2] == 'error')){
                return $this->returnControllerProblem($return,
                    "Embed code could not be retrieved. ".$objs[1] );
            }
            if (is_object($return) && ($return->type == 'error')){
                return $this->returnControllerProblem($return->error_code, "Embed code could not be retrieved. ".
                    $return->error_message );
            }
            return $this->returnControllerResult($objs);
        } else {
            return $this->returnControllerProblem(401, 'You cannot get embedly extraction if you are not logged in');
        }
    }
}
