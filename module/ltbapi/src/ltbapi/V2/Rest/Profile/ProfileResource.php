<?php
namespace ltbapi\V2\Rest\Profile;

use Application\Listener\MyAbstractResourceListener;

class ProfileResource extends MyAbstractResourceListener
{
    protected $end_point = 'Profile';
    protected $log_id_name = 'profile_code';
    protected $defined_methods = array('create', 'delete', //'deleteList', 
        'fetch', 'fetchAll', 'patch', 'update'
         //,'patchList','replaceList'
    );
}
