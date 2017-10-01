<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Application\Controller;

//We choose for an actionController and not a resourceController since we never return 
//collections but simply a csv file for the get action. Other http-methods are not allowed
//since changing a log makes no sense neither does posting a new one.
use Application\Controller\MyAbstractActionController;
use Application\Shared\SharedStatic;

class LogController extends MyAbstractActionController
{
    protected $end_point = 'Log';
    private $log_columns = 'endpoint,method,soft,granted,userid,id,timestamp,search_name,search_tags,search_terms,stack_code';
    private function outputStream($output, $file_name='log.csv', $content_type='text/csv'){
      
        $get_params = $this->getMyQueryParams();
        $is_attachment = SharedStatic::altSubValue($get_params, 'stream', 0) ? false : true;
        if ($output) {
            $output_size = strlen($output);
            
            // hide notices
            @ini_set('error_reporting', E_ALL & ~ E_NOTICE);

            //- turn off compression on the server
            //@apache_setenv('no-gzip', 1);
            //@ini_set('zlib.output_compression', 'Off');

            // set the headers, prevent caching
            header("Pragma: public");
            header("Expires: 0"); //header("Expires: -1");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            
            // set appropriate headers for attachment or streamed file
            
            if ($is_attachment) {
                header("Content-Type: $content_type");
                header("Content-Disposition: attachment; filename=\"$file_name\"");
            } else {
                //Causes the browser to treat it as an attachment 
                //header("Content-Type: $content_type");
                header('Content-Disposition: inline');
            }
            
            //check if http_range is sent by browser (or download manager)
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if ($size_unit == 'bytes') {
                    //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                    //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                    list($range, $extra_ranges) = explode(',', $range_orig, 2);
                } else {
                    $range = '';
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    exit;
                }
            } else {
                $range = '';
            }

            //figure out download piece from range (if set)
            $parts = explode('-', $range, 2);
            $seek_start = (count($parts) > 0) ? $parts[0] : null;
            $seek_end   = (count($parts) > 1) ? $parts[1] : null;

            @ob_clean();
            //set start and end based on range (if set), else set defaults
            //also check for invalid ranges.
            $seek_end = (empty($seek_end)) ? ($output_size - 1) : min(abs(intval($seek_end)), ($output_size - 1));
            $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

            //Only send partial content header if downloading a piece of the file (IE workaround)
            if ($seek_start > 0 || $seek_end < ($output_size - 1)) {
                $length = $seek_end - $seek_start + 1;
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $output_size);    
            } else {
                $length = $output_size;
            }
            
            header("Content-Length: $length");                
            header('Accept-Ranges: bytes');

            $chunk_length = 1024 * 8;
            set_time_limit(0);
            while ($seek_start <= $seek_end) {
                if (($seek_start + $chunk_length) <= $seek_end) {
                    //send another chunk
                    print(substr($output, $seek_start, $chunk_length));
                } else {
                    //send the rest
                    print(substr($output, $seek_start));
                }
                $seek_start += $chunk_length;
                //@ob_flush();
                flush();
                if (connection_status() != 0) {
                    exit;
                }
            }
        } else {
            // output was empty or failed somehow
            header("HTTP/1.0 500 Log file could not be created");
            exit;
        }
    }
    
    /* 
     * deprecated function since we output the csv directly.
     * TODO: test whether saving the csv to file and sending the file is faster.
     */
//    private function deleteLogTemps($location){
//        foreach(glob($location) as $f) {
//            unlink($f);
//        }
//    }
    
    public function getAction(){
        $soft = FALSE || _SWITCH_OFF_AUTH_CHECK;
        $token = SharedStatic::altSubValue($this->getMyQueryParams(), 'token', _TEST_TOKEN);
        SharedStatic::debugLog("Wat is het token in get Log", $token);
        $this->account = $this->getOtherService('Application\Service\Account');
        SharedStatic::debugLog("Wat is het type van account in get Log", gettype($this->account));
        $authenticated = $this->account->getAuth($this->getEvent(), $soft, $token);
        SharedStatic::debugLog("Wat is authenticated", $authenticated ? 'ja': 'nein');
        $user_id = $this->account->getCurrentUserId();
        SharedStatic::debugLog("Wat is user id [$user_id] en moderator:?", $this->account->isEvaluator() ? 'ja': 'nein');
        $is_allowed_in = ($authenticated && ($user_id !== FALSE) && $this->account->isEvaluator());
        if ($is_allowed_in){
            //perhaps check on the owner here?
            $params = $this->getMyQueryParams();
            //Get parameter from route
            $start = SharedStatic::altSubValue($params, 'start');
            $end = SharedStatic::altSubValue($params, 'end');
            if ($start){
                $start_ts = SharedStatic::convertDateToTimestamp($start, 'Y-m-d');                
            }
            if ($end){
                $end_ts = SharedStatic::convertDateToTimestamp($end, 'Y-m-d');
            }
            $conditions = array();
            if ($start_ts){
                $conditions[] = new \Zend\Db\Sql\Predicate\Operator('timestamp', 
                    \Zend\Db\Sql\Predicate\Operator::OPERATOR_GREATER_THAN_OR_EQUAL_TO, $start_ts);
            }
            if ($end_ts){
                $conditions[] = new \Zend\Db\Sql\Predicate\Operator('timestamp', 
                    \Zend\Db\Sql\Predicate\Operator::OPERATOR_LESS_THAN_OR_EQUAL_TO, $end_ts);
            }               
            $where = $conditions ? new \Zend\Db\Sql\Where($conditions) : null;
            $this->userLog('get', $soft, $user_id, null, $params, TRUE);
            $logsObj = $this->getOtherTable('user_log');
            $logs = $logsObj->select($where);
            $output = $this->createCsv($logs);
            return $this->outputStream($output);
        } else {
            return $this->returnControllerProblem(401, 'You cannot get the file contents if you are not logged in as an evaluator or admin.');
        }
    }
    /*
     * We do no longer use getLogColumns It seemed that calculating the columns in the user_log table was extremely slow due to 
     * the ZEND Metadata object. Just put the columns in a class property, saves 8 seconds!!!
     */
//    private function getLogColumns() {
//        $adapter = $this->getOtherService('Zend\Db\Adapter\Adapter');
//        $metadata = new \Zend\Db\Metadata\Metadata($adapter);
//        $table = $metadata->getTable('user_log');//!! Performance issue: this step cossts 8 seconds on average
//        $columns = $table->getColumns();
//        $get_names = function(\Zend\Db\Metadata\Object\ColumnObject $c) {return $c->getName();};
//        $column_names = array_map($get_names, $columns);
//        return implode(',', $column_names);
//    }
    
    private function createCsv($logs){
        $column_line = $this->log_columns;//$this->getLogColumns();
        if ($logs){
            $out = $column_line;
            foreach($logs->toArray() as $log){
                $out .= "\r\n". implode(',', $log);
            }
            return $out;
        } else {
            return $column_line;
        }
    }
}
