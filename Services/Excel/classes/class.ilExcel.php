<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './libs/composer/vendor/autoload.php';

/*
 * Wrapper for Microsoft Excel Import/Export (based on PHPExcel)
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ServicesExcel
 */
class ilExcel
{
	/**
	 * @var PHPExcel
	 */
	protected $workbook; // [PHPExcel]
	
	/**
	 * @var string
	 */
	protected $type; // [string]
	
	const FORMAT_XML = "Excel2007";
	const FORMAT_BIFF = "Excel5";
	
	/**
	 * Constructor
	 * 
	 * @return self
	 */
	public function __construct()
	{
		$this->setFormat(self::FORMAT_XML);		
		$this->workbook = new PHPExcel();	
		$this->workbook->removeSheetByIndex(0);
	}		
	
	
	// 
	// type/format
	// 
	
	/**
	 * Get valid file formats
	 * 
	 * @return array
	 */
	public function getValidFormats()
	{
		return array(self::FORMAT_XML, self::FORMAT_BIFF);
	}
	
	/**
	 * Set file format
	 * 
	 * @param string $a_format
	 */
	public function setFormat($a_format)
	{
		if(in_array($a_format, $this->getValidFormats()))
		{
			$this->format = $a_format;
		}
	}
	
	
	//
	// sheets
	//
	
	/**
	 * Add sheet
	 * 
	 * @param string $a_name
	 * @param bool $a_activate
	 * @return int index
	 */
	public function addSheet($a_name, $a_activate = true)
	{
		// see PHPExcel_Worksheet::$_invalidCharacters;
		$invalid = array('*', ':', '/', '\\', '?', '[', ']');
		
		$a_name = str_replace($invalid, "", $a_name);
		
		$sheet = new PHPExcel_Worksheet($this->workbook, $a_name);
		$this->workbook->addSheet($sheet);
		$new_index = $this->workbook->getSheetCount()-1;
		
		if((bool)$a_activate)
		{
			$this->setActiveSheet($new_index);
		}
		
		return $new_index;		
	}
	
	/**
	 * Set active sheet
	 * 
	 * @param int $a_index
	 */
	public function setActiveSheet($a_index)
	{
		$this->workbook->setActiveSheetIndex($a_index);
	}
	
	
	//
	// cells
	//
	
	/**
	 * Prepare value for cell
	 * 
	 * @param mixed $a_value
	 * @return mixed
	 */
	protected function prepareValue($a_value)
	{
		global $lng;
		
		// :TODO: does this make sense?
		if(is_bool($a_value))
		{
			$a_value = $a_value
				? $lng->txt("yes")
				: $lng->txt("no");
		}
		else if($a_value instanceof ilDate)
		{
			$a_value = PHPExcel_Shared_Date::stringToExcel($a_value->get(IL_CAL_DATE));			
		}	
		else if($a_value instanceof ilDateTime)
		{
			$a_value = PHPExcel_Shared_Date::stringToExcel($a_value->get(IL_CAL_DATETIME));
		}
		else if(is_string($a_value))
		{
			$a_value = strip_tags($a_value); // #14542
		}
		
		return $a_value;
	}
	
	/**
	 * Set date format
	 * 
	 * @param PHPExcel_Cell $a_cell
	 * @param mixed $a_value
	 */
	protected function setDateFormat(PHPExcel_Cell $a_cell, $a_value)
	{
		if($a_value instanceof ilDate)
		{
			// :TODO: i18n?
			$a_cell->getStyle()->getNumberFormat()->setFormatCode("dd.mm.yyyy");
		}
		else if($a_value instanceof ilDateTime)
		{
			// :TODO: i18n?
			$a_cell->getStyle()->getNumberFormat()->setFormatCode("dd.mm.yyyy hh:mm:ss");
		}
	}
	
	/**
	 * Set cell value by coordinates
	 * 
	 * @param string $a_coords
	 * @param mixed $a_value
	 */
	public function setCellByCoordinates($a_coords, $a_value)
	{
		$cell = $this->workbook->getActiveSheet()->setCellValue(
			$a_coords, 
			$this->prepareValue($a_value),
			true
		);		
		$this->setDateFormat($cell, $a_value);		
	}
	
	/**
	 * Set cell value 
	 * 
	 * @param int $a_row
	 * @param int $a_col
	 * @param mixed $a_value
	 */
	public function setCell($a_row, $a_col, $a_value)
	{
		$cell = $this->workbook->getActiveSheet()->setCellValueByColumnAndRow(
			$a_col, 
			$a_row,			 
			$this->prepareValue($a_value),
			true
		);			
		$this->setDateFormat($cell, $a_value);		
	}
	
	/**
	 * Set cell values from array 
	 * 
	 * @param array $a_values
	 * @param string $a_top_left
	 * @param mixed $a_null_value
	 */
	public function setCellArray(array $a_values, $a_top_left = "A1", $a_null_value = NULL)	
	{
		foreach($a_values as $row_idx => $cols)
		{
			if(is_array($cols))
			{
				foreach($cols as $col_idx => $col_value)
				{
					$a_values[$row_idx][$col_idx] = $this->prepareValue($col_value);
				}
			}
			else
			{
				$a_values[$row_idx] = $this->prepareValue($cols);
			}
		}
		
		$this->workbook->getActiveSheet()->fromArray($a_values, $a_null_value, $a_top_left);
	}

	/**
	 * Get column "name" from number
	 * 
	 * @param int $a_col
	 * @return string
	 */
	public function getColumnCoord($a_col)
	{
		return PHPExcel_Cell::stringFromColumnIndex($a_col);
	}
	
	/**
	 * Set all existing columns on all sheets to autosize
	 */
	protected function setGlobalAutoSize()
	{
		// this may change the active sheet
		foreach($this->workbook->getWorksheetIterator() as $worksheet) 
		{
			$this->workbook->setActiveSheetIndex($this->workbook->getIndex($worksheet));
			$sheet = $this->workbook->getActiveSheet();
			$cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
			$cellIterator->setIterateOnlyExistingCells(true);
			foreach($cellIterator as $cell) 
			{
				$sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
			}
		}
	}
	
	//
	// deliver/save
	// 
	
	/**
	 * Prepare workbook for storage/delivery
	 */
	protected function prepareStorage()
	{
		$this->setGlobalAutoSize();
		$this->workbook->setActiveSheetIndex(0);
	}
	
	/**
	 * Send workbook to client
	 * 
	 * @param string $a_file_name
	 */
	public function sendToClient($a_file_name)
	{
		$this->prepareStorage();
		
		switch($this->format)
		{
			case self::FORMAT_BIFF:
				header("Content-Type: application/vnd.ms-excel; charset=utf-8");
				if(!stristr($a_file_name, ".xls"))
				{
					$a_file_name .= ".xls";
				}
				break;
			
			case self::FORMAT_XML:
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
				if(!stristr($a_file_name, ".xls"))
				{
					$a_file_name .= ".xlsx";
				}
				break;
		}
		
		header('Content-Disposition: attachment;filename="'.$a_file_name.'"');
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		
		$writer = PHPExcel_IOFactory::createWriter($this->workbook, $this->format);
		$writer->save('php://output');
		exit();
	}
	
	/**
	 * Save workbook to file
	 * 
	 * @param string $a_file full path
	 */
	public function writeToFile($a_file)
	{
		$this->prepareStorage();
		
		$writer = PHPExcel_IOFactory::createWriter($this->workbook, $this->format);
		$writer->write($a_file);
	}		
	
	
	// 
	// style (:TODO: more options?)
	// 
	
	/**
	 * Set cell(s) to bold
	 * 
	 * @param string $a_coords
	 */
	public function setBold($a_coords)
	{
		$this->workbook->getActiveSheet()->getStyle($a_coords)->getFont()->setBold(true);
	}
	
	/**
	 * Set cell(s) colors
	 * 
	 * @param string $a_coords
	 * @param string $a_background
	 * @param string $a_font
	 */
	public function setColors($a_coords, $a_background, $a_font = null)
	{
		$opts = array(
			'fill' => array(
				'type' => PHPExcel_Style_Fill::FILL_SOLID,
				'color' => array('rgb' => $a_background)
			)
		);   
		
		if($a_font)
		{
			$opts['font'] = array(
				'color' => array('rgb' => $a_font)
			);
		}
		
		$this->workbook->getActiveSheet()->getStyle($a_coords)->applyFromArray($opts);
	}
	
	/**
	 * Toggle cell(s) borders
	 * 
	 * @param string $a_coords
	 * @param bool $a_top
	 * @param bool $a_right
	 * @param bool $a_bottom
	 * @param bool $a_left
	 */
	public function setBorders($a_coords, $a_top, $a_right = false, $a_bottom = false, $a_left = false)
	{
		$style = $this->workbook->getActiveSheet()->getStyle($a_coords);
		
		// :TODO: border styles?
		if($a_top)
		{
			$style->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}
		if($a_right)
		{
			$style->getBorders()->getRight()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}
		if($a_bottom)
		{
			$style->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}
		if($a_left)
		{
			$style->getBorders()->getLeft()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}
	}			
}