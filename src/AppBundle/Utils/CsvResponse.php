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

        $writer = \Box\Spout\Writer\WriterFactory::create(\Box\Spout\Common\Type::XLSX);
        $writer->openToBrowser($this->filename);

        if (!empty($this->headers)) {
            $writer->addRow($this->headers);
        }

        foreach ($data as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        exit;
    }
}
