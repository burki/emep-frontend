<?php

/**
 *
 * Shared methods
 */

namespace AppBundle\Entity;

trait InfoTrait
{
    /*
     * Expanded $info
     */
    protected $infoExpanded = [];
    
    public function hasInfo()
    {
        return !empty($this->info);
    }

    public function buildInfoFull($em, $citeProc)
    {
        // lookup publications
        $publicationsById = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])) {
                $publicationsById[$entry['id_publication']] = null;
            }
        }

        if (!empty($publicationsById)) {
            $qb = $em->createQueryBuilder();

            $qb->select([ 'B' ])
                ->from('AppBundle:Bibitem', 'B')
                ->andWhere('B.id IN (:ids) AND B.status <> -1')
                ->setParameter('ids', array_keys($publicationsById))
                ;

            $results = $qb->getQuery()
                ->getResult();
            foreach ($results as $bibitem) {
                $publicationsById[$bibitem->getId()] = $bibitem;
            }
        }

        $this->infoExpanded = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])
                && !is_null($publicationsById[$entry['id_publication']]))
            {
                $bibitem = $publicationsById[$entry['id_publication']];
                if (!empty($entry['pages'])) {
                    $bibitem->setPagination($entry['pages']);
                }
                $entry['citation'] = $bibitem->renderCitationAsHtml($citeProc, false);
            }
            $this->infoExpanded[] = $entry;
        }
    }

    public function getInfoExpanded()
    {
        return $this->infoExpanded;
    }
}
