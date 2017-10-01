<?php
namespace ltbapi\V2\Rest\Message;

class MessageEntity extends \Application\Model\Model
{
    public $type  = 'message';
    public $table = 'message';
    public $id_name = 'mess_id';
    
    public $fields = array(
        'mess_id',
        'mess_code',
        'subject',
        'content',
        'mess_type',//user, stack, collection
        'entity_code',
        'entity_id',
        'user_code',
        'user_id',
        'owner_id',
        'start',
        'end');
    public $collection_hide_fields = array('mess_id', 'owner_id', 'user_id', 'entity_id');
    public $entity_hide_fields = array('mess_id', 'owner_id', 'user_id', 'entity_id');
    public $defaults = array('status' => 'new', 'mess_type' => 'stack');
}