<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * Shipping Notice Model
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Model_Shipping_Notice extends Mage_Core_Model_Abstract 
{
	protected $logFile = 'synnex.log';

	/**
     * Gets the shipping files from Synnex FTP
     *
     */
	public function processNotices()
	{
		// get the ship notices from the var/synnex/shipping folder
		$shippingNotices = Mage::helper('synnex/connect')->getShippingFiles();
		if($shippingNotices === false){
			return $this;
		}
		foreach ($shippingNotices as $notice) {
			// Process each ship notice
			$noticeConfirm = $this->processNotice($notice);
			
			// Delete the ship notice file
			if($noticeConfirm === true){
				unlink($notice);
			}
		}

		return $this;
	}

	/**
     * Processes the shipping notice from synnex
     *
     * @param string $notice
     * @return bool
     */
	public function processNotice($notice)
	{
		Mage::log('Synnex: Ship Notice: The notice ' . $notice, null, $this->logFile);
		if(!file_exists($notice)){
			Mage::log('Synnex: Ship Notice Error: ship notice file not found!', null, $this->logFile);
			return false;
		}
		
		$xmlObj = simplexml_load_file($notice);
		$error = false;
		$shipNotice = $xmlObj->ShipNotice3D;
		// get shipment and check if it exists
		$shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipNotice->PONumber);
		if(is_null($shipment->getId())){
			Mage::log('Synnex: Ship Notice does not exits: ' . (string)$shipNotice->PONumber , null, $this->logFile);
			return false;
		}

		// check if the order was processed before
		if($shipment->getShipmentStatus() == Osf_Synnex_Model_Source::SHIPMENT_STATUS_SHIPPED){
			return false;
		}

		// get order and check if order exists
		$order = $shipment->getOrder();
		if(is_null($order->getId())){
			Mage::log('Synnex: Processing ship notice: Order does not exist', null, $this->logFile);
			return false;
		}

		// create the invoice
		$invoice = $this->createInvoice($shipNotice, $order);
		if($invoice === false){
			$error = true;
		}

		// add tracking number
		$trackNo = Mage::getModel('sales/order_shipment_track')
                ->setNumber($shipNotice->Package->TrackNumber)
                ->setCarrierCode($shipNotice->ShipCode)
                ->setTitle($shipNotice->ShipDescription);
        $shipment->addTrack($trackNo);
		
		// set the shipment status to shipped
		$shipment->setShipmentStatus(Osf_Synnex_Model_Source::SHIPMENT_STATUS_SHIPPED);
        $shipment->addComment('The purchase order was shipped');
		// send shipment email
		$shipment->sendEmail(true, '');
  		$shipment->setEmailSent(true);
		
		// Save the shipment
		try {
			$shipment->save();
		} catch(Exception $e) {
			Mage::log('Synnex: Ship Notice Error:'. $e->getMessage(), null, $this->logFile);
			$error = true;
		}

		// check to see if there were any errors and send email
		if($error === true){
			// send error email
			$this->sendErrorEmail($shipNotice->InvoiceNumber);
			Mage::log('Synnex: Ship Notice Error: There is an error that holds the finishing of the ship notice processing', null, $this->logFile);
			return false;
		}

		return true;
	}

    /**
     * Create and invoice based on the ship notice
     *
     * @param $shipNotice
     * @param $order
     * @return bool
     * @internal param $object
     */
	public function createInvoice($shipNotice, $order)
	{
		// init and build an array with the received sku from the ship notices items
		$shippedSku = array();
		foreach ($shipNotice->Package->Item as $item) {
			$shippedSku[(string)$item->SKU] = (int)$item->ShipQuantity;
		}

		// get the order items
		$items = $order->getItemsCollection();
        $countOrderItems = count($items);
        $countShipNoticeItems = count($shippedSku);
		// init and build an array for the invoice with the qty and ids
		$qtys = array();
		foreach($items as $orderItem){
		    if(in_array($orderItem->getSku(), array_keys($shippedSku))){
		    	$qtys[$orderItem->getId()] = $shippedSku[$orderItem->getSku()];
		    } else {
		    	$qtys[$orderItem->getId()] = 0;
		    }
		}

		// add the invoiced qtys
		$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice($qtys);
		$amount = $invoice->getGrandTotal();
		$invoice->register()->pay();
		$invoice->getOrder()->setIsInProcess(true);

		// adding a comment to the order
		$history = $invoice->getOrder()->addStatusHistoryComment(
		    'Amount of $' . $amount . ' captured automatically.', false
		);
		$history->setIsCustomerNotified(true);
		// save order
		$order->save();

		// make the invoice transaction
		Mage::getModel('core/resource_transaction')
		    ->addObject($invoice)
		    ->addObject($invoice->getOrder())
		    ->save();

		// save invoice and send the email to the costumer
		try {
			$invoice->save();
			$invoice->sendEmail(true, '');
            if($countOrderItems == $countShipNoticeItems){
                $order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->save();
            }
		} catch (Exception $e){
			Mage::log('Synnex: Invoice could not be created:'. $e->getMessage(), null, $this->logFile);
			return false;
		}

		return true;
	}

    /**
     * Sending the import error email
     * @param $shipNoticeId
     * @return bool
     */
    public function sendErrorEmail($shipNoticeId)
	{
		$toEmail = Mage::getStoreConfig('trans_email/ident_support/email');
		$toName = Mage::getStoreConfig('trans_email/ident_support/name');
		
		// load the template
        $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('synnex_import_error');
        $emailTemplate->setSenderName(Mage::getStoreConfig('general/store_information/name'));
        $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_sales/email'));
        $emailTemplate->setTemplateSubject(Mage::helper('core')->__('Canex: Synnex Ship Notice Import Error'));

        try {
            $emailTemplate->send($toEmail, $toName, array('shipNoticeId'=>$shipNoticeId));
        } catch(Exception $e){
            Mage::log("Synnex: Error send error email: ". $e->getMessage(), null, $this->logFile);
            return false;
        }

        return true;
	}

}

/* Filename: Notice.php */
/* Location: app/code/local/Osf/Synnex/Model/Shipping/Notice.php */