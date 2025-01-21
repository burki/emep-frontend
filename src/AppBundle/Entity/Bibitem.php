<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

if (!function_exists('mb_ucfirst') && function_exists('mb_substr')) {
    function mb_ucfirst($string)
    {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);

        return $string;
    }
}

/**
 * Bibliographic Item
 *
 * See also [blog post](http://blog.schema.org/2014/09/schemaorg-support-for-bibliographic_2.html).
 *
 * @see http://schema.org/CreativeWork and derived documents Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="Publication")
 *
 */
class Bibitem
implements \JsonSerializable, JsonLdSerializable, OgSerializable /*, TwitterSerializable */
{
    use InfoTrait;

    /**
     * Build a list of normalized ISBNs of the book.
     *
     * @return array
     */
    public static function buildIsbnListNormalized($isbn, $hyphens = true)
    {
        $normalized = [];
        if (empty($isbn)) {
            return $normalized;
        }

        $isbnUtil = new \Isbn\Isbn();

        $candidates = preg_split('/\s+/', $isbn);
        foreach ($candidates as $candidate) {
            if (preg_match('/([0-9xX\-]+)/', $candidate, $matches)) {
                $type = $isbnUtil->check->identify($matches[1]);
                if (false !== $type) {
                    $isbn13 = 13 == $type
                        ? $matches[1]
                        : $isbnUtil->translate->to13($matches[1]);

                    if (true === $hyphens) {
                        $isbn13 = $isbnUtil->hyphens->fixHyphens($isbn13);
                    }
                    else if (false === $hyphens) {
                        $isbn13 = $isbnUtil->hyphens->removeHyphens($isbn13);
                    }

                    if (!in_array($isbn13, $normalized)) {
                        $normalized[] = $isbn13;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Build both ISBN-10 and ISBN-13.
     *
     * @return array
     */
    public static function buildIsbnVariants($isbn, $hyphens = true)
    {
        $variants = [];

        $isbnUtil = new \Isbn\Isbn();

        $type = $isbnUtil->check->identify($isbn);
        if (false === $type) {
            return $variants;
        }

        $isbn10 = 13 == $type ? $isbnUtil->translate->to10($isbn) : $isbn;
        if (false !== $isbn10) {
            if (true === $hyphens) {
                $isbn10 = $isbnUtil->hyphens->fixHyphens($isbn10);
            }
            else if (false === $hyphens) {
                $isbn10 = $isbnUtil->hyphens->removeHyphens($isbn10);
            }

            $variants[] = $isbn10;
        }

        $isbn13 = 13 == $type ? $isbn : $isbnUtil->translate->to13($isbn);

        if (true === $hyphens) {
            $isbn13 = $isbnUtil->hyphens->fixHyphens($isbn13);
        }
        else if (false === $hyphens) {
            $isbn13 = $isbnUtil->hyphens->removeHyphens($isbn13);
        }

        $variants[] = $isbn13;

        return $variants;
    }

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

    /**
     * @var string The type of the Bibliographic Item (as in Zotero)
     *
     * @ORM\Column(name="type", type="string", nullable=true)
     */
    protected $itemType;

    /**
     * @var array The author/contributor/editor of this CreativeWork.
     *
     * xORM\Column(type="json", nullable=true)
     */
    protected $creators;

    /**
     * @var string The author of this CreativeWork.
     * @ORM\Column(type="string", nullable=true)
     *
     */
    protected $author;

    /**
     * @var string The editor of this CreativeWork.
     * @ORM\Column(type="string", nullable=true)
     *
     */
    protected $editor;

    /**
     * @var string The series of books the book was published in
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $series;

    /**
     * @var string The number within the series of books the book was published in
     *
     * xORM\Column(type="string", nullable=true)
     */
    protected $seriesNumber;

    /**
     * @var string The volume of a journal or multi-volume book
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $volume;

    /**
     * @var string The number of volumes of a multi-volume book
     *
     * xORM\Column(type="string", nullable=true)
     */
    protected $numberOfVolumes;

    /**
     * @var string The issue of a journal, magazine, or tech-report, if applicable
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $issue;

    /**
     * @var string The edition of a book
     *
     * @ORM\Column(name="book_edition", type="string", nullable=true)
     */
    protected $bookEdition;

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
     * @var string Date of first broadcast/publication.
     *
     * @ORM\Column(name="publication_date", type="string", nullable=true)
     */
    protected $datePublished;

    /**
     * @var string The number of pages of the book
     *
     * @ORM\Column(name="pages", type="string", nullable=true)
     */
    protected $pagination;

    /**
     * @var string The number of pages of the book
     *
     * @ORM\Column(name="number_of_pages", type="string", nullable=true)
     */
    protected $numberOfPages;

    /**
     * @var string The printer of the book
     *
     * @ORM\Column(name="printer", type="string", nullable=true)
     */
    protected $printer;

    /**
     * @var
     *
     * @ORM\Column(name="info", type="json", nullable=true)
     */
    protected $info;

    /**
     * @var string The doi of the article
     *
     * xORM\Column(type="string", nullable=true)
     */
    protected $doi;

    /**
     * @var string The isbn of the book
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $isbn;

    /**
     * @var string The issn of of the Journal
     *
     * *ORM\Column(type="string", nullable=true)
     */
    protected $issn;

    /**
     * @var string The oclc id(s) of the book
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $oclc;

    /**
     * @var string The title of the item.
     *
     * @Assert\Type(type="string")
     * @Assert\NotNull
     * @ORM\Column(length=512)
     */
    protected $title;

    /**
     * @var string The subtitle of the item.
     *
     * @ORM\Column(nullable=true)
     */
    protected $subtitle;

    /**
     * @var string The title of the book or journal for bookSection / journalArticle.
     *
     * @ORM\Column(name="booktitle", length=512,nullable=true)
     */
    protected $containerName;

    /**
     * @var string URL of the item.
     *
     * @Assert\Url
     * @ORM\Column(nullable=true)
     */
    protected $url;

    /**
     * @var string Public note.
     *
     * @ORM\Column(nullable=true)
     */
    protected $note;

    /**
     * @var ArrayCollection<int, BibitemExhibition> The exhibition reference.
     *
     * @ORM\OneToMany(targetEntity="BibitemExhibition", mappedBy="bibitem")
     */
    public $exhibitionRefs;

    /**
     * @var ArrayCollection<int, BibitemHolder> The holder reference.
     *
     * @ORM\OneToMany(targetEntity="BibitemHolder", mappedBy="bibitem")
     */
    public $holderRefs;

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
     * Sets creators.
     *
     * @param array $creators
     *
     * @return $this
     */
    public function setCreators($creators = null)
    {
        $this->creators = $creators;

        return $this;
    }

    /**
     * Gets creators.
     *
     * @return array
     */
    public function getCreators()
    {
        return $this->creators;
    }

    /**
     * Gets author(s).
     *
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Gets author(s).
     *
     * @return string
     */
    public function getItemType()
    {
        return $this->itemType;
    }

    /**
     * Gets author(s).
     *
     * @return string
     */
    public function getEditor()
    {
        return $this->editor;
    }

    /**
     * Sets series.
     *
     * @param string $series
     *
     * @return $this
     */
    public function setSeries($series = null)
    {
        $this->series = $series;

        return $this;
    }

    /**
     * Gets series.
     *
     * @return string
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     * Sets series number.
     *
     * @param string $seriesNumber
     *
     * @return $this
     */
    public function setSeriesNumber($seriesNumber = null)
    {
        $this->seriesNumber = $seriesNumber;

        return $this;
    }

    /**
     * Gets series number.
     *
     * @return string
     */
    public function getSeriesNumber()
    {
        return $this->seriesNumber;
    }

    /**
     * Sets volume.
     *
     * @param string $volume
     *
     * @return $this
     */
    public function setVolume($volume = null)
    {
        $this->volume = $volume;

        return $this;
    }

    /**
     * Gets volume.
     *
     * @return string
     */
    public function getVolume()
    {
        return $this->volume;
    }

    /**
     * Sets number of volumes.
     *
     * @param string $numberOfVolumes
     *
     * @return $this
     */
    public function setNumberOfVolumes($numberOfVolumes = null)
    {
        $this->numberOfVolumes = $numberOfVolumes;

        return $this;
    }

    /**
     * Gets number of volumes.
     *
     * @return string
     */
    public function getNumberOfVolumes()
    {
        return $this->numberOfVolumes;
    }

    /**
     * Sets issue.
     *
     * @param string $issue
     *
     * @return $this
     */
    public function setIssue($issue = null)
    {
        $this->issue = $issue;

        return $this;
    }

    /**
     * Gets issue.
     *
     * @return string
     */
    public function getIssue()
    {
        return $this->issue;
    }

    /**
     * Sets edition of the book.
     *
     * @param string $bookEdition
     *
     * @return $this
     */
    public function setBookEdition($bookEdition = null)
    {
        $this->bookEdition = $bookEdition;

        return $this;
    }

    /**
     * Gets book edition.
     *
     * @return string
     */
    public function getBookEdition()
    {
        return $this->bookEdition;
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
     * Sets datePublished.
     *
     * @param string $datePublished
     *
     * @return $this
     */
    public function setDatePublished($datePublished = null)
    {
        $this->datePublished = $datePublished;

        return $this;
    }

    /**
     * Gets datePublished.
     *
     * @return string
     */
    public function getDatePublished()
    {
        return $this->datePublished;
    }

    /**
     * Sets pagination.
     *
     * @param string $pagination
     *
     * @return $this
     */
    public function setPagination($pagionation = null)
    {
        $this->pagination = $pagionation;

        return $this;
    }

    /**
     * Gets pagination.
     *
     * @return string
     */
    public function getPagination()
    {
        return $this->pagination;
    }

    /**
     * Sets number of pages.
     *
     * @param string $numberOfPages
     *
     * @return $this
     */
    public function setNumberOfPages($numberOfPages = null)
    {
        $this->numberOfPages = $numberOfPages;

        return $this;
    }

    /**
     * Gets number of pages.
     *
     * @return string
     */
    public function getNumberOfPages()
    {
        return $this->numberOfPages;
    }

    /**
     * Sets printer.
     *
     * @param string $printer
     *
     * @return $this
     */
    public function setPrinter($printer = null)
    {
        $this->printer = $printer;

        return $this;
    }

    /**
     * Gets printer.
     *
     * @return string
     */
    public function getPrinter()
    {
        return $this->printer;
    }

    /**
     * Sets the DOI of the publication.
     *
     * @param string $doi
     *
     * @return $this
     */
    public function setDoi($doi = null)
    {
        $this->doi = $doi;

        return $this;
    }

    /**
     * Gets the DOI of the publication.
     *
     * @return string
     */
    public function getDoi()
    {
        return $this->doi;
    }

    /**
     * Sets the ISBN of the book.
     *
     * @param string $isbn
     *
     * @return $this
     */
    public function setIsbn($isbn = null)
    {
        $this->isbn = $isbn;

        return $this;
    }

    /**
     * Gets ISBN of the book.
     *
     * @return string
     */
    public function getIsbn()
    {
        return $this->isbn;
    }

    /**
     * Gets a list of normalized ISBNs of the book.
     *
     * @return array
     */
    public function getIsbnListNormalized($hyphens = true)
    {
        return self::buildIsbnListNormalized($this->isbn, $hyphens);
    }

    /**
     * Sets dateModified.
     *
     * @param \DateTime|null $dateModified
     *
     * @return $this
     */
    public function setDateModified(?\DateTime $dateModified = null)
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    /**
     * Gets dateModified.
     *
     * @return \DateTime|null
     */
    public function getDateModified()
    {
        return $this->changedAt;
    }

    /**
     * Sets itemType.
     *
     * @param string $itemType
     *
     * @return $this
     */
    public function setItemType($itemType)
    {
        $this->itemType = $itemType;

        return $this;
    }

    /**
     * Gets itemType.
     *
     * @return string
     */
    public function itemType()
    {
        return $this->itemType;
    }

    /**
     * Sets title.
     *
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets subtitle.
     *
     * @param string $subtitle
     *
     * @return $this
     */
    public function setSubtitle($subtitle)
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * Gets subtitle.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        $nameParts = [$this->title];
        if (!empty($this->subtitle)) {
            $nameParts[] = $this->subtitle;
        }

        return join('. ', $nameParts);
    }

    /**
     * Sets container name.
     *
     * @param string $containerName
     *
     * @return $this
     */
    public function setContainerName($containerName)
    {
        $this->containerName = $containerName;

        return $this;
    }

    /**
     * Gets container name.
     *
     * @return string
     */
    public function getContainerName()
    {
        return $this->containerName;
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

    /**
     * Gets note.
     *
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    public function renderCitationAsHtml($citeProc, $extended = false)
    {
        $data = json_decode(json_encode($this->jsonSerialize()));
        // var_dump($data);

        $ret = $citeProc->render([ $data ]);

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

            if (!empty($this->printer)) {
                $append[] = 'printed by: ' . $this->printer;
            }

            if (!empty($this->numberOfPages)) {
                $append[] = 'nr. of pages: ' . $this->numberOfPages;
            }

            if (!empty($this->note)) {
                $append[] = $this->note;
            }

            if (!empty($append)) {
                $ret .= htmlspecialchars(mb_ucfirst(join(', ', $append)), ENT_COMPAT, 'utf-8') . '.';
            }
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

    private static function adjustTitle($title)
    {
        if (!is_null($title) && preg_match('/\s*\:\s+/', $title)) {
            // we don't separate subtitle by ': ' but by '. ';
            $titleParts = preg_split('/\s*\:\s+/', $title, 2);
            $title = implode('. ', [ $titleParts[0], self::mb_ucfirst($titleParts[1]) ]);
        }

        return $title;
    }

    private function parseLocalizedDate($dateStr, $locale = 'de_DE', $pattern = 'dd. MMMM yyyy')
    {
        if (function_exists('intl_is_failure')) {
            // modern method
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
            $formatter->setPattern($pattern);
            $dateObj = \DateTime::createFromFormat('U', $formatter->parse($dateStr));
            if (false !== $dateObj) {
                return [
                    'year' => (int)$dateObj->format('Y'),
                    'month' =>  (int)$dateObj->format('m'),
                    'day' => (int)$dateObj->format('d'),
                ];
            }
        }

        // longer but probably more robust
        static $monthNamesLocalized = [];

        if ('en_US' != $locale) {
            // replace localized month-names with english once

            if (!array_key_exists('en_US', $monthNamesLocalized)) {
                $months = [];
                $currentLocale = setlocale(LC_TIME, 'en_US');
                for ($month = 0; $month < 12; $month++) {
                    $months[] =  strftime('%B', mktime(0, 0, 0, $month + 1));
                }
                $monthNamesLocalized['en_US'] = $months;
                setlocale(LC_TIME, $currentLocale);
            }

            if (!array_key_exists($locale, $monthNamesLocalized)) {
                $months = [];
                $currentLocale = setlocale(LC_TIME, $locale . '.utf8');
                for ($month = 0; $month < 12; $month++) {
                    $months[] = strftime('%B', mktime(0, 0, 0, $month + 1));
                }
                $monthNamesLocalized[$locale] = $months;
                setlocale(LC_TIME, $currentLocale);
            }

            $dateStr = str_replace($monthNamesLocalized[$locale], $monthNamesLocalized['en_US'], $dateStr);
        }

        return date_parse($dateStr);
    }

    private function buildDateParts($dateStr)
    {
        $parts = [];

        if ('' === $dateStr) {
            $parts[] = $dateStr;

            return $parts;
        }

        if (!filter_var($dateStr, FILTER_VALIDATE_INT) === false) {
            $parts[] = (int)$dateStr;

            return $parts;
        }

        if (preg_match('/^(\d+)\-(\d+)\-(\d+)$/', $dateStr, $matches)) {
            for ($i = 1; $i <= 3; $i++) {
                if ($matches[$i] == 0) {
                    break;
                }

                $parts[] = $matches[$i];
            }

            return $parts;
        }

        $date = $this->parseLocalizedDate($dateStr, 'de_DE');
        if (false === $date) {
            // failed
            $parts[] = $dateStr;

            return $parts;
        }

        foreach ([ 'year', 'month', 'day' ] as $key) {
            if (empty($date[$key])) {
                return $parts;
            }
            $parts[] = $date[$key];
        }

        return $parts;
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
            'type' => array_key_exists($this->itemType, $typeMap)
                ? $typeMap[$this->itemType] : $this->itemType,
            'title' => self::adjustTitle($this->getName()),
            'container-title' => self::adjustTitle($this->containerName),
            'collection-title' => $this->series,
            'collection-number' => null, // $this->seriesNumber,
            'volume' => $this->volume,
            'number-of-volumes' => null, // $this->numberOfVolumes,
            'edition' => !is_null($this->bookEdition) && $this->bookEdition != 1
                ? $this->bookEdition : null,
            'publisher-place' => $this->publicationLocation,
            'publisher' => !is_null($this->publisher) ? $this->publisher->getName() : null,
            'issued' => [
                'date-parts' => [ $this->buildDateParts($this->datePublished) ],
                'literal' => $this->datePublished,
            ],
            'page' => $this->pagination,
            'number-of-pages' => $this->numberOfPages,
            // 'DOI' => $this->doi,
            'ISBN' => $this->isbn,
            'ISSN' => $this->issn,
            'URL' => $this->url,
            // 'language' => $this->language,
        ];

        foreach (['author', 'editor'] as $key) {
            if (!empty($this->$key)) {
                $creators = preg_split('/\s*;\s*/', $this->$key);
                foreach ($creators as $creator) {
                    // var_dump($creator);
                    if (!array_key_exists($key, $data)) {
                        $data[$key] = [];
                    }
                    $nameParts = preg_split('/\s*,\s*/', $creator, 2);

                    $targetEntry = [];
                    if (2 == count($nameParts)) {
                        $targetEntry['given'] = $nameParts[1];
                    }
                    $targetEntry['family'] = $nameParts[0];

                    $data[$key][] = $targetEntry;
                }
            }
        }
        // var_dump($data);

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
            case 'book':
                $type = 'Book';
                break;

            case 'article':
                $type = 'ScholarlyArticle';
                break;

            case 'inbook':
                $type = 'Chapter'; // see https://bib.schema.org/Chapter
                break;

            case 'innewspaper':
                $type = 'NewsArticle';
                break;

            case 'webpage':
                $type = 'WebPage';
                break;

            case 'techreport':
            case 'misc':
            case 'review':
                $type = 'CreativeWork';
                break;

            // just for building isPartOf
            case 'issue':
                $type = 'PublicationIssue';
                break;

            case 'journal':
                $type = 'Periodical';
                break;
        }

        $ret = [
            '@context' => 'http://schema.org',
            '@type' => $type,
        ];

        if ($type == 'PublicationIssue') {
            // issues don't have a name, but might have an issue-number
            if (!empty($this->volume)) {
                $ret['issueNumber'] = $this->volume;
            }

            $parent = clone $this;
            $parent->setItemType('journal');
            $ret['isPartOf'] = $parent->jsonLdSerialize($parent);
        }
        else {
            $ret['name'] = $this->getName();
        }

        if ($omitContext) {
            unset($ret['@context']);
        }

        foreach (['author', 'editor'] as $key) {
            if (empty($this->$key)) {
                continue;
            }

            $target = [];
            $creators = preg_split('/\s*;\s*/', $this->$key);
            foreach ($creators as $creator) {
                if ('author' == $key
                    && in_array($type, [ 'PublicationIssue', 'Periodical' ]))
                {
                    continue;
                }
                else if ('editor' == $key && in_array($type, [ 'Chapter' ])) {
                    continue;
                }
                $nameParts = preg_split('/\s*,\s*/', $creator, 2);
                if (2 == count($nameParts)) {
                    // we have a person
                    $person = new Person();
                    $person->setGivenName($nameParts[1]);
                    $person->setFamilyName($nameParts[0]);
                    if (!array_key_exists($key, $target)) {
                        $target[$key] = [];
                    }
                    $target[$key][] = $person->jsonLdSerialize($locale, true);
                }
            }

            foreach ($target as $targetKey => $values) {
                $numValues = count($values);
                if (1 == $numValues) {
                    $ret[$targetKey] = $values[0];
                }
                else if ($numValues > 1) {
                    $ret[$targetKey] = $values;
                }
            }
        }

        if (in_array($type, [ 'Book', 'ScholarlyArticle', 'WebPage' ])) {
            foreach ([ 'url' ] as $property) {
                if (!empty($this->$property)) {
                    $ret[$property] = $this->$property;
                }
            }

            if (!empty($this->doi)) {
                $ret['sameAs'] = 'http://dx.doi.org/' . $this->doi;
            }
        }

        if (in_array($type, [ 'Book' ])) {
            $isbns = $this->getIsbnListNormalized(false);
            $numIsbns = count($isbns);

            if (1 == $numIsbns) {
                $ret['isbn'] = $isbns[0];
            }
            else if ($numIsbns > 1) {
                $ret['isbn'] = $isbns;
            }

            if (!empty($this->numberOfPages) && preg_match('/^\d+$/', $this->numberOfPages)) {
                $ret['numberOfPages'] = (int)$this->numberOfPages;
            }
        }
        else if (in_array($type, [ 'ScholarlyArticle', 'Chapter' ])) {
            foreach ([ 'pagination' ] as $property) {
                if (!empty($this->$property)) {
                    $ret[$property] = $this->$property;
                }
            }

            if (!empty($this->containerName)) {
                $parentItemType = null;
                switch ($type) {
                    case 'ScholarlyArticle':
                        $parentItemType = 'issue';
                        break;

                    case 'Chapter':
                        $parentItemType = 'book';
                        break;
                }

                if (!is_null($parentItemType)) {
                    $parent = clone $this;
                    $parent->setItemType($parentItemType);
                    $parent->setTitle($this->containerName);
                    if ('Chapter' == $type && !empty($this->creators)) {
                        $creatorsParent = [];
                        foreach ($this->creators as $creator) {
                            if (!in_array($creator['creatorType'], [ 'author', 'translator'])) {
                                $creatorsParent[] = $creator;
                            }
                        }
                        $parent->setCreators($creatorsParent);
                    }

                    $ret['isPartOf'] = $parent->jsonLdSerialize($locale, true);
                }
            }
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

        if (!is_null($this->datePublished)
            && !in_array($type, [ 'ScholarlyArticle', 'Chapter', 'Periodical' ]))
        {
            $ret['datePublished'] = \AppBundle\Utils\JsonLd::formatDate8601($this->datePublished);
        }

        if (!empty($this->oclc)) {
            $workExamples = [];
            foreach (preg_split('/\s*,\s*/', $this->oclc) as $id) {
                $workExamples[] = sprintf('http://www.worldcat.org/oclc/%s', $id);
            }

            if (!empty($workExamples)) {
                $ret['workExample'] = $workExamples;
            }
        }

        return $ret;
    }

    public function ogSerialize($locale, $baseUrl)
    {
        $type = null;

        switch ($this->itemType) {
            case 'book':
                $isbns = $this->getIsbnListNormalized(false);
                $type = 'books.book';
                break;
        }

        if (is_null($type)) {
            return;
        }

        $ret = [
            'og:type' => $type,
            'og:title' => $this->getName(),
        ];

        $isbns = $this->getIsbnListNormalized(false);
        if (empty($isbns)) {
            // 'books:isbn' is required
            return;
        }

        $ret['books:isbn'] = $isbns[0];

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
