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
        $journalsById = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])) {
                if (preg_match('/^journal:(\d+)$/', $entry['id_publication'], $matches)) {
                    $journalsById[$matches[1]] = null;;
                }
                else {
                    $publicationsById[$entry['id_publication']] = null;
                }
            }
        }

        if (!empty($publicationsById)) {
            $qb = $em->createQueryBuilder();

            $qb->select([ 'B' ])
                ->from('AppBundle\Entity\Bibitem', 'B')
                ->andWhere('B.id IN (:ids) AND B.status <> -1')
                ->setParameter('ids', array_keys($publicationsById))
                ;

            $results = $qb->getQuery()
                ->getResult();
            foreach ($results as $bibitem) {
                $publicationsById[$bibitem->getId()] = $bibitem;
            }
        }

        if (!empty($journalsById)) {
            $qb = $em->createQueryBuilder();

            $qb->select([ 'B' ])
                ->from('AppBundle\Entity\Journal', 'B')
                ->andWhere('B.id IN (:ids) AND B.status <> -1')
                ->setParameter('ids', array_keys($journalsById))
                ;

            $results = $qb->getQuery()
                ->getResult();
            foreach ($results as $journal) {
                $publicationsById['journal:' . $journal->getId()] = $journal;
            }
        }

        $this->infoExpanded = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])
                && !is_null($publicationsById[$entry['id_publication']]))
            {
                $bibitem = $publicationsById[$entry['id_publication']];
                if ($bibitem instanceof Journal) {
                    $citation = $bibitem->getName();
                    if (!empty($entry['pages'])) {
                        $citation .= ', ' . $entry['pages'];
                    }
                    $entry['citation'] = htmlspecialchars($citation . '.', ENT_COMPAT, 'utf-8');
                }
                else {
                    if (!empty($entry['pages'])) {
                        $bibitem->setPagination($entry['pages']);
                    }
                    $entry['citation'] = $bibitem->renderCitationAsHtml($citeProc, false);
                }
            }

            $this->infoExpanded[] = $entry;
        }
    }

    public function getInfoExpanded()
    {
        return $this->infoExpanded;
    }
}
