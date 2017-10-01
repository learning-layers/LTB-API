<?php

namespace Application\Model;

use Zend\InputFilter\Factory as InputFactory;     // <-- Add this import
use Zend\InputFilter\InputFilter;                 // <-- Add this import
use Zend\InputFilter\InputFilterAwareInterface;   // <-- Add this import
use Zend\InputFilter\InputFilterInterface;        // <-- Add this import

class Model {
    public $id_name = '';
    public $table = '';
    public $fields = array();
    public $defaults = array();
    public $collection_hide_fields = array();
    public $entity_hide_fields = array();
    public $type = '';
    public $warnings = '';
    private $inputFilter;
    
    const DATA_TYPE_ARRAY = 'array';
    const DATA_TYPE_OBJECT = 'object';
    
    public function __construct($initial_data='', $type_initial=self::DATA_TYPE_ARRAY){//, $controller=null) {        
        if ($initial_data){
            //TODO check some filters etc for security
            if ($type_initial == self::DATA_TYPE_ARRAY){
                $this->exchangeArray($initial_data);
            } elseif ($type_initial == self::DATA_TYPE_OBJECT) {
                $this->exchangeObject($initial_data);
            } else {
                throw new \Exception('Initial data of object should be a compound value');
            }
        } else {
           foreach ($this->fields as $field) {
              $this->$field = (isset($this->defaults[$field])) ?
                 $this->defaults[$field] :
                 null;
            } 
        }
    }

    public function getIdName() {
        return $this->id_name;
    }

    public function getFields() {
        return $this->fields;
    }

    public function getDefaults() {
        return $this->defaults;
    }

    public function exchangeObject($data) {
        foreach ($this->fields as $field) {
            $this->$field = 
                isset($data->$field) ?
                $data->$field :
                (isset($this->defaults[$field]) ? $this->defaults[$field]: null);
        }
    }
    
    //Set values in the model, using defined defaults for unset fields
    public function exchangeArray($data) {
        foreach ($this->fields as $field) {
            $this->$field = 
               isset($data[$field]) ? 
               $data[$field] :
               (isset($this->defaults[$field]) ? $this->defaults[$field] : null);
        }
    }
    
    /*
     * Sometimes we want to check whether the passed values can indeed be updated in the model
     * we filter the object or array, based on the fields, but do not add defaults. This can be usefull for
     * patching statements
     *
     */
    public function filterObject($data) {
        $o = new \stdClass();
        foreach ($this->fields as $field) {
            if (isset($data->$field)){
                $o->$field = $data->$field;
            }
        }
        return $o;
    }
    
    //Set values in the model, using defined defaults for unset fields
    public function filterArray($data) {
        $a = array();
        foreach ($this->fields as $field) {
            if (isset($data[$field])){
                $a[$field] = $data[$field];
            }
        }
        return $a;
    }
    /*
     * 
     * Removes all the properties to replace them with the defaults if existent, like the object 
     * was just created
     */
     public function getCleanObject(){
        foreach ($this->fields as $field) {
            $this->$field = 
               (isset($this->defaults[$field]) ? $this->defaults[$field] : null);
        }
        $this->setModelInitialisedStatus(FALSE);
        return $this;
    }
    
    //TODO: sort out the inconsistency between getting values and setting values:
    //excluding foreign fields is just a trick to be able to do joins with other tables
    //and extending the resultset to include fields of other tables. This trick is used in Account
    //Most other models are 'clean': foreignfields =[] and so all fields going in and out the model
    //are also in the table attached to the model
    //Set values in the model like exchangeArray, but leaving old values intact for unset fields
    public function setValues($data) {
        
        foreach ($this->fields as $field) {
            if (isset($data[$field])){
                $this->$field = $data[$field];
            }
        }
    }
    
    public function setObjectValues($data) {
        
        foreach ($this->fields as $field) {
            if (isset($data->$field)){
                $this->$field = $data->$field;
            }
        }
    }
    
    public function setValue($field, $value){
        if (in_array($field, $this->fields)){
            $this->$field = $value;
        }
    }
    
    //Just a wrapper
    public function getValues(){
        return $this->getArrayCopy();//use for clean models only
    }
    
    public function toArray() {
        //For some reason the Model is not instance of ArrayObject, so we add this method 
        //where normally a call to getArrayCopy in AbstractResultSet->toArray would be done
        return $this->getArrayCopy();
    }

    // We need to define the standard method getArrayCopy
    public function getArrayCopy() {
        $a = array();
        foreach ($this->fields as $key) {
          $a[$key] = (!isset($this->$key) || is_null($this->$key)) ?
            (isset($this->defaults[$key]) ? $this->defaults[$key] : null) : 
             $this->$key;
        }
        
        return $a;
    }

    // Add content to this method:
    public function setInputFilter(InputFilterInterface $inputFilter) {
        $this->inputFilter = $inputFilter;
//     	throw new \Exception("Not used");
    }

    public function getInputFilter() {
        if (!$this->inputFilter) {
            $this->inputFilter = new InputFilter();
            return $this->inputFilter;
        } else {
            return $this->inputFilter;
        }
    }

    /*
     * Possible filters are: Int, StripTags, StringTrim
     * Possible validators are StringLength ('options' => array(
      'encoding' => 'UTF-8',
      'min'      => 1,
      'max'      => 100,)
     * 
     */

    public function addFilter($field, $required = true, $filters = array(), $validators = array()) {
        $filter_item = array(
            'name' => $field,
            'required' => $required
        );
        if ($filters) {
            if (is_array($filters)) {
                foreach ($filters as $f) {
                    $filter_item['filters'][] = array('name' => $f);
                }
            } else {
                $filter_item['filters'][] = array('name' => $filters);
            }
        }
        if ($validators) {
            if (is_array($validators)) {
                foreach ($validators as $name => $options) {
                    $filter_item['validators'][] = array('name' => $name, 'options' => $options);
                }
            } else {
                $filter_item['validators'][] = array('name' => $validators);
            }
        }

        $factory = new InputFactory();
        if (!$this->inputFilter) {
            $this->inputFilter = new InputFilter();
        }
        $this->inputFilter->add($factory->createInput($filter_item));
    }
    
    //Start over with fresh filter
    public function removeFilter($name) {
        if (!$this->inputFilter) {
            $this->inputFilter = new InputFilter();
        }
        $this->inputFilter->remove($name);
    }

}