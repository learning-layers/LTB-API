<?php
namespace ltbapi\V2\Rpc\Show;

use Application\Shared\SharedStatic;
use Zend\Mvc\Controller\AbstractActionController;

class ShowController extends AbstractActionController
{
    public function __construct($this_server_config=''){
        $this->this_server_config = $this_server_config;
    }
    
    /*
     * The main intent of this rpc call is to redirect the user to the tilestore viewer of that stack
     * There is no authorisation required as this is handled in the next api call retrieving the stack (initiated 
     * by the tilestore view page.
     */
    public function showAction()
    {
        //Get stack code
        $stack_code = $this->params()->fromRoute('stack_code', $this->this_server_config['DefaultStack']);
        
        //Get type (default: stack)
        $type = $this->params()->fromRoute('type', 'stack');
        switch ($type) {
            case 'stack':
                $tilestore_client = $this->this_server_config['TsUri'];
                header ("Location: ${tilestore_client}www/#/stack/$stack_code");
            break;
            default : echo "No show action defined for this type $type ";
        }
        exit;
    }
    
    
}
