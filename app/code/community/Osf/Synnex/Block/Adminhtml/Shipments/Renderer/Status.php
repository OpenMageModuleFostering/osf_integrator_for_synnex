<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * Status Block
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Block_Adminhtml_Shipments_Renderer_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Return the label of the status
     *
     * @param Varien_Object
     * @return string
     *
     */
    public function render(Varien_Object $row)
    {
        $label = '';
        if(!is_null($row->getShipmentStatus())){
            $label = Mage::getModel('synnex/source')->getStatusText($row->getShipmentStatus());
        } else {
            $shipment = Mage::getModel('sales/order_shipment')->load($row->getId());
            $label = Mage::getModel('synnex/source')->getStatusText($shipment->getShipmentStatus());
        }

        return $label;
    }
}

/* Filename: Status.php */
/* Location: app/code/local/Osf/Synnex/Block/Adminhtml/Shipments/Renderer/Status.php */