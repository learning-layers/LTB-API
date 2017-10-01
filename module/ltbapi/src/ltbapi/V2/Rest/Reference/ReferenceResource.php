<?php
namespace ltbapi\V2\Rest\Reference;

use Application\Listener\MyAbstractResourceListener;

class ReferenceResource extends MyAbstractResourceListener
{
    protected $end_point = 'Reference';
    protected $log_id_name = 'reference_code';
    protected $defined_methods = array('create', 'delete', 'deleteList', 
        'fetch', 'fetchAll', 'patch', 'update'
         //,'patchList','replaceList'
    );
    protected $access_check_postponed = array('fetchAll' => TRUE);
 
    //all functionality invoked in parent
}
