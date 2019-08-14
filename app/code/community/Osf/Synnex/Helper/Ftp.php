<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * FTP Helper
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Helper_Ftp extends Mage_Core_Helper_Data 
{
    private $conn;

    /**
     * Connect to FTP
     *
     * @param $host
     * @param $user
     * @param $pass
     * @return string
     * @throws Exception
     * @internal param $none
     */
    public function ftpConnect($host,$user,$pass)
    {
        $this->conn = ftp_connect($host);
        if($this->conn === false){
            throw new Exception("Ftp: Could not connect!", 1);
        }
        if(!ftp_login($this->conn, $user, $pass)){
            throw new Exception("Ftp: Could not login!", 1);
        }
        if(!ftp_pasv($this->conn, true)){
            throw new Exception("Ftp: Could not enter passive mode!", 1);
        }
        return;
    }

	/**
     * Close the ftp connection
     *
     * @param none
     * @return bool
     */
	public function ftpClose()
	{
		return ftp_close($this->conn);
	}

    /**
     * Get the directory file structure
     *
     * @param string $dir
     * @return array
     * @internal param string $params
     */
	public function directoryListing($dir='.')
	{
		return ftp_nlist($this->conn, $dir );
	}

    /**
     * Download a file from the server
     *
     * @param string $localFile
     * @param string $remoteFile
     * @param int|string $type
     * @return bool
     */
	public function downloadFile($localFile, $remoteFile, $type=FTP_BINARY)
	{
		return ftp_get($this->conn, $localFile, $remoteFile, $type);
	}

	/**
     * Download multiple files from the server
     *
     * @param none
     * @return bool
     */
	public function downloadFiles($files)
	{
        $errors = array();
		foreach ($files as $file) {
			$errors[] = $this->downloadFile($file['local'], $file['remote']);
		}

        if(in_array(false, $errors)){
            return false;
        } else {
            return true;
        }
	}

	/**
     * Delete multiple files from the server
     *
     * @param none
     * @return bool
     */
	public function deleteFiles($files)
	{
        $errors = array();
        foreach ($files as $file) {
            $errors[] = $this->deleteFile($file);
        }
        if(in_array(false, $errors)){
            return false;
        } else {
            return true;
        }
	}

    /**
     * Delete a file from the server
     *
     * @param none
     * @return bool
     */
    public function deleteFile($filePath)
    {
        return ftp_delete($this->conn, $filePath);
    }
}