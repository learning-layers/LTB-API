<?php
namespace ltbapi\V2\Rest\Embed;
use Application\Listener\MyAbstractResourceListener;

class EmbedResource extends MyAbstractResourceListener
{
    private $apikey;
    protected $defined_methods = array(//'create', 'delete', 'fetch', 
        'fetchAll'//, 'patch',
        //'update', 'deleteList', 'replaceList'
        );
    private $methods_access = array('fetchAll' => TRUE);
    
    protected $end_point = 'Embed';
    
    public function __construct($apikey, $account='')
    {
      $this->apikey = $apikey;
      parent::__construct(null, $account);
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
    public function fetchAll($params = array())
    {
        $soft = TRUE || _SWITCH_OFF_AUTH_CHECK;
        if ($this->account->getAuth($this->getEvent(), $soft)){
            $pro = new \Embedly\Embedly(array(
                'key' => $this->apikey
            ));
            try {
                $objs = $pro->oembed(array(
                    "urls" => $params['url'],
                    "maxwidth" => isset($params['width']) ? $params['width'] : null,
                    "maxheight" => isset($params['height']) ? $params['height'] : null,
                    "scheme" => (isset($params['scheme']) && in_array($params['scheme'], array('https', 'http'))) ? $params['scheme'] : 'http',
                  ));
            } catch (\Exception $e){
                return $this->returnResourceProblem(500, (_DEBUG ? $e : $e->getMessage()), 'Embedly did not return a valid result');
            }
            
            if (! $objs){
                return $this->returnResourceProblem(500, 'No results returned', null, 
                    'Embedly did not return a valid result');
            }
            $return = $objs[0];
            //TODO embedly has changed the api to return an array in case of an error, see rpc embedly
            if (($return->type == 'error')){
                return $this->returnResourceProblem($return->error_code, "Embed code could not be retrieved. ".
                    $return->error_message );
            }
            return $objs;
        } else {
            return $this->account->unAuthorisedObject('You cannot get embed code if you are not logged in');
        }
    }
    
    /* This function creates a tableGateway to the log table of the database and stores the action
     * the user has initiated
     */
    public function userLog($method, $soft, $user_id, $id=0, $params=null, $granted=TRUE){
        //Skip this function. We do not log actions.
    }
}
