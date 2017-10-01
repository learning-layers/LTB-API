<?php
namespace ltbapi\V2\Rpc\File;

use Application\Controller\MyAbstractActionController;
use Application\Shared\SharedStatic;

class FileController extends MyAbstractActionController
{
 protected $end_point = 'File';
 
 
 
 
 
   /////////////////////// THIS FILE IS DEPRECATED //////////////////////////////
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
    
    private function fileStream($file_path, $file_name, $content_type){
      
      $get_params = $this->getMyQueryParams();
      $is_attachment = SharedStatic::altSubValue($get_params, 'stream', 0) ? false : true;
      
        if (is_file($file_path)) {
            $file_size = filesize($file_path);
            
                
            // hide notices
            @ini_set('error_reporting', E_ALL & ~ E_NOTICE);

            //- turn off compression on the server
            @apache_setenv('no-gzip', 1);
            @ini_set('zlib.output_compression', 'Off');


            // set the headers, prevent caching
            header("Pragma: public");
            header("Expires: 0"); //header("Expires: -1");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");//header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
            header_remove('Cache-Control');
            header_remove('Pragma');
            
            // set appropriate headers for attachment or streamed file
            if (FALSE && $is_attachment) {
               
                // set the mime type based on extension, add yours if needed.
//                header("Content-Type: " . $content_type);
                //NEW
                //header("Content-Description: File Transfer");
                //header("Content-Transfer-Encoding: binary");
                set_time_limit(0);
//                $parts = explode('.', $file_name);
//                $ext_upper =  strtoupper(array_pop($parts));
//                array_push($parts, $ext_upper);
//                $file_name_mobile = implode('.', $parts);
                
                header("Content-Disposition: attachment; filename=\"$file_name\"");
                header("Content-Length: $file_size");
                header("Content-Type: $content_type");
//                readfile($filePath);
//                exit;
//                header("Content-Type: application/octet-stream");
//                @ob_clean();
//                @flush();
                $file = @fopen($file_path, "rb");
                if ($file) {
                    fpassthru($file);
                    fclose($file);
                    exit;
                } else {
                    return $this->returnControllerProblem(500, 'File could not be opened.');
                }
            } else {
                header("Content-Type: $content_type");
                if($is_attachment){
                    header("Content-Disposition: attachment; filename=\"$file_name\"");
                }else{
//                    header("Content-Disposition: attachment; filename=\"$file_name\"");
                    header('Content-Disposition: inline');
                }
//                header("Content-Description: File Transfer");
//                header("Content-Transfer-Encoding: binary");
                
                //header("Content-Type: application/octet-stream");
                $file = @fopen($file_path, "rb");
                if ($file) {
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
                    $seek_start = (count($parts) > 0)?$parts[0]:null;
                    $seek_end = (count($parts) > 1)?$parts[1]:null;

                    @ob_clean();
                    //set start and end based on range (if set), else set defaults
                    //also check for invalid ranges.
                    $seek_end = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)), ($file_size - 1));
                    $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

                    //Only send partial content header if downloading a piece of the file (IE workaround)
                    if ($seek_start > 0 || $seek_end < ($file_size - 1)) {
                        $length = $seek_end - $seek_start + 1;
                        header('HTTP/1.1 206 Partial Content');
                        header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size);    
                    } else {
                        $length = $file_size;
                    }
                    header("Content-Length: $length");                
                    header('Accept-Ranges: bytes');
                    
                    $chunk_length = 1024 * 8;
                    set_time_limit(0);
                    fseek($file, $seek_start);

                    while (!feof($file) && ($length > 0)) {
                        print(@fread($file, min($chunk_length, $length)));
                        $length -= $chunk_length;
                        @ob_flush();
                        flush();
                        if (connection_status() != 0) {
                            @fclose($file);
                            exit;
                        }
                    }

                    // file save was a success
                    @fclose($file);

                } else {
                    // file couldn't be opened
                    header("HTTP/1.0 500 Internal Server Error");
                    exit;
                }
            }
            exit;
            
        } else {
            // file does not exist
            header("HTTP/1.0 404 Not Found");
            exit;
        }
    }

    public function fileAction()
    {
        $soft = TRUE || _SWITCH_OFF_AUTH_CHECK;
        $token = SharedStatic::altSubValue($this->getMyQueryParams(), 'token');
        $is_allowed_in = $this->account->getAuth($this->getEvent(), $soft, $token);
        $user_id = $this->account->getCurrentUserId();
        
        
        if ($is_allowed_in){
            //perhaps check on the owner here?
            $params = $this->getMyQueryParams();
            //Get parameter from route
            $file_code = $this->params()->fromRoute('ref_code');
            $this->userLog('file', $soft, $user_id, $file_code);
            
            if (!$file_code){
                return $this->returnControllerProblem(400, 'Not enough information to download the file. Need a reference code.');
            }
            $referenceO = $this->getOtherTable('ltbapi\V2\Rest\Reference\ReferenceTable');
            $reference = $referenceO->getItem($file_code);
            if (! $reference){
                return $this->returnControllerProblem(404, 'File not found.');
        
            }
            $location = $referenceO->getFileLocation($reference->file_ref_code, $reference->file_name );
            
            return $this->fileStream($location, $reference->file_name, $reference->file_type, false);
            $f= 'unknown'; $l=1;
            if ($sent = headers_sent($f, $l)) {
                echo "Headers were already sent in a file $f at line $l.";
            } else {
                header("Pragma: public");
                header("Expires: 0");
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);
                header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
               

                //Apparently needed for pdf file that is sent in big chunks of data
                //See issue at github: https://github.com/mozilla/pdf.js/issues/3150#issuecomment-17582371
//                header('Access-Control-Allow-Headers: Range');
//                header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');
//                
//
                header("Content-Length: ". $reference->file_size);//filesize($location));//
                header("Content-Type: ".  $reference->file_type);//mime_content_type($location));//
                header("Content-Disposition: attachment; filename=". $reference->file_name);
//TEST1                
//echo $this->readfile_chunked($location); //Used to be file_get_contents
 //TEST3               set_time_limit(0);
  //TEST2                ob_end_clean();//prbeersel
//                $file = @fopen($location,"rb");
//                while(!feof($file))
//                {
//                    print(@fread($file, 1024*8));
//                    //ob_flush();
//                    flush();
//                }
//                exit;
                
                //Tot hier
  //TEST4               
//                $fp = fopen($location, 'rb');
//                
//                fpassthru($fp);
//                exit;
                
              //TEST5
              echo file_get_contents($location);
              exit;
            }
        } else {
            return $this->returnControllerProblem(401, 'You cannot get the file contents if you are not logged in');
        }
    }
}
