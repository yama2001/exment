<?php

namespace Exceedone\Exment\Services\DataImportExport\Formats;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell;
use Exceedone\Exment\Enums\SpreadsheetVendor;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

abstract class FormatBase
{
    protected $datalist;
    protected $filebasename;
    protected $spreadsheetVendor;
    protected $accept_extension = '*';

    public function datalist($datalist = [])
    {
        if (!func_num_args()) {
            return $this->datalist;
        }
        
        $this->datalist = $datalist;
        
        $this->setSpreadsheetVendor();

        return $this;
    }

    public function filebasename($filebasename = [])
    {
        if (!func_num_args()) {
            return $this->filebasename;
        }
        
        $this->filebasename = $filebasename;
        
        return $this;
    }

    public function accept_extension()
    {
        return $this->accept_extension;
    }

    /**
     * create file
     * 1 sheet - 1 table data
     */
    public function createFile()
    {
        if($this->spreadsheetVendor == SpreadsheetVendor::SPOUT){
            return $this->createFileSpOut();
        }else{
            return $this->createFilePhpSpreadSheet();
        }
    }

    /**
     * create file using PHP spreadsheet
     * 1 sheet - 1 table data
     */
    protected function createFilePhpSpreadSheet()
    {
        // define writers. if zip, set as array.
        $files = [];
        // create excel
        $spreadsheet = new Spreadsheet();
        foreach ($this->datalist as $index => $data) {
            $sheet_name = array_get($data, 'name');
            $outputs = array_get($data, 'outputs');

            $sheet = new Worksheet($spreadsheet, $sheet_name);
            $sheet->fromArray($outputs, null, 'A1', false, false);

            // set autosize
            if (count($outputs) > 0) {
                // convert folmula cell to string
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $highestColumnIndex = Cell\Coordinate::columnIndexFromString($highestColumn);
                for ($row = 1; $row <= $highestRow; ++$row) {
                    for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        if (strpos($cell->getValue(), '=') === 0) {
                            $cell->setDataType(Cell\DataType::TYPE_STRING);
                        }
                    }
                }
                $counts = count($outputs[0]);
                for ($i = 0; $i < $counts; $i++) {
                    $sheet->getColumnDimension(getCellAlphabet($i + 1))->setAutoSize(true);
                }
            }

            if ($this->isOutputAsZip()) {
                $spreadsheet->addSheet($sheet);
                $spreadsheet->removeSheetByIndex(0);
                $files[] = [
                    'name' => $sheet_name,
                    'spreadsheet' => $spreadsheet
                ];
                $spreadsheet = new Spreadsheet();
            } else {
                $spreadsheet->addSheet($sheet);
            }
        }

        if (!$this->isOutputAsZip()) {
            $spreadsheet->removeSheetByIndex(0);
            $files[] = [
                'name' => $sheet_name,
                'spreadsheet' => $spreadsheet
            ];
        }
        return $files;
    }
    
    /**
     * create file SpOyr
     * 1 sheet - 1 table data
     */
    public function createFileSpOut()
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

    protected function setSpreadsheetVendor(){
        if(boolval(config('exment.export_always_use_spout', false))){
            $this->spreadsheetVendor = SpreadsheetVendor::SPOUT;
            return $this;
        }
        
        // calc data count
        $rowCount = 0;
        $colCount = 0;
        foreach ($this->datalist as $index => $data) {
            $outputs = array_get($data, 'outputs', []);
            $rowCount += count($outputs);

            if($index == 0 && count($outputs) > 0){
                $colCount = count($outputs[0]);
            }
        }

        if($rowCount * $colCount >= 10000){
            $this->spreadsheetVendor = SpreadsheetVendor::SPOUT;
        }else{
            $this->spreadsheetVendor = SpreadsheetVendor::PHPSPREADSHEET;
        }

        return $this;
    }

    abstract public function createResponse($files);
    abstract protected function getDefaultHeaders();
}
