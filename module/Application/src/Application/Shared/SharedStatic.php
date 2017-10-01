<?php
namespace Application\Shared;

use ZF\ApiProblem\ApiProblem;
/* 
 * The code below is written by Edwin Veenendaal in 2006 under the umbrella of Raycom
 * It may be used under the Creative Commons License cc-by-sa
 */
class SharedStatic {
    const _SHORTCODE_LENGTH_MIN = 5;
    const _BASE_10 = '0123456789';
    const _BASE_26 = '0123456789ABCDEDFGHIJKLMNO';
    
    static public function first($arr, $alt=array()){
        return $arr ? $arr[0] : $alt;
    }
    
    static public function altValue($val, $alt=''){
		if (isset($val)) return $val;
		else return $alt;
	}
	
    static public function altSubValue($struct, $key, $alt=''){
            if (isset($struct[$key])) return $struct[$key];
            else return $alt;
    }

    static public function altSubSubValue($struct, $key1, $key2, $alt=''){
            if (isset($struct[$key1]) && isset($struct[$key1][$key2])) return $struct[$key1][$key2];
            else return $alt;
    }

    static public function altProperty($obj, $prop, $alt=''){
        if (!empty($obj) && isset($obj->$prop)) return $obj->$prop;
        else return $alt;
    }
    
    static public function checkSubset($subset, $superset){
        return count(array_diff($subset, $superset)) === 0;
    }
    
    static public function returnApprovedParams($given, $allowed_scalars=null, $allowed_lists=null,
        $defaults=array(), $flatten=FALSE){
        
        $approved_params = array();
        if ($allowed_scalars){
            foreach ($allowed_scalars as $i => $param_key){
                self::getTrimmedArg($approved_params, $given, $param_key, FALSE, 
                    (isset($defaults[$i]) ? $defaults[$i] : FALSE));
            }
        }
        if ($allowed_lists){
            foreach ($allowed_lists as $param_key){
                self::getTrimmedArg($approved_params, $given, $param_key, TRUE, null, $flatten);
            }
        }
        return $approved_params;
    }
    
    static function getTrimmedList($list, $flatten=FALSE){
        $list2 = is_string($list) ? explode(',', $list): $list;
        $new = array_filter(array_map('trim', $list2));
        return $flatten ? implode(',',$new) : $new;
    }
    
    /* For convenience we store the argument in a variable result list
     */
    static function getTrimmedArg(&$result_list, $arg_list, $key, $is_sublist=FALSE,
        $default=FALSE, $flatten=FALSE){
        if (!$result_list){
            $result_list = array();
        }
        if (isset($arg_list[$key])) {
            if ($arg_list[$key]){
                $result_list[$key] = ($is_sublist ?
                    self::getTrimmedList($arg_list[$key]) :
                    trim($arg_list[$key]));
            } else {
                $result_list[$key] = $is_sublist ? ($flatten ? '': array()) : '';
            }
            
        } elseif ($default !== FALSE) {
            $result_list[$key] = $default;
        } else {
            $result_list[$key] = null;
        }
        
        return $result_list[$key];
    }
    
    static public function rrmdir($dir) { 
        if (is_dir($dir)) {
            $objects = scandir($dir, SCANDIR_SORT_NONE);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)){
                        self::rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    static public function getDbError($ex_str){
        if(strstr($ex_str, 'SQLSTATE[')) {
            //E.g.: SQLSTATE[23000]: Integrity Constraint violation: 1169 Can't write, because of unique constraint, to table... 
            $matches = NULL;
            preg_match('/.*SQLSTATE\[(\w+)\]: (.*?): ([\d]*)(.*)/', $ex_str, $matches);
            if ($matches){
                $code = $matches[3]; 
                $message = $matches[2]. ": ".$matches[4];
                return array($code, $message);
            }
        }
        return array(0, '');
    }
     
    static public function userLogStore($tbl_gateway, $end_point, $method, $soft,
        $user_id, $id=0, $params=NULL, $granted=TRUE){
        $record = array(
            'endpoint' => $end_point,
            'method' => $method,
            'soft' => $soft,
            'granted' => $granted,
            'userid' => $user_id,
            'id' => $id,
            'timestamp' => time()
        );
        if ($method === 'fetchAll') {
            $parameters = is_object($params) ? $params->getArrayCopy() : array();
            switch ($end_point){
                case 'Stack':
                    $record['search_name'] = isset($parameters['name']) ? $parameters['name'] : '';
                    $record['search_tags'] = isset($parameters['tags']) ? $parameters['tags'] : '';
                    $record['search_terms'] = isset($parameters['terms']) ? $parameters['terms'] : '';
                    $record['search_author'] = isset($parameters['author']) ? $parameters['author'] : '';
                    break;
                case 'Reference':
                    $record['stack_code'] = isset($parameters['entity_code']) ? $parameters['entity_code'] : '';
                    break;
            }
        }
        
        if ($method === 'create'){
//            $parameters = is_object($params) ? $params->getArrayCopy() : array();
            switch ($end_point){
                case 'Reference':
                    $record['stack_code'] = isset($params->entity_code) ? $params->entity_code : '';
                    break;
                case 'Favourite':
                    $record['stack_code'] = isset($params->entity_code) ? $params->entity_code : '';
                    break;
            }
        }
        
        //Put record in database
        $tbl_gateway->insert($record);
    }
    
    static public function doLogging ($m, $val=' (no value passed to log).', $log_file='', $log=null){
        if (!$log){
            $log = $GLOBALS['my_service_manager']->get('Application\Service\Logging');
        }
        if (!$log){
            throw new \Exception('Could not instantiate logging function');
        }
        $log->lwrite($m. print_r($val, 1), $log_file);
        // close log file
        $log->lclose();
    }
   
    static public function debugLog ($m, $val=' (no value passed to log).'){
        $log = $GLOBALS['my_service_manager']->get('Application\Service\Logging');
        if (!$log){
            throw new \Exception('Could not instantiate logging function');
        }
        $file_path = $log->getPath()."_debug.log";
        self::doLogging("DEBUG: $m", $val, $file_path, $log);
    }
    
    static public function increaseVersion($version){
        $vers_arr = explode(".", "$version");
        $new = 1 + array_pop($vers_arr);
        $vers_arr[] = $new;
        return implode('.', $vers_arr);
    }
    /**
     *  
     * @param int $status Some http status
     * @param string $detail Detailed description of the problem. Can also be 
     * derived from the Exception when detail is some Exception object
     * @param string $title Some title for the problem
     * @param array $params the parameters passed to the api
     * @param string $type: Leave null, by default null and will be set to http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     * @return ApiProblem
     */
    static public function returnApiProblem($status=500, $detail='', $title='', $params=null, $type=null){
        if ($detail instanceof \Exception){
            if (!$title) {
                $title = $detail->getMessage();
            }
            $title = "Some exception occurred. $title";
            if (_DEBUG){
                $detail = $detail->__toString();
            } else {
                self::doLogging("$title:\n".$detail);
                $detail = '';
            }
        } elseif ($detail && ($title == '')){
            $title = "Some error occurred ($status): see details for further information";
        }
        $param_array = ($params ? (array) $params: array());
        return new ApiProblem($status, $detail, $type, $title, $param_array);
    }
    
    static public function convertDateToTimestamp($date, $format="d-m-Y"){
       if (is_numeric($date)) {
            return $date;
        } else {
            $dateO = date_create_from_format($format, $date);
            $dateO->setTime(0,0,0);//Set the date to midnight
            return $dateO->getTimestamp();
        }
    }
    
    static public function getID($short){
        if (is_numeric($short)) return (int) $short;
        return self::my_reconvert($short);
    }
	
    static public function getShortCode($id){
		return (is_numeric($id)) ? self::my_convert($id) : $id;
	}
    
	static public function my_convert($int, $wishedlen = self::_SHORTCODE_LENGTH_MIN){
        if (! $int){
            return '';
        }
		$map = array(
				'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',//  10
				'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',// 20
				'U', 'V', 'W', 'X', 'Y', 'Z');// 26
			
		$map2 = array_merge(array(
				'0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $map);
		//The max value base_convert can handle is  2147483647 (2.1 * 10^9). Above this int, the max value is always returned
		//TODO: split the int in two parts, calculate both parts and glue them. Question is, do we want to be able to cope with
		//these magnificent numbers? Would the database not give overflow problems before?
		$start = str_split(strtoupper(base_convert($int, 10, 26)));
		$string = '';
		foreach($start as $c){
			$pos = array_search($c, $map2);
			$string .= $map2[$pos + 10];
		}
	
		$len = count($start);
		if ($len < $wishedlen){
			$pad = $wishedlen - $len - 1;//Leave one position as pad number indicator
			if ($pad > 0){
				for ($i=1; $i <= $pad;$i++){
					$string = $map[rand(0,25)] . $string;
				}
			}
			$string = $map[max($pad, 0)]. "$string";
		} elseif ($len == $wishedlen){
			$string = "A$string";
		}
	
		$forbidden_words = array();
                $max_rounds = 3;
                $round = 1;
		while ($round <= $max_rounds && in_array($string, $forbidden_words)){
                        //The string consists of A-Z only now. We rotate a random number and assume it is ok
                        //after a couple of rounds. We have no garanty, so we just give up after 3 rounds.
			$round++;
            $shift = rand(1,10);
			$shift_indicator = $map[$shift];
			$result_arr = str_split($string);
			$return = array_map(
					create_function('$c', '$new = ord($c)+ '.$shift.
							'; return $new > 90 ? chr(65 + ($new - 91)) : chr($new);'), $result_arr);
			array_unshift($return, $shift_indicator);
			array_unshift($return, chr(95));
			$string = implode("", $return);
		}
		return $string;
	}
	
	static public function my_reconvert($code, $wishedlen = self::_SHORTCODE_LENGTH_MIN){
		$map = array(
				'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', // 0-7
				'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 8-15
				'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 16-23
				'Y', 'Z');
		$map2 = array_merge(array(
				'0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $map);
		$chars = str_split($code);
	
		//unshift for forbidden words indicator ord('_') == 95
		if (ord($chars[0]) == 95){//
			array_shift($chars);
			$shift = array_shift($chars);
			$shift = ord($shift)-65; //Since ord(A) = 65, A means shift = 0
            //If the shift is below the A, shift further down, but then from the Z down
			$chars = array_map(
					create_function('$c', '$new = ord($c) - '.$shift.
						'; return $new < 65 ? chr(91 - (65 - $new)) : chr($new);'), $chars);
		}
		$pad = 0;
		if (strlen($code) == $wishedlen){
			//padding to remove: B=1, C=2... + indicator char itself
			$pad = ((ord($chars[0]) - 65) + 1) ;
		} elseif ($chars[0] == 'A'){
			//Input can also be longer than the wished length. This has to be
            //indicated: zero-length padding: A==0, remove indicator (char A)
			$pad = 1;
		}
	
		if ($pad > 0){
            //throw away $pad lenght chars from the front
			array_splice($chars, 0, $pad);
		}
		$string = '';
		foreach($chars as $c){
			$pos = array_search($c, $map2);
			
			if ($pos == FALSE){
				return 0;
			} elseif ($pos < 10) {
				throw new \Exception("Was looking for char $c but this resulted in undefined index $pos");
			} else {
                //The space with 26 base runs from 0..9,A-O. We added 10 during convert
                //to avoid numbers in the code, now we roll this back
				$string .= $map2[$pos - 10];
			}
		}
		
        //Intval does a conversion just like base_convert if a base is given
        //Intval gives overflow somewhere between 4OOOOOO (4 with 6 chars O -not zeros)
        //and 6OOOOOO -reaching the int max of 2^31 (= 2147483648), so if we 
        //encounter a cod with length less than 7, we play safe, 
        //otherwise we better use the less overflow prone convBase that I wrote.
        //In most cases 6 chars will be enough since it covers more than 2.1 miljard 
        //unique entries
        if (strlen($string) < 7){
            return intval(strtolower($string), 26);
        } else {
            return self::convBase($string, self::_BASE_26, self::_BASE_10);
        }
	}
	
	static public function convBase($numberInput, $fromBaseInput, $toBaseInput)
	{
		if ($fromBaseInput==$toBaseInput) return $numberInput;
		$fromBase = str_split($fromBaseInput,1);
		$toBase = str_split($toBaseInput,1);
		$number = str_split($numberInput,1);
		$fromLen=strlen($fromBaseInput);
		$toLen=strlen($toBaseInput);
		$numberLen=strlen($numberInput);
		$retval='';
		if ($toBaseInput == self::_BASE_10)
		{
			$retval=0;
			for ($i = 1;$i <= $numberLen; $i++)
				$retval = bcadd($retval,
                    bcmul(
                        array_search($number[$i-1], $fromBase),
                        bcpow($fromLen,$numberLen-$i)));
			return $retval;
		}
		if ($fromBaseInput != self::_BASE_10)
			$base10=convBase($numberInput, $fromBaseInput, self::_BASE_10);
		else
			$base10 = $numberInput;
		if ($base10<$toLen)
			return $toBase[$base10];
		while($base10 != '0')
		{
			$retval = $toBase[bcmod($base10,$toLen)].$retval;
			$base10 = bcdiv($base10,$toLen,0);
		}
		return $retval;
	}
	
    /*This function creates an arbitrary code to be inserted into the database. The 
     * string is based on the microtime so that if two actions ask for a unique code,it
     * is very unlikely the request is at the same time. Moreover we add a random number to
     * it, to make the chance of a collision even more unlikely.
     * 
     * @return am uppercase 
     */
	static public function makeShortCode(){
	
		list($usec, $sec) = explode(" ", microtime());
		//By applying an arithmetic operation (necessary anyway), a conversion to 
        //int is established,
		//but strrev just converts back to string automatically
		$usec = strrev($usec * 1000000);//get rid of the precision dot
		$sec = strrev($sec - strtotime('2015-01-01'));//prevent overflow by subtracting a common lowerbound number
		$usec = str_pad($usec, 6, '0', STR_PAD_LEFT);
		$str = (int) rand(10,99).$usec.$sec;
		$code = base_convert($str, 10, 36);
		return strtoupper($code);
	}
	
	static public function sessionDestroy($name=''){
		if ($name){
			$sessionManager = new SessionManager();
			$array_of_sessions = $sessionManager->getStorage();
			unset($array_of_sessions[$name]);
			/*Is the same as:
			 * 	 $session_user->getManager()->getStorage()->clear('user'); OR
			* 	 unset($_SESSION['user']);
			* */
		} else {//destroy all
			$session = new Container('base');
			$session->getManager()->destroy();
			/* Or equivalently:
			 * session_destroy();
			*/
				
		}
	}
	
    static public function translateArray($arr, $implode=FALSE){
        $msgs = array();
        if ($arr) {
            foreach ($arr as $m) {
                $msgs[] = self::translate($m);
            }
        }
        return ($implode === FALSE) ? $msgs : implode($implode, $msgs);
    }
    
    static public function translate($str){
        // TODO : This is to be implemented
        return $str;
    }
}