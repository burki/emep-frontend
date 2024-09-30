<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Journal
 *
 * @see http://schema.org/CreativeWork and derived documents Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="Journal")
 *
 */
class Journal
implements \JsonSerializable, JsonLdSerializable /*, OgSerializable, TwitterSerializable */
{
    use InfoTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    protected $itemType = 'journal';

    /**
     * @var string The place(s) of publication
     *
     * @ORM\Column(name="place", type="string", nullable=true)
     */
    protected $publicationLocation; /* map to contentLocation in Schema.org */

    /**
     * @var Publisher The publisher.
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Publisher")
     * @ORM\JoinColumn(name="publisher_id", referencedColumnName="id")
     */
    protected $publisher;

    /**
     * @var string The issn of of the Journal
     *
     * *ORM\Column(type="string", nullable=true)
     */
    protected $issn;

    /**
     * @var string The name of the item.
     *
     * @Assert\Type(type="string")
     * @Assert\NotNull
     * @ORM\Column(length=512)
     */
    protected $name;

    /**
     * @var string URL of the item.
     *
     * @Assert\Url
     * @ORM\Column(nullable=true)
     */
    protected $url;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets status.
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gets status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets publication location.
     *
     * @param string $publicationLocation
     *
     * @return $this
     */
    public function setPublicationLocation($publicationLocation = null)
    {
        $this->publicationLocation = $publicationLocation;

        return $this;
    }

    /**
     * Gets publication location.
     *
     * @return string
     */
    public function getPublicationLocation()
    {
        return $this->publicationLocation;
    }

    /**
     * Sets publisher.
     *
     * @param Publisher $publisher
     *
     * @return $this
     */
    public function setPublisher($publisher = null)
    {
        $this->publisher = $publisher;

        return $this;
    }

    /**
     * Gets publisher.
     *
     * @return Publisher
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * Sets dateModified.
     *
     * @param \DateTime $dateModified
     *
     * @return $this
     */
    public function setDateModified(\DateTime $dateModified = null)
    {
        $this->changedAt = $dateModified;

        return $this;
    }

    /**
     * Gets dateModified.
     *
     * @return \DateTime
     */
    public function getDateModified()
    {
        return $this->changedAt;
    }

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Gets url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function renderCitationAsHtml($citeProc, $extended = false, $mode = null)
    {
        $data = json_decode(json_encode($this->jsonSerialize()));
        // var_dump($data);

        $ret = $citeProc->render($data, $mode);

        /* vertical-align: super doesn't render nicely:
           http://stackoverflow.com/a/1530819/2114681
        */
        $ret = preg_replace('/style="([^"]*)vertical\-align\:\s*super;([^"]*)"/',
                            'style="\1vertical-align: super; font-size: 66%;\2"', $ret);

        if ($extended) {
            // make links clickable
            $ret = preg_replace_callback('/(<span class="citeproc\-URL">&lt;)(.*?)(&gt;)/',
                function ($matches) {
                    return $matches[1]
                        . sprintf('<a href="%s" target="_blank">%s</a>',
                              $matches[2], $matches[2])
                    . $matches[3];
                },
                $ret);

            $append = [];
        }

        return preg_replace('~^<div class="csl\-bib\-body"><div style="text\-indent: \-25px; padding\-left: 25px;"><div class="csl-entry">(.*?)</div></div></div>$~',
                            '\1', $ret);
    }

    private static function mb_ucfirst($string, $encoding = 'UTF-8')
    {
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);

        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    /*
     * We transfer to Citeproc JSON
     * see https://github.com/citation-style-language/schema/blob/master/csl-data.json
     */
    public function jsonSerialize(): mixed
    {
        // see http://aurimasv.github.io/z2csl/typeMap.xml
        static $typeMap = [
            'inbook' => 'chapter',
            'techreport' => 'report',
            'article' => 'article-journal',
            'letter' => 'personal_communication',
            'innewspaper' => 'article-newspaper',
            'webpage' => 'webpage',
        ];

        $data = [
            'id' => $this->id,
            // 'uid' => $this->uid,
            // 'citation-label' => $this->slug,
            // 'status' => $this->status,
            'type' => 'journal',
            'title' => $this->getName(),
            'publisher-place' => $this->publicationLocation,
            'publisher' => !is_null($this->publisher) ? $this->publisher->getName() : null,
            'ISSN' => $this->issn,
            'URL' => $this->url,
            // 'language' => $this->language,
        ];

        return $data;
    }

    public function jsonLdSerialize($locale, $omitContext = false)
    {
        // TODO:
        // for full property,
        // see https://www.worldcat.org/title/bauvertragsrecht-kommentar-zu-den-grundzugen-des-gesetzlichen-bauvertragsrechts-631-651-bgb-unter-besonderer-berucksichtigung-der-rechtsprechung-des-bundesgerichtshofs/oclc/920898066#microdatabox
        // and http://experiment.worldcat.org/entity/work/data/1348531819
        $type = 'CreativeWork';

        switch ($this->itemType) {
            case 'journal':
                $type = 'Periodical';
                break;
        }

        $ret = [
            '@context' => 'http://schema.org',
            '@type' => $type,
        ];

        $ret['name'] = $this->name;

        if ($omitContext) {
            unset($ret['@context']);
        }

        if (in_array($type, [ 'Periodical', 'Book' ])) {
            foreach ([ 'issn' ] as $property) {
                if (!empty($this->$property)) {
                    $ret[$property] = $this->$property;
                }
            }

            if (!is_null($this->publisher)) {
                $ret['publisher'] = $this->publisher->jsonLdSerialize($locale, true);
                if (!empty($this->publicationLocation)) {
                    $location = new Place();
                    $location->setName($this->publicationLocation);
                    $ret['publisher']['location'] = $location->jsonLdSerialize($locale, true);
                }
            }
        }

        return $ret;
    }

    public function twitterSerialize($locale, $baseUrl, $params = [])
    {
        $ret = [];

        $citation = $this->renderCitationAsHtml($params['citeProc'], true);
        if (preg_match('/(.*<span class="citeproc\-title">.*?<\/span>)(.*)/', $citation, $matches)) {
            $ret['twitter:title'] = rtrim(html_entity_decode(strip_tags($matches[1])));
            $ret['twitter:description'] = rtrim(html_entity_decode(strip_tags($matches[2])));
        }

        return $ret;
    }
}
