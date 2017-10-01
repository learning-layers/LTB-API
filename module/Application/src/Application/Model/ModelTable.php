<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Where;
use ZF\ApiProblem\ApiProblem;
use Zend\Db\ResultSet\ResultSet;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
//To show the sql for dev purposes: uncomment the next use statements and do something like
/* $sql = $this->tableGateway->getSql();
  $selectObj = $sql->select()->where(array($this->id_name => $id));
  echo "Executed Sql: ".$sql->getSqlStringForSqlObject($selectObj); */
//use Zend\Db\Sql\Expression;
//use Zend\Db\Sql\Where;

class ModelTable implements ServiceLocatorAwareInterface{
    protected $model_initialised = FALSE;
    protected $tableGateway;
    protected $model = null;
    protected $controller = null;
    protected $services;
    protected $account = null;
        
    const ModelPostfix = 'Entity';
    const DATA_TYPE_ARRAY = 'array';
    const DATA_TYPE_OBJECT = 'object';
    const DEFAULT_CLEAN = FALSE;
    const StrictUpdate = TRUE;
    
    public function __construct(TableGateway $tableGateway, Model $model = null, $controller= null) {
        $this->tableGateway = $tableGateway;
        
        if ($model) {
            $this->setModel($model);
        }
        if ($controller) {
            $this->setController($controller);
        }
        //The magic get function makes sure the id_name is searched for in the model object too
        if (!$this->id_name){
            throw new \Exception('Table model is wrong. Missing definition for id field');
        }
        if (!$this->type || !$this->table){
            throw new \Exception('Table model is wrong. Missing definition for type or table. '.
                'You might have used table or type in the fields. This is not allowed.');//TODO . print_r($this, 1)
        }
        return $this;
    }

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    public function getServiceLocator()
    {
        return $this->services;
    }
    
    public function getOtherTable($table_factory_key)
    {
        $table = $this->getOtherService($table_factory_key);
        //In case the other table is not a ModelTable, just do not make use
        //of account object
        $this_class = '\Application\Model\ModelTable';
        
        if ($table instanceof $this_class){
            $table->setAccount($this->account);
        }
        return $table;
    }
    
    public function getOtherService($service_factory_key)
    {
        return $this->getServiceLocator()->get($service_factory_key);
    }
    
    public function getModel($data='', $type=self::DATA_TYPE_ARRAY, $clean=self::DEFAULT_CLEAN) {
        if ($this->model) {
            if ($clean) {
                return $this->model->getCleanObject();
            } elseif ($data) {
                if ($type == self::DATA_TYPE_ARRAY){
                    $this->model->exchangeArray($data);
                } elseif ($type == self::DATA_TYPE_OBJECT) {
                    $this->model->exchangeObject($data);
                } else {
                    throw new \Exception('The initial data for the model is not of the correct type.');
                }
            }
            return $this->model;
        }
        throw new \Exception('The table has no correct model attached.');
    }

    public function setAccount($account){
        $this->account = $account;
    }

    public function setModel($model) {
        $this->model = $model;
    }

    public function getController() {
        if ($this->controller) {
           return $this->controller;
        }
        throw new \Exception('The table has no controller attached.');
    }

    public function setController($controller) {
        $this->controller = $controller;
    }
    
    public function translate($translate_this){
        return $translate_this; //$this->getController()->translate($translate_this);
    }
    
    public function getModelInitialisedStatus() {
        return $this->model_initialised;
    }
    
    public function setModelInitialisedStatus($status = TRUE) {
        $this->model_initialised = $status;
    }
        
    public function __call($method, $params) {
        $model = $this->getModel();//Get the model as it is attached now to this handler
        if (method_exists($model, $method)) {
            return call_user_func_array(array($model, $method), $params);
        } else {
            throw new \Exception("Method $method does not exist. ");
        }
    }

    public function __get($name) {
        if ($this->model){
            return (isset($this->model->$name)) ? $this->model->$name : null;
        }
        throw new \Exception('Trying to get non-existent property from model.');
    }

    public function getAllItems($to_array = FALSE) {
        $resultSet = $this->tableGateway->select();
        return $to_array ? $resultSet->toArray() : $resultSet;
    }

    public function getExternalItems($table, $cond, $to_array=TRUE){
        $table_gateway = $this->getOtherTable($table);
        $sql = $table_gateway->getSql();
        $selectObj = $sql->select()->where($cond);
        $rowset = $table_gateway->selectWith($selectObj);
        return $to_array ? $rowset->toArray() : $rowset;
    }
    
    public function getColumnFromResult($column, $result, $is_array=TRUE){
        $list = array();
        if (is_string($result)){
            //this is the case if some exception occurs that was not caught and returned as result somehow for example in getItemsJoin.
            //This only happens when _DEBUG is on, but to be sure, we test that again
            if(_DEBUG){
                die("The database crashed for some reason. Got back: $result");
            } else {
                return $list;
            }
        }
        if (! count($result)) return $list;
        if ($is_array){
            foreach ($result as $r){
                $list[] = $r[$column];
            }
        } else {
            foreach ($result as $r){
                $list[] = $r->$column;
            }
        }
        return $list;
    }
    
    public function getItems($cond = array(), $to_array = FALSE, $field_set='', $order = NULL) {
       if ($cond instanceof Predicate\PredicateInterface) {
           //We do not need to do anything but check that the set is not empty
           if ($cond instanceof Predicate\PredicateSet && ! $cond->count()){
               $cond= array();
           }
       } else {
            if (is_object($cond)){
                $cond = (array) $cond;
            }
            if (is_array($cond)) {
                 //TODO: some checks whether array_keys in model:: 
                 //If the Keys searched, are not in model it ignores them and goes to getAllItems
                 //-or searches for the ones that exist
                 //more checks on select $field_set
                $set = new \Zend\Db\Sql\Where();
        
                foreach ($cond as $key => $condition_value){
                    if (is_array($condition_value)){
                        $set->in($key, $condition_value);
                    } else {
                        $set->equalTo($key, $condition_value);
                    }
                }
                $cond = $set;
             } elseif ($cond) {   
                 $cond = array($this->id_name => $cond);
             } else {
                 $cond = array();
             }
       }
        $sql = $this->tableGateway->getSql();
        $selectObj = $sql->select()->where($cond);
        if ($order) {
            if (!is_array($order)) {
                $order = array($order);
            }
            $selectObj->order($order);
        }
        /* It seems that the function selectWith gets all the objects
         * through the $resultSetPrototype object giving null values to the values we
         * wanted to hide.
         */
        if ($field_set){
            if (is_string($field_set)){
                $field_set = explode(',', $field_set);
            }
            $selectObj->columns($field_set);
            $adapter = $this->tableGateway->getAdapter();
            $statement = $sql->getSqlStringForSqlObject($selectObj);
            $rowset    = $adapter->query($statement, $adapter::QUERY_MODE_EXECUTE);
        } else {
            $rowset = $this->tableGateway->selectWith($selectObj);
        }
        return $to_array ? $rowset->toArray() : $rowset;
    }
    /*
     * @param cond: either an array or a PredicateInterface
     * @param to_array: whether to convert the objects to arrays
     * @field_set the set of fields to include from this table, when not set: the default
     * collection set or otherwise just *
     * @order: a field to order on, optional
     * @joins: [other table_str, on_spec_str, [<alias> => expr_str|fld_str]]
     * @group: a field (or array of fields) to group on
     * @single_record: if we expect to have only one record: get first of list
     * @show: show the sql string on screen for debugging purposes
     */
    public function getItemsJoin($cond = array(), $to_array=FALSE, $field_set='',
        $order=NULL, $joins=NULL, $group=NULL, $single_record=FALSE, $show=FALSE,
        $offset=0, $page_size=NULL) {
       if ($cond instanceof Predicate\PredicateInterface) {
           //We do not need to do anything
       } else {
            if (is_array($cond)) {
                 //TODO: some checks whether array_keys in model:: 
                 //If the keys searched, are not in the model it ignores them and
                 //goes to getAllItems -or searches for the ones that exist
                 //more checks on select $field_set
             } else {   
                 $cond = array($this->id_name => $cond);
             }
       }
       
        $sql = $this->tableGateway->getSql();
        $adapter = $this->tableGateway->getAdapter();
        $selectObj = $sql->select()->where($cond);
        if ($field_set){
            $selectObj->columns((is_string($field_set) ? explode(',', $field_set): $field_set));
        } else {
            $selectObj->columns($this->getCollectionFields($selectObj::SQL_STAR));
        }
        if ($order) {
            if (!is_array($order)) {
                $order = array($order);
            }
            $selectObj->order($order);
        }
        
        if ($group) {
            if (!is_array($group)) {
                $group = array($group);
            }
            $selectObj->group($group);
        }
        
        try {
            if ($joins){
                //joins specs: [table_str, on_spec_str, [<alias> => expr_str|fld_str]]
                foreach ($joins as $join_spec){
                    $tbl = $join_spec[0];
                    $on_spec = $join_spec[1];
                    $fld_spec = isset($join_spec[2]) ? $join_spec[2] : null;
                    
                    if ($fld_spec){
                        $f = array();
                        foreach ($fld_spec as $fld_alias => $fld){
                            if (is_numeric($fld_alias)){
                                $f["${tbl}.$fld"] = $fld;
                            } else {
                               $f[$fld_alias] = $fld; 
                            }
                        }
                        $join_fields = $f;
                    } else {
                        $join_fields = $selectObj::SQL_STAR;
                    }
                    $selectObj->join($tbl, $on_spec, $join_fields, $selectObj::JOIN_LEFT);
                }
            }
            if ($offset > 0){
                $selectObj->offset($offset);
            }
            if ($page_size && $page_size > 0){
                $selectObj->limit($page_size);
            }
           $statement = $sql->getSqlStringForSqlObject($selectObj);
           if ($show){
               echo $statement;
           }
           $rowset = $adapter->query($statement, $adapter::QUERY_MODE_EXECUTE);
           //Can only be done, when no other tables are involved in the selection fields
           //Otherwise these fields are ignored by the resultset filter of the tablegateway
           //   $rowset = $this->tableGateway->selectWith($selectObj);
           
        } catch (\Exception $ex){
            return (_DEBUG ? (@$statement ? "[$statement]": '').$ex->__toString(): FALSE);
        }
        if ($single_record){
            if ($to_array){
                $r = $rowset->toArray();
                return $r ? $r[0] : array();
            }
            return $rowset->current();
        }elseif ($to_array) {
            return $rowset->toArray();
        } else {
            return $rowset;
        }
    }

    public function getItem($id = null, $where=array(), $to_array=FALSE, $restrict=FALSE, $show_query=FALSE) {
        if (is_string($id)){
            $id = \Application\Shared\SharedStatic::getID($id);//converts short code to id
        }
        if ($id && ! is_numeric($id)){
            throw new \Exception("Wrong id passed. We expect an integer.", 500);
        }
        $id = (int) $id;
        if (!$where){
            $where = array();
        }
        if (!$id) {
            throw new \Exception('No id specified when trying to retrieve item.');
        }
        if ($where instanceof Predicate\PredicateSet){
            $id_pred = new Predicate\Predicate();
            $id_pred->equalTo($this->id_name, $id);
            $where = new Predicate\PredicateSet(array($id_pred, $where));
        } else {
            $where[$this->id_name] = $id;
        }
        if ($restrict){
            $sql = $this->tableGateway->getSql();
            $adapter = $this->tableGateway->getAdapter();
            $selectObj = $sql->select()->where($where);
            $selectObj->columns($this->getEntityFields());
            //The following pushes the resulting object through the object's resultset
            //which will give 'hidden' fields the default value or null which might be
            //confusing at the client side. E.g. owner_id is translated to owner_code and the
            //owner_id should not be used at the client side at all
            
            $statement = $sql->getSqlStringForSqlObject($selectObj);
            if ($show_query){
                echo $statement;
            }
            $rowset = $adapter->query($statement, $adapter::QUERY_MODE_EXECUTE);

        } else {
            $rowset = $this->tableGateway->select($where);
        }
        $row = $rowset->current();
        if (!$row) {
            return FALSE;
        }
        return ($to_array && ($row instanceof \Application\Model\Model)) ? $row->toArray() : $row;
    }

    public function getItemWhere($where = array(), $to_array = FALSE,
        $order = NULL, $single_record = FALSE) {

        //To show the sql for dev purposes: uncomment
        /* $sql = $this->tableGateway->getSql();
          $selectObj = $sql->select()->where($where);
          echo 'Executed Sql: '.$sql->getSqlStringForSqlObject($selectObj); */
       
        $rowset = $this->tableGateway->select($where);
        $row = $rowset->current();

        if (!$row) {
            return FALSE;
        }
        return ($to_array) ? $row->toArray() : $row;
    }
    
    public function getItemPair($id = 0, $table_name = '', $on_spec = '', $field_spec='',
        $to_array = FALSE, $where=array(), $order = NULL, $single_record = FALSE, $show_query=FALSE) {
        
        //To show the sql for dev purposes: uncomment
        /* $sql = $this->tableGateway->getSql();
          $selectObj = $sql->select()->where(array($this->id_name => $id));
          echo "Executed Sql: ".$sql->getSqlStringForSqlObject($selectObj); */
        
        $adapter = $this->tableGateway->getAdapter();
        $sql     = new Sql($adapter);
        $table = $this->table;

        if (!$table_name){
            throw new \Exception('No joining table name specified', 500);
        } else {
            $join_alias = ($table_name !== $table) ? $table_name : 'JOINING';
        }
        
        if (!$on_spec) {
            $on = "$table." . $this->id_name . " = $join_alias." . $this->id_name;
        } else {
            $item_ids = array_keys($on_spec);
            $ons = array();
            foreach ($on_spec as $id_name => $join_name) {
                $ons[] = "$table.$id_name = $join_alias.$join_name";
            }
            $on = implode(" AND ", $ons);
        }
        $add_id_condition = ($id !== 0);
        if ($where){
            if ($where instanceof Predicate\PredicateInterface){
                if ($add_id_condition){
                    $id_pred = new Predicate\Predicate();
                    $id_pred->equalTo("$table.".$this->id_name, $id);
                    $where = new Predicate\PredicateSet(array($id_pred, $where));
                }
            } elseif(is_array($where)){
                if ($add_id_condition){
                    $where[$this->id_name] = $id;
                }
                foreach ($where as $f => $v){
                    $where["$table.$f"] = $v;
                    unset($where[$f]);
                }
            } elseif (is_string($where) && $add_id_condition) {
                $where .= " AND $table.".$this->id_name. " = $id";
            }
        } else {
            $where = $add_id_condition ? array("$table.".$this->id_name => $id): array();
        }
        
        $selectObj = $sql->select($table);
        if (! $field_spec){
            $select_join_spec = $selectObj::SQL_STAR;
            $selectObj->columns($this->getEntityFields($selectObj::SQL_STAR));
        } else {
            //Handle field specs. The list can contain the main table which will be handled
            //first. After handle the joining table
            if (isset($field_spec[$table]) && $field_spec[$table]){
                //For some reason a table without columns does not render
                //so we either rely on sensible specs or * or a selection from 
                //the existing fields which is an array of existing fields for the 
                //main table.
                $selectObj->columns($field_spec[$table]);
                unset($field_spec[$table]);
            } else {
                $selectObj->columns($this->getEntityFields($selectObj::SQL_STAR));
            }
            //The non empty field spec can consist of a table specific list or
            //a plain array of field names (in which case the main table cannot
            //be specified in the field_spec). We already dealt with the main table
            //now specify joining fields if there are specs left or take * otherwise.
            $select_join_spec = array();
            if ($field_spec){
                $fields = isset($field_spec[$table_name]) ? $field_spec[$table_name]: $field_spec;
                if (!$fields) {
                    $select_join_spec = $selectObj::SQL_STAR;
                } else {
                    $expr_count = 0;
                    foreach ($fields as $alias => $f_name_or_expr){
                        if ($f_name_or_expr instanceof \Zend\Db\Sql\Expression){
                            $name = is_numeric($alias) ? ("$join_alias.expr". ++$expr_count) : $alias;
                        } else {
                            $name = is_numeric($alias) ? "$join_alias.$f_name_or_expr" : $alias;
                        }
                        $select_join_spec[$name] = $f_name_or_expr;
                        
                    }
                }   
            } else {
                $select_join_spec = $selectObj::SQL_STAR;
            }
        }
        $selectObj->where($where);
        
        $selectObj->join(array($join_alias => $table_name), $on, $select_join_spec, $selectObj::JOIN_LEFT);
        if ($order) {
            if (!is_array($order)) {
                $order = array($order);
            }
            $selectObj->order($order);
        }
        
        try {
           $statement = $sql->getSqlStringForSqlObject($selectObj);
           if ($show_query){
               echo "Executed: $statement";
           }
           $rowset = $adapter->query($statement, $adapter::QUERY_MODE_EXECUTE);
        } catch (\Exception $e) {
            if (_DEBUG){
                die($e->getMessage() . "<br/>Executed Sql: $statement");
            } else {
                throw new \Exception($this->translate('Some database error occured.'));
            }
        }
        
        if ($single_record){
            if ($to_array){
                $r = $rowset->toArray();
                return $r ? $r[0] : array();
            }
            return $rowset->current();
        } elseif ($to_array) {
            return $rowset->toArray();
        } else {
            return $rowset;
        }
    }
    
    public function getEntityFields($alt=''){
        return $this->entity_hide_fields ?
            array_diff($this->fields, $this->entity_hide_fields) :
            ($alt ?: $this->fields);
    }
    
    /* Returns a list of fields to return in queries. Defaults to the fields
     * defined in entity class minus the hide fields if any
     */
    public function getCollectionFields($alt=''){
        return $this->collection_hide_fields ?
            array_diff($this->fields, $this->collection_hide_fields) :
            ($alt ? (is_string($alt) ? array($alt): $alt): $this->fields);
    }
    
    /*
     * Returns an Api problem which is a valid response communicating the error
     */
    public function returnProblem($status=500, $detail='', $title='', $params=null, $type=null) {
        return \Application\Shared\SharedStatic::returnApiProblem($status, $detail, $title, $params, $type);
    }
    
    /* returning a response, gives the freedom to send arbitrary datastructures back communicating the 
     * result of the action.
     */
    public function returnResponse($result, $warnings='', $messages=''){
        $body = json_encode(array('result'=>$result, 'warnings' => $warnings, 'msg' => $messages));
        $resp = new \Zend\Http\Response();
        $resp->setContent($body);
        return $resp;
    }
    
    public function isProblem($result) {
        return $result instanceof ApiProblem;
    }
    
    public function composeWarnings(ApiProblem $result){
        return _DEBUG ? 'The Social Semantic Server in not in Sync with this server now: '.$result->getTitle().
            $result->getDetail() : '';
    }
    
    public function convertCodeToId($short) {
        if (is_numeric($short)) {
            return $short;
        } else {
            return \Application\Shared\SharedStatic::my_reconvert($short);
        }
    }
    
    public function convertIdToCode($id) {
        if (is_numeric($id)) {
            return \Application\Shared\SharedStatic::my_convert($id);
        } else {
            return $id;
        }
    }
    
    public function convertDateToTimestamp($date){
        if (is_numeric($date)) {
            return $date;
        } else {
            return \Application\Shared\SharedStatic::convertDateToTimestamp($date, 'Y-m-d');
        }
    }
    
    public function saveItem($model, $existing=null, $force_insert=FALSE, $log_insert=FALSE) {
        $id_name = $this->id_name;
        try {
            if (is_object($model)){
                $data = $model->getArrayCopy();
                unset($data[$id_name]);
                $id = isset($model->$id_name) ? (int) $model->$id_name : 0;   
            } else {
                $id = isset($model[$id_name]) ? (int) $model[$id_name] : 0;
                unset($model[$id_name]);
                //$class = ucfirst($this->type).self::ModelPostfix;
                $model = $this->getModel($model, self::DATA_TYPE_ARRAY);
                $data = $model->getArrayCopy();
            }

            //If the id is not in the model, it is a new item
            if ($id == 0 || $force_insert) {
                if ($log_insert) {
                    $insert = new \Zend\Db\Sql\Insert($this->table);
                    $insert->values($data);
                    $platform = $this->tableGateway->getAdapter()->getPlatform();
                    \Application\Shared\SharedStatic::doLogging('DB DEBUG: '. $insert->getSqlString($platform));
                }
                $this->tableGateway->insert($data);
                return $this->tableGateway->lastInsertValue;
            } else {
                if ($existing || $this->getItem($id)) {
                    $this->tableGateway->update($data, array($id_name => $id));
                    return $id;
                } else {
                    throw new \Exception(sprintf($this->translate('OBject %2$s with id (%1$s) does not exist'), $this->type, $id));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($this->translate('Some database error occured.'). ':'.$e->getMessage().
                    (_DEBUG ? 'Got db error "'  . $e->__toString().
                        '" Tried saving model:' .
                        print_r(get_object_vars($model), 1)
                        : ''
                    ),
                500
            );
        }
    }

    public function updateItem($id='', $where='', $data='', $existing=FALSE, $log_update=FALSE) {
        try {
            if ((!$id && !$where) || (!$data)) {
                throw new \Exception(
                        $this->translate('One tried to update the item without a valid conditional restriction or data to set.').
                        (_DEBUG ? "id: $id where:".print_r($where, 1)."  data".print_r($data, 1): '' ));
            }
            $id_name = $this->id_name;
            $where = $where ?: array();
            if ($id){
                $where[$id_name] = $id;
            }

            if ($existing || $this->getItemWhere($where)) {
                //Here we filter the passed data through the data model
                //As we are only using filterObject/filterArray on it, it does not 
                //matter whether the properties are already instantiated or not
                //In case of an object that is already an instance of the expected
                //class, that is the object that should be stored
                $model = $this->getModel();
                $expected_class = get_class($model);
                if (is_object($data)){
                    if ($data instanceof $expected_class){
                        $new_data = $data->getValues();
                    } else {                     
                        $new_data = (array) $model->filterObject($data);
                    }
                } elseif (is_array($data)) {
                    $new_data = $model->filterArray($data);
                } else {
                    throw new \Exception('The data parameter for the update cannot be converted to a valid array');
                }
                unset($new_data[$id_name]);
                //If we want to log this update statement
                if ($log_update) {
                    $update = new \Zend\Db\Sql\Update($this->table);
                    $update->set($data);
                    $update->where($where);
                    
                    $platform = $this->tableGateway->getAdapter()->getPlatform();
                    \Application\Shared\SharedStatic::doLogging('DB DEBUG: '.
                        $update->getSqlString($platform));
                }
                //TODO: nicer to pick this up with the db profiler
//                $logging = $log_update && $this->logDbStatement('prepare');
                $this->tableGateway->update($new_data, $where);
                /*if ($log_update && $logging) {
                    \Application\Shared\SharedStatic::doLogging('DB DEBUG: with profiler :'.
                        $this->logDbStatement('retrieve'));
                }*/
                return $id;
            } else {
                throw new \Exception(
                   sprintf($this->translate(
                      'The update %1$s cannot be performed: object did not exist'), 
                       (_DEBUG ? 'with condition '.print_r($where,1) : '')));
            }
        } catch (\Exception $e) {
            throw new \Exception($this->translate('Some database error occured.').
                    (_DEBUG ? 
                        ' Got db error "' . $e->getMessage() . $e->__toString() . 
                            '" Tried saving data:' .print_r($data,1) 
                        : ''));
        }
    }
    
//
//    This does not work yet. Investigate profiler    private function logDbStatement($stage=null){
//        static $profiler = null;
//        if ($stage === 'prepare'){
//            echo " hier 1 ";
//            //Before executing your query
//            $adap = $this->tableGateway->getAdapter();
//            echo " hier 2 ";$profiler = $adap->getDriver()->getProfiler();
//            echo " hier 3 ";$profiler->setEnabled(true);
//            //return $profiler;
//            echo " hier 4 ";
//            return TRUE;
//        } elseif ($stage === 'retrieve'){
//            if (!$profiler){
//                throw new \Exception('profiler of db statements not defined');
//            }
//            echo get_class($profiler);
//            // Execute your any of database query here like select, update, insert
//            //The code below must be after query execution
//            $query  = $profiler->getLastQueryProfile();
//            $params = $query->getQueryParams();
//            $querystr  = $query->getQuery();
// echo get_class($query). '  en '.get_class($params).'  en '.get_class($querystr);
//            foreach ($params as $par) {
//                $querystr = preg_replace('/\\?/', "'" . $par . "'", $querystr, 1);
//            }
//            return $querystr;
//        } else {
//            throw new \Exception('An unknown stage for profiler of db statements.');
//        }
//        
//    }
    
    public function deleteItems($id='', $where='') {
        $where = $where ? (array) $where : array();
        
        if ($id){
            $id_name = $this->id_name;
            $where[$id_name] = $id;
        }
        $delete_where = new \Zend\Db\Sql\Where();
        try {
            foreach ($where as $key => $condition_value){
                if (is_array($condition_value)){
                    $delete_where->in($key, $condition_value);
                } else {
                    $delete_where->equalTo($key, $condition_value);
                }

            }
            $result = $this->tableGateway->delete($delete_where);
        } catch (\Exception $e){
            $result = FALSE;
        }
        return $result;
    }
    
    public function entityExists($id, $type_arg='') {
        $type = ucfirst($type_arg ?: $this->type);
        //We could make a distinction here based on the type whether the type
        //is included in the version V2 of the API.
        $key = "ltbapi\V2\Rest\\$type\\${type}Table";
        
        try {
            $result = (bool) $this->getOtherTable($key)->getItem($id, null, TRUE);
        } catch (\Exception $ex) {
            $result = FALSE;
        }
        return $result;
    }
}