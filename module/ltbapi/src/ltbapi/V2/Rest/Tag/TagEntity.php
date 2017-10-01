<?php
namespace ltbapi\V2\Rest\Tag;

class TagEntity extends \Application\Model\Model {

    public $type = 'tag';
    public $table = 'tag';
    public $id_name = 'tag_id';
    public $fields = array(
        'tag_id',
        'tag_type',
        'entity_id',
        'owner_id',
        'owner_code',
        'tag_txt',
        'timestamp',
        'private');
    public $defaults = array('tag_id' => 0, 'tag_type' =>'stack', 'private' => 0);
    public $collection_hide_fields = array();
    public $entity_hide_fields = array('entity_id', 'owner_id');
}
