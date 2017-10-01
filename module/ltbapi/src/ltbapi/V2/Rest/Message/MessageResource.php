<?php
namespace ltbapi\V2\Rest\Message;
use Application\Listener\MyAbstractResourceListener;

class MessageResource extends MyAbstractResourceListener
{
    protected $end_point = 'Message';
    protected $log_id_name = 'mess_code';
    protected $defined_methods = array('create', 'delete', 'fetch', 'fetchAll', 'patch',
        'update');
    protected $access_check_postponed = array('fetchAll' => TRUE, 'fetch' => TRUE);
}
