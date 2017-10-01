<?php
namespace ltbapi\V2\Rest\Stack;

class StackResourceFactory
{
    public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Stack\StackTable');
        $account = $services->get('Application\Service\Account');
        return new StackResource($table_object, $account);
    }
}
