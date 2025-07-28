<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Response;

class CsvResponse extends Response
{
    protected $filename;

    public function __construct($data = [], $status = 200, $headers = [], $filename = 'export.xlsx')
    {
        $this->headers = $headers;
        $this->filename = $filename;
        $this->sendResponse($data);
    }

    protected function sendResponse(array $data)
    {
        set_time_limit(5 * 60); // ItemExhibition is large

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToBrowser($this->filename);

        if (!empty($this->headers)) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($this->headers));
        }

        foreach ($data as $row) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
        }

        $writer->close();

        exit;
    }
}
