<?php
namespace ltbapi\V2\Rest\Favourite;

class FavouriteResourceFactory
{
    public function __invoke($services)
    {
        $table_object = $services->get('ltbapi\V2\Rest\Favourite\FavouriteTable');
        $account = $services->get('Application\Service\Account');
        return new FavouriteResource($table_object, $account);
    }
}
