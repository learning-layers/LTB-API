<?php
namespace ltbapi\V2\Rpc\Auth;

class OIDC {
    
    private $message = '';
    
    public function __construct($open_id_config){
        /*
         * The settings are copied from the local settings. The redirect uri
         * will be accessed from the AuthController but should be here for completeness.
         */
        $this->redirect_uri = $open_id_config['RedirectUri'];
        $this->client_id = $open_id_config['ClientID'];
        $this->client_secret = $open_id_config['ClientSecret'];
        $this->provider = $open_id_config['Provider'];
        $this->OidcEndpointToken = $open_id_config['OidcEndpointToken'];
        $this->OidcEndpointUserinfo = $open_id_config['OidcEndpointUserinfo'];
        $this->web_agent_uri = $open_id_config['WebAgent'];
        $this->app_agent_uri = $open_id_config['AppAgent'];
    }
    
    public function getMessage(){
        return $this->message;
    }
    
    private function addMessage($m){
        $this->message .= $m;
    }
    
    public function connectOIDC($token_endpoint_key, $auth_mode, $data='', $token='', $verbose=false){
        //$info_endpoint = $this->provider."/.well-known/openid-configuration";
        $token_endpoint = $this->$token_endpoint_key;
        if ($auth_mode == 'BASIC'){
            $data['client_id'] = $this->client_id;
            $data['client_secret'] = $this->client_secret;
        }
        $postdata = '';
        if ($data){
            foreach ($data as $k => $v){
                $postdata .= $k."=".$v."&";
            }
            $postdata = rtrim($postdata, "&");
        }
        try {
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $token_endpoint);
            curl_setopt($ch,CURLOPT_POST, count($data));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $postdata);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

            if ($auth_mode == 'BASIC'){
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $token));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            //For local installations the CA certificate cannot be verified, so we have to
            //tell curl not to check
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !_DEVELOP_ENV);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            if ($verbose){
                curl_setopt($ch, CURLOPT_VERBOSE, $verbose);
                $verbosebuffer = fopen('php://temp', 'rw+');
                curl_setopt($ch, CURLOPT_STDERR, $verbosebuffer);
            }

            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            
            if ($response === FALSE) {
                $this->addMessage(sprintf("cUrl error (#%d): %s<br>\n", curl_errno($ch),
                   htmlspecialchars(curl_error($ch))));
                $result = FALSE;
            } else {
                $response = $this->curl_exec_utf8($response, $ch);
                $result = json_decode($response, true);
            }
            curl_close($ch);
        } catch (\Exception $ex) {
            $this->addMessage('Could not connect well to OpenID server or got invalid data back. '.
                (_DEBUG ? $ex->getMessage() : ''));
            $result = FALSE;
        }
        
        $debug_msg = ' In connectOIDC got back:['. print_r($result,1).            
            "\n\n Curl info ".print_r($info, 1).']';
        if($verbose){
            rewind($verbosebuffer);
            $verboseLog = stream_get_contents($verbosebuffer);
            $debug_msg .= ' Debug info '.print_r($verboseLog, 1);
        }
        if (_DEBUG){
            $this->addMessage($debug_msg);
        }
        
        return $result;
    }
    
    private function curl_exec_utf8($data, $ch) {
        unset($charset);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        /* 1: HTTP Content-Type: header */
        preg_match( '@([\w/+]+)(;\s*charset=(\S+))?@i', $content_type, $matches );
        if ( isset( $matches[3] ) )
            $charset = $matches[3];

        /* 2: <meta> element in the page */
        if (!isset($charset)) {
            preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches );
            if ( isset( $matches[3] ) )
                $charset = $matches[3];
        }

        /* 3: <xml> element in the page */
        if (!isset($charset)) {
            preg_match( '@<\?xml.+encoding="([^\s"]+)@si', $data, $matches );
            if ( isset( $matches[1] ) )
                $charset = $matches[1];
        }

        /* 4: PHP's heuristic detection */
        if (!isset($charset)) {
            $encoding = mb_detect_encoding($data);
            if ($encoding)
                $charset = $encoding;
        }

        /* 5: Default for HTML */
        if (!isset($charset)) {
            if (strstr($content_type, "text/html") === 0)
                $charset = "ISO 8859-1";
        }

        /* Convert it if it is anything but UTF-8 */
        /* You can change "UTF-8"  to "UTF-8//IGNORE" to 
           ignore conversion errors and still output something reasonable */
        if (isset($charset) && strtoupper($charset) != "UTF-8")
            $data = iconv($charset, 'UTF-8', $data);

        return $data;
    }
}
