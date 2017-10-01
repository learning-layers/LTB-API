<?php
namespace ltbapi\V2\Rest\Profile;

class ProfileResourceFactory
{
    public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Profile\ProfileTable');
        $account = $services->get('Application\Service\Account');
        return new ProfileResource($table_object, $account);
    }
}
