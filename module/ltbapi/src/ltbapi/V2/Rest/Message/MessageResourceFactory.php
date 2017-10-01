<?php
namespace ltbapi\V2\Rest\Message;

class MessageResourceFactory
{
    public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Message\MessageTable');
        $account = $services->get('Application\Service\Account');
        return new MessageResource($table_object, $account);
    }
}
