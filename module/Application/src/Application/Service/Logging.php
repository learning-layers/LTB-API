<?php
namespace Application\Service;
/**
 * Logging class:
 * - contains lfile, lwrite and lclose public methods
 * - lfile sets path and name of log file
 * - lwrite writes message to the log file (and implicitly opens log file)
 * - lclose closes log file
 * - first call of lwrite method will open log file implicitly
 * - message is written with the following format: [d/M/Y:H:i:s] (script name) message
 */
class Logging {
    // Seclare log file and file pointer as private properties
    private $log_file, $fp;
    
    // Retrieve log file (path and name) depending on the OS
    public function getPath() {
        if (!$this->log_file){
            // in case of Windows set default log file
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->log_file = _WIN_LOG;
            } else {
                $this->log_file = _UNIX_LOG;
            }
        }
        
        return $this->log_file;
    }
    
    public function setPath($path) {
        $this->log_file = $path;
    }
    
    // write message to the log file
    public function lwrite($message, $logfile = '') {
        if (!is_resource($this->fp)) {
            $file_path = $this->getLogFile($logfile);
            //Make sure that if the file did not exist yet, it is created with
            //group write permissions. This is not longer necessary as we store
            //logdata outside the git root where no chown command is issued
            if (!file_exists($file_path)){
                if (!$this->lopen('a', $file_path)) {
                    return false;
                } else {
                    $this->lclose();
                    chmod($file_path, 0660);
                }
            }
            if (!$this->lopen('a', $file_path)) {
                return false;
            }
        }
        if ($this->fp) {
            // define current time and suppress E_WARNING if using the system TZ settings
            // (don't forget to set the INI setting date.timezone)
            $time = @date('[d/M/Y:H:i:s]');
            $fstats = fstat($this->fp); 
            if ($fstats['size'] > _MAX_LOG_FILE){
                //There is reason to truncate the file
                $this->truncate(_MIN_KEEP_LOG_FILE);             
            }
            // write current time, script name and message to the log file
            fwrite($this->fp, "At $time: $message" . PHP_EOL);
            return true;
        } else {
            return false;
        }
    }
    
    public function truncate($size=_MIN_KEEP_LOG_FILE){
        $this->lclose();
        if ($this->lopen('r+')){
            fseek($this->fp, - $size, SEEK_END);
            $keep = fread($this->fp, $size);
            rewind($this->fp);
            ftruncate($this->fp, 0);
            fwrite($this->fp, "Truncated from here:\n$keep\nTruncated log up to last ".
                "$size bytes\n", $size + 100);  
        }
        $this->lclose();
        $this->lopen('a');
    }
    
    // close log file (it's always a good idea to close a file when you're done with it)
    public function lclose() {
        fclose($this->fp);
    }
    
    private function getLogFile($logfile = ''){
        // Use log file from parameters of from getPath method
        return $logfile ?: $this->getPath();
    }
    
    // open log file
    private function lopen($mode='a', $logfile = '') {
        $lfile = $this->getLogFile($logfile);
        // open log file for writing only and place file pointer at the end of the file
        // (if the file does not exist, try to create it)
        $this->fp = fopen($lfile, $mode) ;
        if (!$this->fp) {
            return false;
        }
        return true;
    }
}
