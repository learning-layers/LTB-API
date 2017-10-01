<?php
namespace ltbapi\V2\Rest\Favourite;
use Application\Listener\MyAbstractResourceListener;

class FavouriteResource extends MyAbstractResourceListener
{
    protected $end_point = 'Favourite';
    protected $log_id_name = 'entity_code';
    protected $defined_methods = array('create', 'delete', //'deleteList', 
        'fetch', 'fetchAll', 'patch', 'update'
         //,'patchList','replaceList'
    );
}
