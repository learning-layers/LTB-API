<?php
namespace ltbapi\V2\Rest\Reference;

class ReferenceEntity extends \Application\Model\Model
{
    public $type  = 'reference';
    public $table = 'reference';
    public $id_name = 'reference_id';
    
    public $fields = array(
        'reference_id',
        'reference_code',
        'entity_code',
        'file_name',
        'file_type',
        'file_size',
        'file_ref_code',
        'created',
        'owner_id',
        'ref_type',//file, link
        'url',
        'external_url',
        'internal_url', 
        'image_url',
        'name',
        'description',
        'details'
        );
    public $collection_hide_fields = array('reference_id', 'owner_id');
    public $entity_hide_fields = array('reference_id', 'owner_id');
    public $defaults = array('public' => 1);
}
