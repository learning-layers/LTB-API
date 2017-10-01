<?php
namespace ltbapi\V2\Rest\Tag;
use Application\Listener\MyAbstractResourceListener;

class TagResource extends MyAbstractResourceListener
{
    protected $end_point = 'Tag';
    protected $log_id_name = 'entity_id';
    protected $defined_methods = array('create', 'delete', //'deleteList', 
        'fetch', 'fetchAll', 'patch', 'update'
         //,'patchList','replaceList'
    );
}
