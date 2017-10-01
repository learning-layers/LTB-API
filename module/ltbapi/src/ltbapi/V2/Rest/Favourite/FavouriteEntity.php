<?php
namespace ltbapi\V2\Rest\Favourite;

class FavouriteEntity extends \Application\Model\Model 
{
    public $type  = 'favourite';
    public $table = 'favourite';
    public $id_name = 'favourite_id';
    public $fields = array('favourite_id', 'fav_type','entity_id', 'entity_code', 'user_id');
    public $collection_hide_fields = array('entity_id', 'user_id');
    public $entity_hide_fields = array('entity_id', 'user_id');
    
    public $defaults = array('favourite_id' => 0, 'fav_type' => 'stack');
}