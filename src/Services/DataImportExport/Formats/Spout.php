<?php

namespace Exceedone\Exment\Services\DataImportExport\Formats;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Carbon\Carbon;

class Spout extends FormatBase
{
    protected $accept_extension = 'xlsx';

    public function getFileName()
    {
        return $this->filebasename.date('YmdHis'). ".xlsx";
    }

    public function createResponse($files)
    {
        return response()->stream(function () use ($files) {
            $files[0]['writer']->save('php://output');
        }, 200, $this->getDefaultHeaders());
    }

    protected function getDefaultHeaders()
    {
        $filename = $this->getFileName();
        return [
            'Content-Type'        => 'application/force-download',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
    }

    /**
     * get data table list. contains self table, and relations (if contains)
     */
    public function getDataTable($request)
    {
        // get file
        if (is_string($request)) {
            $path = $request;
        } else {
            $file = $request->file('custom_table_file');
            $path = $file->getRealPath();
        }
        
        $reader = $this->createReader();
        $reader->open($path);

        $datalist = [];

        try {
            // get all data
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $datalist[$sheetName] = getDataFromSheet($sheet, 0, false, true);
                }
            }
        } finally {
            $reader->colse();
        }

        return $datalist;
    }

    /**
     * create file
     * 1 sheet - 1 table data
     */
    public function createFile()
    {
        // define writers. if zip, set as array.
        $files = [];
        // create excel
        $filename = $this->getFileName();

        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToBrowser($filename);

        foreach ($this->datalist as $index => $data) {
            $sheet_name = array_get($data, 'name');
            $outputs = array_get($data, 'outputs');

            if ($index > 0) {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
            } else {
                $sheet = $writer->getCurrentSheet();
            }
            $sheet->setName($sheet_name);

            // set autosize
            if (count($outputs) > 0) {
                for ($i = 0; $i < count($outputs); $i++) {
                    $data = collect($outputs[$i])->map(function($value) {
                        if ($value instanceof Carbon) {
                            return $value->__toString();
                        }
                        return $value;
                    })->toArray();
                    $row = WriterEntityFactory::createRowFromArray($data);
                    $writer->addRow($row);
                }
            }
        }
        $writer->close();
    }

    /**
     * whether this out is as zip.
     * This table is parent and contains relation 1:n or n:n.
     */
    protected function isOutputAsZip()
    {
        return false;
    }
    
    protected function createWriter($spreadsheet)
    {
        return WriterEntityFactory::createXLSXWriter();
    }
    
    protected function createReader()
    {
        return ReaderEntityFactory::createXLSXReader();
    }
}
