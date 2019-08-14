<?php
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Index controller
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_IndexController extends Mage_Core_Controller_Front_Action
{
	/**
     * The default index action
     *
     * @return string
     *
     */
	// public function indexAction()
	// {
	// 	return;
	// }

	/**
     * The Action that starts the import of products from Synnex
     *
     * @return null
     *
     */
	public function startImportAction()
	{
		return Mage::getModel('synnex/import')->processData();
	}

	/**
     * Retry to send purchase ordersCreate Shipment
     *
     * @return null
     *
     */
	public function retryCronAction()
	{
		return Mage::getModel('synnex/order')->retry();
	}

	/**
     * Check for ship notices to finalize the purchase order
     *
     * @return null
     *
     */
	public function shipNoticeCronAction()
	{
		return Mage::getModel('synnex/shipping_notice')->processNotices();
	}
}

/* Filename: IndexController.php */
/* Location: ../app/code/local/Osf/Synnex/controllers/IndexController.php */