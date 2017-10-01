<?php
namespace ltbapi\V2\Rest\Reference;

class ReferenceResourceFactory
{
    public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Reference\ReferenceTable');
        $account = $services->get('Application\Service\Account');
        return new ReferenceResource($table_object, $account);
    }
}
