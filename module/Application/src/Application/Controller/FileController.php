<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Application\Controller;

use Application\Controller\MyAbstractActionController;
use Application\Shared\SharedStatic;

class FileController extends MyAbstractActionController
{
    protected $end_point = 'File'; 
    
    private function sizeFilename($path, $w=FALSE, $h=FALSE){
        $path_parts = explode('.', $path);
        $ext = array_pop($path_parts);
        $path_parts[] = $w . 'x' . $h;
        $path_parts[] = $ext;
        
        //return new path
        //like: path/image.ext => path/image.200x200.ext
        return implode('.', $path_parts);
    }
    
    private function createResize($image_path, $new_width, $new_height = FALSE){
        if (!$new_width) {
            //if $w not set return false
            return $image_path;
        } else if (!$new_height) {
            //if $w like 200x200
            $size_parts = explode('x', $new_width);
            if (count($size_parts) == 2) {
                $new_width = $size_parts[0];
                $new_height = $size_parts[1];
            } else {
                //if $w malformed return false
                return $image_path;
            }
        }

        $new_path = $this->sizeFilename($image_path, $new_width, $new_height);

        if (!$new_path) {
            return $image_path;
        } elseif (file_exists($new_path)) {
            return $new_path;
        }

        $mime = getimagesize($image_path);

        $src_img = false;

        if ($mime['mime'] == 'image/png') {
            $src_img = imagecreatefrompng($image_path);
        }
        if ($mime['mime'] == 'image/jpg') {
            $src_img = imagecreatefromjpeg($image_path);
        }
        if ($mime['mime'] == 'image/jpeg') {
            $src_img = imagecreatefromjpeg($image_path);
        }
        if ($mime['mime'] == 'image/pjpeg') {
            $src_img = imagecreatefromjpeg($image_path);
        }

        if (!$src_img) {
            //mime not supported
            return $image_path;
        }

        $old_x = imageSX($src_img);
        $old_y = imageSY($src_img);

        if ($old_x > $old_y) {
            $thumb_w = $new_width;
            $thumb_h = round($old_y * ($new_height / $old_x));
        }

        if ($old_x < $old_y) {
            $thumb_w = round($old_x * ($new_width / $old_y));
            $thumb_h = $new_height;
        }

        if ($old_x == $old_y) {
            $thumb_w = $new_width;
            $thumb_h = $new_height;
        }

        $dst_img = ImageCreateTrueColor($thumb_w, $thumb_h);

        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);


        // New save location

        if ($mime['mime'] == 'image/png') {
            $result = imagepng($dst_img, $new_path, 8);
        }
        if ($mime['mime'] == 'image/jpg') {
            $result = imagejpeg($dst_img, $new_path, 80);
        }
        if ($mime['mime'] == 'image/jpeg') {
            $result = imagejpeg($dst_img, $new_path, 80);
        }
        if ($mime['mime'] == 'image/pjpeg') {
            $result = imagejpeg($dst_img, $new_path, 80);
        }

        imagedestroy($dst_img);
        imagedestroy($src_img);

        return $new_path;
    }

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
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
//header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
            
            // set appropriate headers for attachment or streamed file
            header("Content-Type: $content_type");
            if ($is_attachment) {
                header("Content-Disposition: attachment; filename=\"$file_name\"");
            } else {
                header('Content-Disposition: inline');
            }

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
                @fclose($file);

            } else {
                // file couldn't be opened
                header("HTTP/1.0 500 Internal Server Error");
                exit;
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
        $query_params = $this->getMyQueryParams();
        $token = SharedStatic::altSubValue($query_params, 'token', _TEST_TOKEN);

        $this->account = $this->getOtherService('Application\Service\Account');
        $is_allowed_in = $this->account->getAuth($this->getEvent(), $soft, $token);
        $user_id = $this->account->getCurrentUserId();
        
        if ($is_allowed_in){
            //perhaps check on the owner here?
            $params = $this->getMyQueryParams();
            //Get parameter from route
            $ref_code = $this->params()->fromRoute('ref_code');
            $file_name = $this->params()->fromRoute('file_name');
            $file_dir = $this->params()->fromRoute('file_dir');
            
            $this->userLog('file', $soft, $user_id, $ref_code, null, TRUE);
            
            if (!$ref_code){
                return $this->returnControllerProblem(400, 'Not enough information to download the file. Need a reference code.');
            }
            $referenceO = $this->getOtherTable('ltbapi\V2\Rest\Reference\ReferenceTable');
            $reference = $referenceO->getItem($ref_code);
            if (! $reference){
                return $this->returnControllerProblem(404, 'File not found.');
        
            }
            $size = SharedStatic::altSubValue($query_params, 'size');
            
            if(!$file_name){
                $file_name = $reference->file_name;
                $file_type = $reference->file_type;
                $get_file_name = $file_name;
                
            }elseif($file_dir === 'image' && $reference->details){
                $details = (object) json_decode($reference->details);
                
                $file_name = $details->image_details->file_name;
                $file_type = $details->image_details->file_type;
                $get_file_name = $file_dir.'/'.$file_name;
            }else{
                return $this->returnControllerProblem(404, 'File not found.');
            }
            
            $location = $referenceO->getFileLocation($reference->file_ref_code, $get_file_name );
            if ($size){
                $location = $this->createResize($location, $size);
            }
            return $this->fileStream($location, $file_name, $file_type);
        
        } else {
            return $this->returnControllerProblem(401, 'You cannot get the file contents if you are not logged in');
        }
    }
}
