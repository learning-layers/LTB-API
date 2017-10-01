<?php
namespace Application\Model;

class ModelTableCapableSSS extends ModelTable {    
   
    public function callSocSemServer($action, $data, $oid_token){
        $sss = $this->getOtherService('Application\Service\SocialSemanticConnector');
        $sss->setOidToken($oid_token);
        list($sss_result, $sss_ok, $sss_msg) = 
            $sss->callSocialSemanticServer($action, $data, _DEBUG);
        if ($sss_ok){
            return array($sss_result, $sss_msg);
        } else {
            return $this->returnProblem($sss_result, 
            "Your request has not entirely been performed: Social Semantic ".
            "Server Fails to perform the changes too. Resulting in [$sss_result] ".$sss_msg);
        }
    }      
}
