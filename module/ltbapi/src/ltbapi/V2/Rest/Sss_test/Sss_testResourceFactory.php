<?php
namespace ltbapi\V2\Rest\Sss_test;

class Sss_testResourceFactory
{
    public function __invoke($services)
    {
        //we just use an arbitrary table object to be able to do more or less the same
        $table_object = $services->get('ltbapi\V2\Rest\Tag\TagTable');
        $account = $services->get('Application\Service\Account');
        return new Sss_testResource($table_object, $account);
    }
}
