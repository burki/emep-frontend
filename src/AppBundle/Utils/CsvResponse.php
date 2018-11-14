<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Response;


/*class CsvResponse extends Response
{
    protected $data;
    protected $filename = 'export.csv';
    public function __construct($data = [], $status = 200, $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->setData($data);
    }
    public function setData(array $data)
    {
        $output = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $this->data = '';
        while ($line = fgets($output)) {
            $this->data .= $line;
        }
        $this->data .= fgets($output);
        return $this->update();
    }
    public function getFilename()
    {
        return $this->filename;
    }
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this->update();
    }
    protected function update()
    {
        $this->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $this->filename));
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'text/csv');
        }
        return $this->setContent($this->data);
    }
}*/


class CsvResponse extends Response
{
    protected $data;
    protected $filename = 'export.csv';
    public function __construct($data = [], $status = 200, $headers = [])
    {
        // parent::__construct('', $status, $headers);
        $this->setData($data);
    }
    public function setData(array $data)
    {
        set_time_limit(5 * 60); // ItemExhibition is large

        $writer = \Box\Spout\Writer\WriterFactory::create(\Box\Spout\Common\Type::XLSX);
        $writer->openToBrowser('export' . '.xlsx');

        foreach ($data as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        exit;
    }
    public function getFilename()
    {
        return $this->filename;
    }
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this->update();
    }
    protected function update()
    {
        $this->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $this->filename));
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'text/csv');
        }
        return $this->setContent($this->data);
    }
}
