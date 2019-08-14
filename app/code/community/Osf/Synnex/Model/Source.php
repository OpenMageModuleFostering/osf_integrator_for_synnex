<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Source Model
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Model_Source extends Mage_Core_Model_Abstract 
{
    
    const SHIPMENT_STATUS_PENDING    = 0;
    const SHIPMENT_STATUS_READY      = 1;
    const SHIPMENT_STATUS_SHIPPED    = 2;
    const SHIPMENT_STATUS_CANCELED   = 3;
    const SHIPMENT_STATUS_ONHOLD     = 4;

    public $statuses;

    public function _construct()
    {
        $this->statuses = array(
                            "Pending",
                            "Ready",
                            "Shipped",
                            "Canceled",
                            "Onhold"
                        );
    }

    /**
     * Get the status text based on the status value
     * @param string
     * @return string
     */
    public function getStatusText($status)
    {
        return $this->statuses[$status];
    }

}

/* Filename: Source.php */
/* Location: app/code/local/Osf/Synnex/Model/Source.php */