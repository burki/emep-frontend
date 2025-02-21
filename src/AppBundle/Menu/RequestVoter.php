<?php

namespace AppBundle\Menu;

use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Voter\VoterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestVoter implements VoterInterface
{
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function matchItem(ItemInterface $item): ?bool
    {
        $requestUri = $this->requestStack->getCurrentRequest()->getRequestUri();

        if ($item->getUri() === $requestUri) {
            // URL's completely match
            return true;
        }
        else if ($item->getUri() !== $this->requestStack->getCurrentRequest()->getBaseUrl() . '/'
                  && (substr($requestUri, 0, strlen($item->getUri())) === $item->getUri())) {
            // URL isn't just "/" and the first part of the URL match
            return true;
        }
        else {
            // check if any of the children match
            foreach ($item->getChildren() as $child) {
                if ($child->getUri() == $requestUri) {
                    return true;
                }
            }
        }

        return null;
    }
}
