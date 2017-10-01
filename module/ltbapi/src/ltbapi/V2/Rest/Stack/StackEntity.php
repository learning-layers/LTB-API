<?php
namespace ltbapi\V2\Rest\Stack;

class StackEntity extends \Application\Model\Model 
{

    public $type  = 'stack';
    public $table = 'stack';
    public $id_name = 'stack_id';
    public $fields = array('stack_id', 'stack_code', 'name', 'description', 'domain', 'owner_id', 'owner_code', 
            'public', 'access_level', 'details', 'version', 'create_ts', 'update_ts', 'archived');
    public $collection_hide_fields = array('details', 'owner_id');
    public $entity_hide_fields = array('owner_id','stack_id');
    
    public $defaults = array('stack_id' => 0, 'version' => 1, 'public' => 0, 'archived' => 0, 'access_level' => 1);
    
}
