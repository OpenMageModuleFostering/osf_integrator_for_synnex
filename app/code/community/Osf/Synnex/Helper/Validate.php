<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * Validate Helper
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Helper_Validate extends Mage_Core_Helper_Data 
{
	public $strictRules = array();
    protected $availOperands = array();
    public $customeConditions = array();

    public function __construct()
    {
        $this->availOperands['='] = 'equal';
        $this->availOperands['!='] = 'notEqual';
        $this->availOperands['>'] = 'bigger';
        $this->availOperands['<'] = 'smaller';
        $this->getConditions();
    }
	
    /**
     * Run the validation
     *
     * @param array product row
     * @return bool
     */
    public function runValidation($row)
    {
        // simplified version of validation because a dynamic validation is overkill for one condition
        if($row[22] == 'SWL'){
            return false;
        }

        return true;
    }

    /**
     * Run the custom validation from the backend configuration
     *
     * @param array product row
     * @return string
     *
     */
    public function runCustomValidation($row)
    {
        foreach ($this->customeConditions as $condition) {
            if(!isset($row[$condition[0]])) continue;
            
            if(!$this->{$condition[1]}($row,$condition[0], $condition[2])){
                return false;
            }
        }
        return true;
    }

	/**
     * Set the strict rules
     *
     * @return bool
     */
	public function setStrictRules()
	{
		$strictRules = array(22=>'RTL');
		
		return $this->strictRules = $strictRules;
	}

    /**
     * Get and process the conditions set in admin
     *
     * @return bool
     *
     */
    public function getConditions()
    {
        $strConditions = Mage::getStoreConfig('synnex/synnex_import/import_conditions');
        $conditions = explode(";", $strConditions);
        $theOperands = array_keys($this->availOperands);
        foreach ($conditions as $condition) {
            if(empty($condition)){
                continue;
            }
            
            $conditionElements = explode(' ', trim($condition));
            if(!in_array($conditionElements[1], $theOperands)){
                continue;
            }

            if(!is_numeric($conditionElements[0])){
                continue;
            }

            $this->customeConditions[] = array(
                    $conditionElements[0] - 1,
                    $this->availOperands[$conditionElements[1]],
                    $conditionElements[2]
                );

        }

        return true;
    }

    /**
     * The equal condition function, if a product value is equal with the codition value then import product
     *
     * @param array product row
     * @param string condition column 
     * @param string condition value 
     * @return bool
     *
     */
    public function equal($row, $a, $b)
    {
        return ($row[$a] != $b)? false : true;
    }

    /**
     * The non equal condition function, if a product value is not equal with the condition value then import product
     *
     * @param array product row
     * @param string condition column 
     * @param string condition value 
     * @return bool
     *
     */
    public function notEqual($row, $a, $b)
    {
        return ($row[$a] == $b)? false : true;
    }

    /**
     * The bigger condition function, if a product value is bigger then the conditon value then import product
     *
     * @param array product row
     * @param string condition column 
     * @param string condition value 
     * @return bool
     *
     */
    public function bigger($row, $a, $b)
    {
        return ($row[$a] < $b)? false : true;
    }

    /**
     * The smaller condition function, if a product value is smaller then the conditon value then import product
     *
     * @param array product row
     * @param string condition column 
     * @param string condition value 
     * @return bool
     *
     */
    public function smaller($row, $a, $b)
    {
        return ($row[$a] > $b)? false : true;
    }
}

/* Filename: Validate.php */
/* Location: app/code/local/Osf/Synnex/Helper/Validate.php */