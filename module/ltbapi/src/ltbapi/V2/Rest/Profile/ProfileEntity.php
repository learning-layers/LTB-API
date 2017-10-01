<?php
namespace ltbapi\V2\Rest\Profile;

class ProfileEntity extends \Application\Model\Model 
{
    public $type  = 'profile';
    public $table = 'profile';
    public $id_name = 'profid';
    public $fields = array('profid', 'profile_code', 'user_id', 'user_code', 'name', 'surname',
        'birthday', 'prof_nr', 'prof_nr_sub', 'partic_nr', 
        'course_nr', 'start_date', 'end_date', 'email', 'stack_code',
        );
    public $collection_hide_fields = array('profid', 'user_id');
    public $entity_hide_fields = array('profid', 'user_id');
    
    public $defaults = array('profid' => 0, 'prof_nr_sub' => 0);
}