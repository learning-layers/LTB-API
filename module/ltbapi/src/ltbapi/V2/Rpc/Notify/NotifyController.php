<?php
namespace ltbapi\V2\Rpc\Notify;

use Application\Shared\SharedStatic;
use Zend\Mail;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Application\Controller\MyAbstractActionController;

class NotifyController extends MyAbstractActionController {
    
    public function notifyAction(){
        return new \Zend\View\Model\JsonModel(
            array(
                'result'=>false, 
                'message'=> "This action (notifyAction) has not been defined yet",
                'status' => 406)
        );
    }
    
    public function sendmailAction(){
        $result = FALSE;
        if ($this->account->getAuth($this->getEvent(), FALSE)){
           $user = $this->account->getCurrentUserInfo();
        } else {
            $user = FALSE;
            $message = "Only logged in users can notify themselves by email.";
            $state = 401;
        } 
        if ($user){
            //get parameters
            $to = $user['email'];
            $post_params = $this->getPostParams();
            $subject = SharedStatic::altSubValue($post_params, 'subject');
            $body = SharedStatic::altSubValue($post_params, 'message');
            $c_type = SharedStatic::altSubValue($post_params, 'c_type', 'plain');
            if (!$to || !$subject || !$body){
                $state = 400;
                $message = "We have not enough information to send a notification: $to en $subject en [$body].";
            } else {
                //send email here
                $mail = new Mail\Message();
                if ($c_type === 'html'){
                    $html = new MimePart($body);
                    $html->type = "text/html";
                    $body = new MimeMessage();
                    $body->setParts(array($html));
                }

                $mail->setBody($body)
                     ->setFrom('no_reply@ltb.io', 'Learning Toolbox Admin')
                     ->addTo($to)
                     ->setSubject($subject)
                    ->setEncoding("UTF-8");
                $transport = new Mail\Transport\Sendmail();
                try {
                    $transport->send($mail);
                    $result = TRUE;
                    $message = "You have sent your message.";
                    $state = 200;
                } catch (\Exception $e){
                    $state = $e->getCode();
                    $message = 'Sending mail caused an exception:'.(_DEBUG ? $e->getMessage() : '');
                }
            }
        }
        
        return new \Zend\View\Model\JsonModel(
            array(
                'result'  => $result, 
                'message' => $message,
                'status'  => $state
            )
        ); 
    }
}
