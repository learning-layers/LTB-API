<?php
namespace Application\Service;

use \Application\Shared\SharedStatic;

class SocialSemanticConnector {
    private $get_target = null;
    private $post_target = null;
    private $oid_token = null;
    
    const _DEFAULT_VERSION_DUMMY = 'THE_DEFAULT';
    
     //A result from calling GET /entities/types (getEntityTypes): 29th January 2016
    private $accepted_sss_types = array(
         "entity",
         "coll",
         "disc",
         "qa",
         "chat",
         "discEntry",
         "qaEntry",
         "chatEntry",
         "user",
         "uploadedFile",
         "file",
         "rating",
         "tag",
         "tagFrequ",
         "category",
         "userEvent",
         "learnEp",
         "learnEpTimelineState",
         "learnEpVersion",
         "learnEpCircle",
         "learnEpEntity",
         "evernoteNotebook",
         "evernoteNote",
         "evernoteResource",
         "circle",
         "activity",
         "flag",
         "comment",
         "video",
         "videoAnnotation",
         "message",
         "app",
         "image",
         "appStackLayout",
         "location",
         "placeholder",
         "livingDoc",
         "mail",
     );
    
    public function __construct($config)
    {
        //todo: It seems there is no distinction anymore between get and post method calling with respect to base url
        $this->get_target =  isset($config['get_target']) ? $config['get_target'] : '';
        $this->post_target = isset($config['post_target']) ? $config['post_target'] : '';
        $this->auth = isset($config['auth']) ? $config['auth'] : 'oidc';
        $this->sss_key = isset($config['sss_key']) ? $config['sss_key'] : 'nonsens';
        $this->default_version = isset($config['default_version']) ? $config['default_version'] : self::_DEFAULT_VERSION_DUMMY;
        $this->version = isset($config['version']) ? $config['version'] : $this->default_version;
    }
    
    public function setOidToken($oid_token){
        $this->oid_token = $oid_token;
    }
    
    public function checkEntityType($type){
        if (!in_array($type, $this->accepted_sss_types)){
            throw new \Exception("You cannot ask for an entity type $type".
                " that is not supported by the Social Semantic Server");
        }
    }
    private function getAuthToken(){
        if ($this->auth == 'oidc'){
            return $this->oid_token;
        } else {//It is NoAuth
            return $this->sss_key;
        }
    }
    
    /* this function specifies the way calls should be initiated to the SSS
     * For every entry of the SSS API we use a record is returned comprising of 
     * the following entries:
     *  'GET',//method
        'auth',//service_type
        'auth',//entity_type (in lots of cases equal to service_type)
        $params //other params
     * subargument: either an id or some string e.g.: POST entities/tags/entities 
     * (So: method = POST,resource=entities,operation=tags, params={foruser:'Edwin', labels:['x','y']
     * ,space:'sharedSpace'}, subargument=entities
     */
    /* TIJDELIJK EVEN ERUIT OM MISVERSTANDEN TE VOORKOMEN 
    private function createDefaultParameters($version, $operation, $params=''){
        $params = $params ?: array();
        if ($version == $this->default_version){
            switch ($operation){
                case 'auth':
                    $data = array(//we will get at /auths/auths
                        'GET',//method
                        'auth',//resource
                        'auth',//operation
                        $params //other params
                     );
                break;
                case 'deleteTag':
                    $stack = $params['stack'];
                    if (!$stack) 
                        throw new \Exception("You cannot delete a tag without specifying the entity id");
                    $sss_params = array();
                    if ($params){
                        if (isset($params['space']) && $params['space']) {
                            $sss_params['space'] = $params['space'];
                        }
                        if (isset($params['label']) && $params['label']) {
                            $sss_params['label'] = $params['label'];
                        }
                    }
                    $data = array(
                        'DELETE',
                        'tags',
                        'tags',
                        $sss_params,
                        $stack,
                        '',//get label
                        'entities'//extra level
                     );
                break;
                case 'addTag':
                    if (!isset($params['entity'])|| ! $params['entity']){
                        if (isset($params['stack'])&& $params['stack']) {
                            $params['entity'] = $params['stack'];
                        } else {
                            throw new \Exception("You cannot add a tag without specifying the entity" );
                        }
                    }
                    //Allowed are label, entity, space, creationTime (we leave out the latter)
                    $data = array(
                        'POST',
                        'tags',
                        'tags',
                        array(
                            "label" => $params['label'],//the actual tag
                            "space" => isset($params['space']) ? $params['space'] : 'sharedSpace',
                            "entity" => $params['entity']
                        )
                     );
                break;
                case 'changeTag':
                    if (! $params){
                       throw new \Exception("You cannot modify a tag without specifying the old and new label" ); 
                    }
                    
                    if (isset($params['label']) && $params['label']) {
                        $tag = urlencode($params['label']);
                    } else {
                        throw new \Exception("You cannot modify a tag without specifying the tag label" );
                    }
                    
                    $sss_params = array();
                    
                    if (isset($params['newlabel']) && $params['newlabel']) {
                        $sss_params['newlabel'] = urlencode($params['newlabel']);
                    } else {
                        throw new \Exception("You cannot modify a tag without specifying a new label" );
                    }

                    $data = array(
                        'PUT',
                        'tags',
                        'entities',
                        $sss_params,
                        "label/$tag"

                     );
                break;
                case 'getTags':
                    $get_all_tags = array(
                        'GET',
                        'tags',
                        'tags',
                        null,
                        null,
                        'tags'//get label                            
                    );
                    if ($params){
                        $sss_params = array();
                       // if (isset($params['space']) && $params['space']) $sss_params['space'] = $params['space'];
                        if (isset($params['forUser']) && $params['forUser']) $sss_params['forUser'] = ($params['forUser']);
                        if (isset($params['entities']) && $params['entities']) {
                            $sss_params['entities'] = SharedStatic::getTrimmedList($params['entities']);
                        }
                        if (isset($params['labels']) && $params['labels']) {
                            $sss_params['labels'] = SharedStatic::getTrimmedList($params['labels']);
                        }
                        
                        $data = $sss_params ? array(
                            'POST',
                            'tags',
                            'tags',
                            $sss_params,
                            'filtered',
                            'tags'//get label
                         ) : $get_all_tags;
                    } else {
                        $data = $get_all_tags;
                    }
                break;
                case 'getEntityTypes':
                    $sss_params = array();
                    $data = array(
                        'GET',
                        'entities',
                        'entities',
                        $sss_params,
                        'types' //get label
                     );
                break;
                case 'getEntityById':
                    $sss_params = array();
                    $entity = trim(SharedStatic::altSubValue($params, 'entity'));
                    if (!$entity) {
                       throw new \Exception("An id should be provided");
                    }
                    $params['entities'] = array($entity);
                    //Move on to getEntitiesByIds
                case 'getEntitiesByIds':
                    $sss_params = array();
                    $entities = SharedStatic::getTrimmedList(SharedStatic::altSubValue($params, 'entities'));
                    if (!$entities) {
                       throw new \Exception("A list of comma separated ids should be provided in a param called entities.");
                    }
                    $data = array(
                        'POST',
                        'entities',
                        'entities',
                        $sss_params,
                        implode(",", $entities),
                        'entities',//get label
                        'filtered'//extra label
                     );
                break;
                case 'getEntitiesByTag':
                    $sss_params = array();
                    //TODO: startTime when ready in api ssss
                    if (isset($params['space']) && $params['space']) $sss_params['space'] = $params['space'];
                    if (isset($params['forUser']) && $params['forUser']) $sss_params['forUser'] = ($params['forUser']);
                    if (isset($params['labels']) && $params['labels']) $sss_params['labels'] = 
                        SharedStatic::getTrimmedList($params['labels']);
                        
                    $data = array(
                        'POST',
                        'entities',
                        'tags',
                        $sss_params,
                        'entities',
                        'entities' //get label
                     );
                break;
                case 'addStack': //SSAppStackLayoutCreateRESTAPIV2Par: create a stack [name, description]
                    $possible_params = array('label', 'uuid', 'app', 'description');
                    $approved_params = $this->returnApprovedParams($params, $possible_params);
                    if (isset($params['stack']) && $params['stack'] && ! isset($params['uuid'])){
                        $approved_params['uuid'] = $params['stack'];
                    }
                    $data = array(
                        'POST',
                        'appstacklayouts',
                        'appstacklayouts',
                        $approved_params,
                     );
                break;
                case 'changeStack': //SSAppStackLayoutCreateRESTAPIV2Par: create a stack [name, description]
                    $stack_code = trim((isset($params['stack'])? $params['stack'] : ''));
                    if (!$stack_code) {
                        throw new \Exception("You cannot change a stack without specifying the id");
                    }
                    $possible_params = array('label', 'description');
                    $approved_params = $this->returnApprovedParams($params, $possible_params);
                    $data = array(
                        'PUT',
                        'appstacklayouts',
                        'appstacklayouts',
                        $approved_params,
                        $stack_code
                     );
                break;
                case 'deleteStack':
                    $stack_code = trim((isset($params['stack'])? $params['stack'] : ''));
                    if (!$stack_code) 
                        throw new \Exception("You cannot delete without specifying the stack id (code)");
                    $data = array(
                        'DELETE',
                        'appstacklayouts',
                        'appstacklayouts',
                        null,
                        $stack_code
                     );
                break;
                case 'getStacks':
                   $approved_params = array();//at the moment there are no parameters for this call
                   $data = array(
                       'GET',
                       'appstacklayouts',
                       'appstacklayouts',
                       $approved_params,
                       null,
                       'stacks' //get label
                     ); 
                break;
                case 'getStacksByTag':
                    $params['search'] = $params['tag'];
                    $params['includeDescription'] = FALSE;
                    $params['includeLabel'] = FALSE;
                    $params['includeTags'] = TRUE;
                case 'searchStacks':
                    if (! isset($params['search']) || ! $params['search']){
                        throw new \Exception('A search term is mandatory.');
                    } else {
                        $params['search'] = SharedStatic::getTrimmedList($params['search']);
                        $trimming_done = true;
                    }
                case 'search':
                    if (! isset($params['search']) || ! $params['search']){
                        $params['search'] = '';
                    } else {
                        if (!isset($trimming_done) || !$trimming_done){
                            $params['search'] = SharedStatic::getTrimmedList($params['search']);
                        }
                    }
                    //$approved_params 
                    $search_params = array(
                      // "includeTextualContent" => FALSE,//we search for label and description only
                      //Is meant for contextsensitive search in files and so on. Not used
                      //at the moment "wordsToSearchFor"=>$params['search'],
                       "authorsToSearchFor"=> array(
                       ),
                       "applyGlobalSearchOpBetweenLabelAndDescription" => false,
                       "typesToSearchOnlyFor"=> array(
                            SharedStatic::altSubValue($params, 'entity_type', 'appStackLayout'),
                       ),
                       "minRating" => 0,
                       "maxRating" => 0,
                       "localSearchOp" => SharedStatic::altSubValue($params, 'local', "or"),
                       "globalSearchOp" => SharedStatic::altSubValue($params, 'global', "or"),
                    );
                    if (SharedStatic::altSubValue($params, 'includeLabel', TRUE) && $params['search']) {
                        $search_params["labelsToSearchFor"] = $params['search'];
                    }
                    
                    if (SharedStatic::altSubValue($params, 'includeDescription', TRUE) && $params['search']) {
                        $search_params["descriptionsToSearchFor"] = $params['search'];
                    }
                    
                    if (SharedStatic::altSubValue($params, 'includeTags', FALSE)) {
                        $search_params["tagsToSearchFor"] = $params['tags'];
                    }
                    
                    $data = array(
                        'POST',
                        'search',
                        'search',
                        $search_params,
                        'filtered',//$entity_or_label
                        'entities',//$get_label
                    ); 
                break;
                case 'addApp':
                    //downloads...videos are all SSURI type parameters
                    $possible_params = array(
                        'label', 'descriptionShort', 'descriptionFunctional',
                        'descriptionTechnical', 'descriptionInstall',
                        'downloads', 'downloadIOS', 'downloadAndroid', 'fork',
                        'screenShots', 'videos');
                    $approved_params = $this->returnApprovedParams($params, $possible_params);
                    $data = array(
                        'POST',
                        'apps',
                        'apps',
                        $approved_params
                     );
                break;
                case 'deleteApps':
                    if (! $params['apps']){
                        throw new \Exception('A list of apps (comma-separated ids) is mandatory.');
                    }
                    if (is_array($params['apps'])){
                        $apps = implode(",", array_map(trim, $params['apps']));
                    } else {
                        $apps = $params['apps'];
                    }
                    $apps = str_replace(" ", "", $apps);

                    $data = array(
                        'DELETE',
                        'apps',
                        'apps',
                        $approved_params,
                        $apps
                     );
                break;
                case 'getApps':
                    $data = array(
                        'GET',
                        'apps',
                        'apps',
                        $params,
                     );
                break;
                //We offer a couple of shortcuts to retrieve certain entities
                case 'videos':
                case 'entities':
                    $data = array(
                        'GET',
                        $operation,
                        $operation,
                        null,
                        null,
                        $operation
                    );
                break;
                case 'tags':
                    return $this->createParametersVersion($version, 'getTags', null);
                    break;
                default:
                    throw new Exception('Such an api call to the Social Semantic'.
                        "Server is not registered currently: $operation");
            }
        } else {
            return $this->createParametersVersion($version, $operation, $params);
        }
        return $data;
    }
    */
    
    /* this function specifies the way calls should be initiated to the SSS
     * For every entry of the SSS API we use a record is returned comprising of 
     * the following entries:
     *  'GET',//method
        'rest', //the service type (before this was in lots of cases equal to entity_type)
        'auth',//entity_type also the service_type, but that is now 'rest')
        $params //other params
     * subargument: either an id or some string e.g.: POST entities/tags/entities 
     * (So: method = POST,resource=entities,operation=tags, params={foruser:'Edwin', labels:['x','y']
     * ,space:'sharedSpace'}, subargument=entities
     */
    private function createParametersV3($version, $operation, $params=''){
        $params = $params ?: array();
        $approved_params = array();
        $service_type = 'rest';
        if ($version == 'v3'){
            switch ($operation){
                case 'auth':
                    $data = array(//we will get at /auths/auths
                        'GET',//method
                        $service_type,
                        'auth',//operation
                        $params //other params
                     );
                break;
                case 'deleteTag':
                    if (isset($params['stack']) && $params['stack']) {
                        $stack = $params['stack'];
                    } else {
                        throw new \Exception("You cannot delete a tag without specifying the entity id");
                    }
                    $allowed_scalars = array('space','label', 'circle');
                    $sss_params = $this->returnApprovedParams($params, $allowed_scalars);
                    
                    $data = array(
                        'DELETE',
                        $service_type,
                        'tags',
                        $sss_params,
                        $stack,
                        '',//get label
                        'entities'//extra level
                     );
                break;
                case 'addTag':
                    if (!isset($params['entity'])|| ! $params['entity']){
                        if (isset($params['stack'])&& $params['stack']) {
                            $params['entity'] = $params['stack'];
                        } else {
                            throw new \Exception("You cannot add a tag without specifying the entity" );
                        }
                    }
                    
                    //Allowed are label, entity, space, creationTime (we leave out the latter)
                    $allowed_scalars = array('space','label', 'entity');
                    $defaults = array('sharedSpace');
                    $sss_params = $this->returnApprovedParams($params, $allowed_scalars, null, $defaults);
                    $data = array(
                        'POST',
                        $service_type,
                        'tags',
                        $sss_params
                     );
                break;
                case 'changeTag':
                    //TODO
                    throw new \Exception("Work on this. SSS does not support any longer put of a tag" );
                    
                    if (! $params){
                       throw new \Exception("You cannot modify a tag without specifying the old and new label" ); 
                    }
                    
                    if (isset($params['label']) && $params['label']) {
                        $tag = urlencode($params['label']);
                    } else {
                        throw new \Exception("You cannot modify a tag without specifying the tag label" );
                    }
                    
                    $sss_params = array();
                    
                    if (isset($params['newlabel']) && $params['newlabel']) {
                        $sss_params['newlabel'] = urlencode($params['newlabel']);
                    } else {
                        throw new \Exception("You cannot modify a tag without specifying a new label" );
                    }

                    $data = array(
                        'PUT',
                        $service_type,
                        'entities',
                        $sss_params,
                        "label/$tag"

                     );
                break;
                case 'getTags':
                    $get_all_tags = array(
                        'GET',
                        $service_type,
                        'tags',
                        null,
                        null,
                        'tags'//get label                            
                    );
                    if ($params){
                        $allowed_scalars = array('forUser');
                        $allowed_lists = array('labels', 'entities');
                        //$defaults = array('sharedSpace');
                        $sss_params = $this->returnApprovedParams($params, $allowed_scalars, $allowed_lists);
                        
                        $data = $sss_params ? array(
                            'POST',
                            $service_type,
                            'tags',
                            $sss_params,
                            'filtered',
                            'tags'//get label
                         ) : $get_all_tags;
                    } else {
                        $data = $get_all_tags;
                    }
                break;
                case 'getEntityTypes':
                    $sss_params = array();
                    $data = array(
                        'GET',
                        $service_type,
                        'entities',
                        $sss_params,
                        'types' //get label
                     );
                break;
                case 'getEntityById':
                    $entity = SharedStatic::altSubValue($params, 'entity');
                    if (!$entity) {
                       throw new \Exception("An id should be provided. Pass entity=...");
                    }
                    $params['entities'] = array($entity);
                    //Move on to getEntitiesByIds
                case 'getEntitiesByIds':
                    $sss_params = $this->returnApprovedParams($params, null, array('entities'), null, TRUE);
                    if (!$sss_params['entities']) {
                       throw new \Exception("A list of comma separated ids should be provided in a param called entities.");
                    }

                    $data = array(
                        'POST',
                        $service_type,
                        'entities',
                        null,//no payload will be sent
                        $sss_params['entities'],//will be a comma separated list of ids
                        'entities',//get label
                        'filtered'//extra label
                     );
                break;
                case 'getEntitiesByTag':
                    $sss_params = array();
                    //TODO: startTime when ready in api ssss
//                    if (isset($params['space']) && $params['space']) $sss_params['space'] = $params['space'];
//                    if (isset($params['forUser']) && $params['forUser']) $sss_params['forUser'] = ($params['forUser']);
//                    if (isset($params['labels']) && $params['labels']) $sss_params['labels'] = 
//                        SharedStatic::getTrimmedList($params['labels']);
                    $sss_params = $this->returnApprovedParams($params, array('space', 'forUser'), array('labels'));
                    $data = array(
                        'POST',
                        $service_type,
                        'tags',
                        $sss_params,
                        'entities',
                        'entities', //get label
                        'filtered'//extra level : /tags/filtered/entities
                     );
                break;
                case 'addStack': //SSAppStackLayoutCreateRESTAPIV2Par: create a stack [name, description]
                    $possible_params = array('label', 'uuid', 'app', 'description');
                    $approved_params = $this->returnApprovedParams($params, $possible_params);
                    if (isset($params['stack']) && $params['stack'] && ! isset($approved_params['uuid'])){
                        $approved_params['uuid'] = $params['stack'];
                    }
                    $data = array(
                        'POST',
                        $service_type,
                        'appstacklayouts',
                        $approved_params,
                     );
                break;
                case 'changeStack': //SSAppStackLayoutCreateRESTAPIV2Par: create a stack [name, description]
                    $stack_code = SharedStatic::altSubValue($params, 'stack');
                    if (!$stack_code) {
                        throw new \Exception("You cannot change a stack without specifying the id");
                    }
                    $possible_params = array('label', 'description');
                    $approved_params = $this->returnApprovedParams($params, $possible_params);
                    $data = array(
                        'PUT',
                        $service_type,
                        'appstacklayouts',
                        $approved_params,
                        $stack_code
                     );
                break;
                case 'deleteStack':
                    $stack_code = SharedStatic::altSubValue($params, 'stack');
                    if (!$stack_code)
                        throw new \Exception("You cannot delete without specifying the stack id (code)");
                    $data = array(
                        'DELETE',
                        $service_type,
                        'appstacklayouts',
                        null,
                        $stack_code
                     );
                break;
                case 'getStacks':
                   //at the moment there are no parameters for this call
                   $data = array(
                       'GET',
                       $service_type,
                       'appstacklayouts',
                       $approved_params,
                       null,
                       'stacks' //get label
                     ); 
                break;
                case 'getStacksByTag':
                    $params['search'] = SharedStatic::altSubValue($params, 'tag', NULL);
                    $params['includeDescription'] = FALSE;
                    $params['includeLabel'] = FALSE;
                    $params['includeTags'] = TRUE;
                case 'searchStacks':
                    //The keys we use internal differ now from the ones used by SSS. Maybe we should map these
                    //ones upon a time with a nice function
                    if (! isset($params['search']) || ! $params['search']){
                        throw new \Exception('A search term is mandatory.');
                    } else {
                        $params['search'] = SharedStatic::getTrimmedList($params['search']);
                        $trimming_done = true;
                    }
                case 'search':
                    if (! isset($params['search']) || ! $params['search']){
                        $params['search'] = '';
                    } else {
                        if (!isset($trimming_done) || !$trimming_done){
                            $params['search'] = SharedStatic::getTrimmedList($params['search']);
                        }
                    }
                    //$approved_params 
                    
                    $local_params = $this->returnApprovedParams($params,
                        array('local', 'global', 'includeLabel', 'includeDescription', 'includeTags'),
                        array('search', 'tags', 'entity_types'),
                        array('or', 'or', TRUE, TRUE, FALSE));
                    
                    if ($local_params['entity_types']){
                        $types_to_searchfor = explode(',',$local_params['entity_types']);
                    } else {
                        $types_to_searchfor = array('appStackLayout');
                    }
                    //$approved_params 
                    $search_params = array(
                      // "includeTextualContent" => FALSE,//we search for label and description only
                      //Is meant for contextsensitive search in files and so on. Not used
                      //at the moment "wordsToSearchFor"=>$params['search'],
                       "authorsToSearchFor"=> array(
                       ),
                       "applyGlobalSearchOpBetweenLabelAndDescription" => false,
                       "typesToSearchOnlyFor"=> $types_to_searchfor,
                       "minRating" => 0,
                       "maxRating" => 0,
                       "localSearchOp" => $local_params['local'],
                       "globalSearchOp" => $local_params['global'],
                        "orderByLabel" => TRUE
                    );
                    if ($local_params['includeLabel'] && $local_params['search']) {
                        $search_params["labelsToSearchFor"] = $local_params['search'];
                    }
                    
                    if ($local_params['includeDescription'] && $local_params['search']) {
                        $search_params["descriptionsToSearchFor"] = $local_params['search'];
                    }
                    
                    if ($local_params['includeTags'] && $local_params['tags']) {
                        $search_params["tagsToSearchFor"] = $local_params['tags'];
                    }
                    
                    $data = array(
                        'POST',
                        $service_type,
                        'search',
                        $search_params,
                        'filtered',//$entity_or_label
                        'entities',//$get_label
                    ); 
                break;
                case 'addApp':
                    //downloads...videos are all SSURI type parameters
                    $possible_params = array(
                        'label', 'descriptionShort', 'descriptionFunctional',
                        'descriptionTechnical', 'descriptionInstall',
                        'downloadIOS', 'downloadAndroid', 'fork',
                        );
                    $list_params = array('downloads','screenShots', 'videos');
                    $approved_params = $this->returnApprovedParams($params, $possible_params, $list_params);
                    $data = array(
                        'POST',
                        $service_type,
                        'apps',
                        $approved_params
                     );
                break;
                case 'deleteApps':
                    $approved_params = $this->returnApprovedParams($params, null, array('apps'), TRUE, null, TRUE);
                    
                    if (! $approved_params['apps']){
                        throw new \Exception('A list of apps (comma-separated ids) is mandatory.');
                    }

                    $data = array(
                        'DELETE',
                        $service_type,
                        'apps',
                        $approved_params,
                        $approved_params['apps']
                     );
                break;
                case 'getApps':
                    $data = array(
                        'GET',
                        $service_type,
                        'apps',
                        $params,
                     );
                break;
                //We offer a couple of shortcuts to retrieve certain entities
                case 'getCertainTypeEntities':
                    $operation = $params['type'];
                case 'videos': //this is the old way: approach with direct call to the entity operation
                    $data = array(
                        'GET',
                        $service_type,
                        $operation,
                        null,
                        null,
                        $operation
                    );
                break;
                //just an alias of getTags
                case 'tags':
                    return $this->createParametersVersion($version, 'getTags', null);
                    break;
                default:
                    throw new Exception('Such an api call to the Social Semantic'.
                        "Server is not registered currently: $operation");
            }
            return $data;
        } else {
            return $this->createParametersVersion($version, $operation, $params);
        }
    }
    
    private function returnApprovedParams($given, $allowed_scalars=null, $allowed_lists=null, $defaults=array(), $flatten = FALSE){
        $approved_params = array();
        if ($allowed_scalars){
            foreach ($allowed_scalars as $i => $param_key){
                SharedStatic::getTrimmedArg($approved_params, $given, $param_key, FALSE, 
                    (isset($defaults[$i]) ? $defaults[$i] : FALSE));
            }
        }
        if ($allowed_lists){
            foreach ($allowed_lists as $param_key){
                SharedStatic::getTrimmedArg($approved_params, $given, $param_key, TRUE, null, $flatten);
            }
        }
        return $approved_params;
    }
    
    private function createParametersVersion($version, $operation, $params=''){
        if ($version == $this->default_version){
            return $this->createDefaultParameters($version, $operation, $params);
        } else {
            switch ($version){
                case 'v3': return $this->createParametersV3($version, $operation, $params);break;
                default: throw new \Exception($version.'This version of Social Semantic Server Api is not known');
            }
        }
    }
    
    /*
     * @operation: one of the operations we allow to pass on to the SSS. It is 
     * most likely the same list of allowed operations as their api allows, but we could
     * make a translation for our own wording.
     * @params: a key-value list of parameters suitable for 
     */
    public function callSocialSemanticServer($operation, $params=null, $verbose=false){
        //handling what to post or get, etc...
        //Most calls have the form of METHOD:http://sss_url/service_type/entity_type/entity_id/entity_subtype
        //Before we used the terminology resource operation instead of servicetype/entitytype
        SharedStatic::doLogging("het sss oid token is ".
            $this->oid_token);
        try {
            @list($method, $service_type, $operation, $data, $entity_or_label,
                $get_label, $extra_level) = $this->createParametersVersion($this->version, $operation, $params);
            $service_url = (($method == 'GET') ? $this->get_target : $this->post_target) .
                ($service_type ? "$service_type/": "").
                "$operation".
                ($extra_level? "/$extra_level": "").
                ($entity_or_label ? "/$entity_or_label" : "");
              
            $test_header = TRUE;
            //Will retrieve the actual result instead of the array(op =>...)
            $get_result_only = TRUE;
            $msg = ' In the connector for SSS: ';

            //By using the curl_init this way, we do not need a 
            //curl_setopt($resource, CURLOPT_URL, $service_url);
            $curl = curl_init($service_url);

            //TODO: At the moment the buffering of the verbose information does
            //not seem to work. Have a look at this.
//            if ($verbose){
//                $verbosebuffer = fopen('php://temp', 'rw+');
//                curl_setopt($curl, CURLOPT_STDERR, $verbosebuffer);
//                curl_setopt($curl, CURLOPT_VERBOSE, true);
//                curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE);
//            }
        
            if ($method == 'GET') {
                $length = 0;
            } else {
                //The default method is GET, POST is covered above, all other cases we have to catch
                $data_string = $data ? json_encode($data): "{}";
                if ($method == 'POST'){
                    //It seems this is necessary for operations expecting payload
                    curl_setopt($curl, CURLOPT_POST, true);
                } else {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);  
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
                $length = strlen($data_string);
            }
            
            if ($test_header){
                curl_setopt($curl, CURLOPT_HEADER, 1);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, !_DEVELOP_ENV);//TODO is this really necesary?
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->getAuthToken(),
                'Content-Length: ' . $length)                                                                     
            );

            $curl_response = curl_exec($curl);
          
            $return_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $extra = 'Extra information';
            $extra .= ". Returned code: $return_code";
            $good_result = (in_array($return_code, array(200, 201, 202, 203, 204)));
            if (! $curl_response){
                $msg .= ' CURL Error: (' . curl_errno($curl). '): '.curl_error($curl);
                $result = "";
            } else {
                if ($test_header){
                    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                    $return_header = substr($curl_response, 0, $header_size);
                    $return_body = substr($curl_response, $header_size);
                    $curl_response = $return_body;
                }
                $result = json_decode($curl_response, true);
                if ($get_result_only && $get_label && $good_result){
                    $result = $result[$get_label];
                }
                $extra .= $test_header ? ". RETURN HEADER: $return_header" : "";
                $extra .= ". RESPONSE: $curl_response";
            }
                       
            $curl_info = (_DEBUG ? ' Curl info: '. print_r(curl_getinfo($curl), 1) : '');
            curl_close($curl);

            $msg .= _DEBUG ? (" Requested: $method: $service_url with Sent data:".
                print_r($data, 1).$curl_info)  : "";

            //Returning result now
            if ($good_result){
                return array($result, TRUE, $msg. $extra);
            } else {
                return array(
                    $return_code,
                    FALSE,
                    "SocialSemanticServer response: [".print_r($result, 1).
                        "] with the connector's messages:"."'. $msg ".
                        (_DEBUG ? $extra : ""));
            }
        } catch (\Exception $ex) {
            return array($ex->getCode(), FALSE, "For sss api version ".$this->version." Got: ".
                $ex->getMessage());
        }
    }

}