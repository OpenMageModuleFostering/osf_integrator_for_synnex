<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Connect Helper
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Helper_Connect extends Mage_Core_Helper_Data 
{
	public $ftpHost;
	protected $ftpUser;
	protected $ftpPass;
	public $XMLEndpoint;
	public $tmpLocation;
	public $prodFileName;
	public $logFile = 'synnex.log';
	protected $logH = 'Synnex: Error: ';

    /**
     * The class constructor method
     */
    public function __construct()
	{
		$this->ftpHost = Mage::getStoreConfig('synnex/ftplogin/ftp_host');
		$this->ftpUser = Mage::getStoreConfig('synnex/ftplogin/ftp_user');
		$this->ftpPass = Mage::helper('core')->decrypt(Mage::getStoreConfig('synnex/ftplogin/ftp_password'));
		$this->prodFileName = Mage::getStoreConfig('synnex/ftplogin/ftp_prod_file');
		$this->XMLEndpoint = Mage::getStoreConfig('synnex/xmllogin/xml_endpoint');
		$this->tmpLocation = Mage::getBaseDir('var') . DS . 'synnex' . DS;
		// check if the synnex folder exists if not create it
		return $this->checkDir();
	}

    /**
     * Get the products file from Synnex FTP
     * @return string
     * @throws Exception
     * @internal param $none
     */
	public function getProductsFile()
	{
		// init the ftp and download the products file
		$ftp = Mage::helper('synnex/ftp');
		if(is_null($this->ftpHost) || is_null($this->ftpUser) || is_null($this->ftpPass)){
			Mage::log($this->logH . 'ftphost or credentials can not be empty', 
				null, 
				$this->logFile);
			return false;
		}
		$ftp->ftpConnect($this->ftpHost, $this->ftpUser, $this->ftpPass);
		$ftp->downloadFile($this->tmpLocation . $this->prodFileName, $this->prodFileName);
		$ftp->ftpClose();

		// Checking the download file is an archive
		$fileObj = new SplFileInfo($this->tmpLocation . $this->prodFileName);
		$filename = ($fileObj->getExtension() === 'zip')? $this->extractProductsFile() : $this->prodFileName;
		$fullPath = $this->tmpLocation . $filename;
		return $fullPath;
	}

	/**
     * Gets the shipping files from Synnex FTP
     *
     * @param none
     * @return bool
     */
	public function getShippingFiles()
	{
		$shippingNotices = array();
		$files = array();
		$reqDel = array();
		
		$ftp = Mage::helper('synnex/ftp');
		$ftp->ftpConnect($this->ftpHost, $this->ftpUser, $this->ftpPass);
		// getting a list of all the files on the server
		$remoteFiles = $ftp->directoryListing();
		foreach ($remoteFiles as $remote) {
			$remoteArr = explode('.', $remote);
			$ext = array_pop($remoteArr);
			// the ship notice is an xml file so check it
			if($ext != 'xml'){
				continue;
			}

			$file['local'] = $this->tmpLocation . $remote;
			$file['remote'] = $remote;
			$files[] = $file;
			$reqDel[] = $remote;
			$shippingNotices[] = $this->tmpLocation . $remote; // same as local
		}

		// check if the files exist on the server
		if(empty($shippingNotices)){
			return false;
		}
		// download the files
		$downloadConfirm = $ftp->downloadFiles($files);
		
		// if download confirmed delete the files on the server
		if($downloadConfirm === true){
			$deleteConfirm = $ftp->deleteFiles($reqDel);
			if($deleteConfirm === false){
				Mage::log($this->logH . 'Deleting the ship notice files from the server failed', 
					null, 
					$this->logFile);
			}
		} else {
			Mage::log($this->logH . 'Downloading the ship notice files from the server failed', 
				null, 
				$this->logFile);
		}

		// close the ftp connection
		$ftp->ftpClose();

		return $shippingNotices;
	}

	/**
     * Send the process order xml request
     *
     * @param string
     * @return string
     */
	public function sendXMLRequest($xml)
	{
		/* init the http client */
		$client = new Zend_Http_Client($this->XMLEndpoint);
		/* set the method type and the timeout for the http call */
		$client->setMethod(Zend_Http_Client::POST);
		$client->setConfig(array(
		    'timeout'      => 30)
		);
		
		/* adding the post params */
		$client->setParameterPost(array(
		    'xmldata' => $xml
		));

		/* making the request to the server and receiving the request */
		$response = $client->request();
		return $response->getBody();
	}

	/**
     * Check if the Synnex temporary file exists, if not create it, if we are allowed
     *
     * @return bool
     */
	public function checkDir()
	{
		if(file_exists($this->tmpLocation) === false){
			if(!mkdir($this->tmpLocation, 0777, true)){
				Mage::log($this->logH . 'Synnex folder does not exist in var folder and it could not be created', 
					null, 
					$this->logFile);
				return false;
			}
		}
		return true;
	}

	/**
     * Send the process order xml request
     *
     * @return string|bool
     */
	public function extractProductsFile()
	{
		// init the zip archive object and open
		$zip = new ZipArchive;
		$result = $zip->open($this->tmpLocation . $this->prodFileName);
		if($result === true){
			$filename = $zip->getNameIndex(0);
			$zip->extractTo($this->tmpLocation);
			$zip->close();
		} else { 
			return false;
		}
		return $filename;
	}

}

/* Filename: Connect.php */
/* Location: app/code/local/Osf/Synnex/Helper/Connect.php */