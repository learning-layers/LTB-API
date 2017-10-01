<?php
namespace ltbapi\V2\Rest\Tag;

class TagResourceFactory
{
   public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Tag\TagTable');
        $account = $services->get('Application\Service\Account');
        
        return new TagResource($table_object, $account);
    }
}
