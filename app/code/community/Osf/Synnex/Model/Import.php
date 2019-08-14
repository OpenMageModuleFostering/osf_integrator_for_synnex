<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Import Model
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Model_Import extends Mage_Core_Model_Abstract 
{
	public $resource;
	public $readConnection;
	public $categoryMap = array();
	public $synnexVendorId;
	protected $logFile = 'synnex.log';

	public function _construct()
	{
		$this->resource = Mage::getSingleton('core/resource');
	    $this->readConnection = $this->resource->getConnection('core_read');
	    $this->buildCategoryMap();
        if(!$this->checkMagmiConfig()){
            echo "Could not create magmi config file. Please make the folder ... temporary writeable";
            die();
        }
	    parent::_construct();
	}
	
	/**
     * Process the received csv
     *
     * @param none
     * @return bool
     */
	public function processData()
	{
		/* getting the products file from the server */
		$productsFile = Mage::helper('synnex/connect')->getProductsFile();
		if($productsFile === false){
			Mage::log("Synnex: Error in getting products file", null, $this->logFile);
			return false;
		}

        /* init vars */
		$file = new SplFileObject($productsFile);
		$productsData = array();
		$row1 = 0;

		/* looping through the csv */
		while(!$file->eof()){
			$row = $file->fgetcsv("~");
			/* omit the first row that has the header of the file */
			if($row1 == 0){
				$row1 = 1;
				continue;
			}
			if(empty($row[4])){
				continue;
			}
			/* validate data */
			if(!$this->validate($row)){
				continue;
			}

			/* setting the product data */
            $productData = $this->createDataArr($row);
            if($productData !== false){
                $productsData[] = $productData;
            }

		}

		/* setting the data to be imported and starting the import */
		Mage::helper('synnex/data')->setImportArray($productsData);
		Mage::helper('synnex/data')->startMagmiImport();

		return true;
	}

	/**
     * Validate the product based on the required validations
     *
     * @param array $row
     * @return bool
     */
	public function validate($row)
	{
		// Validate strict rules
		Mage::helper('synnex/validate')->setStrictRules();
		$valid = Mage::helper('synnex/validate')->runValidation($row);
		if(!$valid){
			return false;
		}
        
        $valid2 = Mage::helper('synnex/validate')->runCustomValidation($row);
        if(!$valid2){
            return false;
        }

		$productExists = $this->productExists($row[4]);
		# Check if ABC Code is equal with active
		if($row[39] !== 'A' && $productExists === false){
			return false;
		}
		
		# Check if Kit/Stand Alone Flag is equal with S
		if($row[40] !== 'S' && $productExists === false){
			return false;
		}
		
		return true;
	}

	/**
     * Construct the product array for import
     *
     * @param array $row
     * @return array $productData
     */
	public function createDataArr($row)
	{
        $importedCatId = ltrim($row[24],'0');
        $catMap = $this->getCategoryId($importedCatId);
        $websites = Mage::app()->getWebsites();
        $storeCode = $websites[1]->getDefaultStore()->getCode();

		$productData                        = array();
		$productData['sku']                 = $row[4];
		$productData['url_key']             = Mage::getModel('catalog/product_url')->formatUrlKey($row[6]);
		$productData['url_rewrite']         = 1;
		$productData['mpn']                 = $row[2];
		$productData['name']                = $row[6];
		$productData['short_description']   = $row[6];
		$productData['description']         = $row[6];
		$productData['manufacturer']        = $row[7];
        $productData['qty']                 = $row[9];
		$productData['msrp']                = $row[13];
		$productData['is_oversized']        = ($row[18] === 'N')? 1 : 0;
        $productData['osf_product_cost']	= $row[20];
        $productData['osf_product_vendor']  = 'Synnex';
        $productData['weight']              = (!empty($row[27]))? $row[27] : 1;
        $productData['upc']                 = $row[33];
        $productData['item_length']         = $row[52];
        $productData['item_width']          = $row[53];
        $productData['item_height']         = $row[54];
        $productData['status']              = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        $productData['visibility']          = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
        $productData['tax_class_id']        = 2;
        $productData['type_id']             = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
        $productData['attribute_set_id']    = 4;
        $productData['store']               = $storeCode;
        $productData['categories']          = (!is_null($catMap))? $catMap['category_paths'] : null;
        
        return $productData;
    }

	/**
     * Check if a product with a specific sku exists in Magento
     *
     * @param string $sku
     * @return bool
     */
	public function productExists($sku)
	{
		$tableName = $this->resource->getTableName('catalog_product_entity');
		$select = $this->readConnection
            ->select()
            ->from($tableName, array(new Zend_Db_Expr('count(entity_id)')))
            ->where($this->readConnection->quoteInto('sku=?', $sku));
        $countSku = $this->readConnection->fetchOne($select);

	    return ($countSku != 0)? true :false;
	}

	public function getCategoryId($catId)
	{
		return (array_key_exists($catId, $this->categoryMap))? $this->categoryMap[$catId] : null;
	}

    /**
     * Build the category array map based of the category map file
     */
    public function buildCategoryMap()
	{
        $filename = Mage::getStoreConfig('synnex/synnex_import/upload_file');
        $categoryMapFile = Mage::getBaseDir('media') . DS . 'admin-config-uploads' . DS . $filename;

        if(!file_exists($categoryMapFile)){
            return;
        }

		$mapFile = new SplFileObject($categoryMapFile);
		
		$row1 = 0;
		/* looping thought the csv */
		while(!$mapFile->eof()){
			$row = $mapFile->fgetcsv();

			/* omit the first row that has the header of the file */
			if($row1 == 0){
				$row1 = 1;
				continue;
			}

			/* setting the product data */
			$this->categoryMap[$row[0]]['category_paths'] = $row[1];
		}

		return;
	}

    /**
     * Check if the db configuration for magmi exists and if not create it
     *
     * @return bool
     */
    public function checkMagmiConfig()
    {
        $filename = Mage::getModuleDir('', 'Osf_Synnex') . DS . 'lib' . DS . "magmi/conf/magmi.ini";
        if(file_exists($filename)){
            return true;
        }

        $config  = Mage::getConfig()->getResourceConnectionConfig("default_setup");
        $file = new SplFileObject($filename,"w");
        $fileText = "[DATABASE]\nconnectivity = \"net\"\nhost = \"" 
        . $config->host . "\"\nport = \"3306\"\nunix_socket = \ndbname = \"" 
        . $config->dbname . "\"\nuser = \"" 
        . $config->username . "\"\npassword = " 
        . $config->password . "\ntable_prefix = \n[MAGENTO]\nversion = \"1.7.x\"\nbasedir = \"../../\"\n[GLOBAL]\n"
        ."step = \"0.5\"\nmultiselect_sep = \",\"\ndirmask = \"755\"\nfilemask = \"644\"\n";

        try{
            $file->fwrite($fileText);
        } catch (Exception $e){
            Mage::log('Magmi Conf folder not writeable');
            return false;
        }

        return true;
    }

}

/* Filename: Import.php */
/* Location: app/code/local/Osf/Synnex/Model/Import.php */