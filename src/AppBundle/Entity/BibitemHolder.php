<?php


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BibitemHolder
 *
 * @ORM\Table(name="HolderPublication")
 * @ORM\Entity
 */
class BibitemHolder
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Bibitem")
     * @ORM\JoinColumn(name="id_publication", referencedColumnName="id")
     * @var Item
     */
    private $bibitem;

    /**
     * @var Holder
     *
     * @ORM\ManyToOne(targetEntity="Holder")
     * @ORM\JoinColumn(name="id_holder", referencedColumnName="id")
     */
    private $holder;

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="string", nullable=true)
     */
    private $signature;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", nullable=true)
     */
    private $url;

    /* custom accessor */
    public function getBibitem()
    {
        return $this->bibitem;
    }

    public function setBibitem($bibitem)
    {
        $this->bibitem = $bibitem;

        return $this;
    }

    public function getHolder()
    {
        return $this->holder;
    }

    public function setHolder($holder)
    {
        $this->holder = $holder;
    }

    public function getSignature() {
        return $this->signature;
    }

    public function setSignature($signature) {
        $this->signature = $signature;
    }

    public function getUrl() {
        return $this->url;
    }

    public function setUrl($url) {
        $this->url = $url;
    }
}
