<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VenueListBuilder
extends LocationListBuilder
{
    /**
     * @override
     */
    public function getEntity()
    {
        return 'Venue';
    }
}
