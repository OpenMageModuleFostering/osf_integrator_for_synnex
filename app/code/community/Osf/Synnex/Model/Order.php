<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * Order Model
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Model_Order extends Mage_Core_Model_Abstract 
{
	protected $synnexAccount;
	protected $XMLUser;
	protected $XMLPass;
	protected $XMLObj;
	protected $order;
	protected $queue;
	protected $logFile = 'synnex.log';
	protected $knownErrors = array('DatabaseIssue', 'IncompleteXML01', 'Internal API Fault',
            'PO_TIMEOUT', 'ServerError', 'IP Address Invalid');

	public function _construct()
	{
		$this->synnexAccount = Mage::getStoreConfig('synnex/synnex/synnex_account_number');
		$this->XMLUser = Mage::getStoreConfig('synnex/xmllogin/xml_username');
		$this->XMLPass = Mage::helper('core')->decrypt(Mage::getStoreConfig('synnex/xmllogin/xml_password'));
		parent::_construct();
	}

	/**
     *  Build the xml from array
     *
     * @param array
     * @return string
     */
	public function buildXMLData($orderData){
		$this->XMLObj = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SynnexB2B></SynnexB2B>');
		
		// credentials
		$credentials = $this->XMLObj->addChild('Credential');
		$credentials->addChild('UserID', $this->XMLUser);
		$credentials->addChild('Password', $this->XMLPass);
		
		// Order Request
		$orderReq = $this->XMLObj->addChild('OrderRequest');
		foreach ($orderData['orderReq'] as $reqKey => $reqValue) {
			$orderReq->addChild($reqKey, $reqValue);
		}

		// Order Request Items
		$items = $orderReq->addChild('Items');
		$i = 1;
		foreach ($orderData['items'] as $item) {
			$itemNode = $items->addChild('Item');
			$itemNode->addAttribute('lineNumber', $i);
			$itemNode->addChild('SKU', $item['sku']);
			$itemNode->addChild('CustomerPartNumber', $item['customerPartNumber']);
			$itemNode->addChild('ProductName', $item['name']);
			$itemNode->addChild('UnitPrice', $item['price']);
			$itemNode->addChild('OrderQuantity', $item['qty']);
			$itemNode->addChild('Comment', $item['comment1']);
			$itemNode->addChild('Comment', $item['comment2']);
			$itemNode->addChild('ShipFromWarehouse', $item['shipfrom']);
			$itemNode->addChild('SpecialPriceReferenceNumber', $item['specialPriceRef']);
			$i++;
		}
		
		// Add the shipment node of the xml
		$shipmentNode = $orderReq->addChild('Shipment');
		foreach ($orderData['shipment'] as $shipmentKey => $shipment) {
			if(!is_array($shipment)){
				$shipmentNode->addChild($shipmentKey, $shipment);
			} else {
				$node = $shipmentNode->addChild($shipmentKey);
				foreach ($shipment as $shipKey => $shipValue) {
					$node->addChild($shipKey,$shipValue);
				}
			}
		}

		// Add the payment node in the xml
		$paymentNode = $orderReq->addChild('Payment');
		foreach ($orderData['payment'] as $paymentKey => $payment) {
			if(!is_array($payment)){
				$paymentNode->addChild($paymentKey, $payment);
			} else {
				$node = $paymentNode->addChild($paymentKey);
				if($paymentKey == 'BillTo'){
					$node->addAttribute('code', trim($this->synnexAccount));
				}
				foreach ($payment as $shipKey => $shipValue) {
					$node->addChild($shipKey,$shipValue);
				}
			}
		}
		
		return $this->XMLObj->asXML();
	}

	/**
     *  Build the order array so it can be sent to xml process
     *
     * @param object
     * @param array
     * @return string
     */
	public function buildOrderArray($order, $shipment)
	{
		$this->order = $order;
		$data = array(
			'orderReq' => $this->buildOrderReq($shipment),
			'items' => $this->buildItems($shipment),
			'payment' => $this->buildPayment($order),
			'shipment' => $this->buildShipping($shipment)
		);

		return $this->buildXMLData($data);
	}

	/**
     * Map order shipping to array for xml
     *
     * @param object
     * @return array
     */
	public function buildShipping($shipment)
	{
		$shippingAddress = $shipment->getShippingAddress();
		$streetArr = $shippingAddress->getStreet();

		$shipment = array(
            "ShipTo" => array(
                "AddressName1" => $shippingAddress->getFirstname() . $shippingAddress->getLastname(),
                "AddressName2" => null,
                "AddressLine1" => $streetArr[0],
                "AddressLine2" => (count($streetArr) > 1)? $streetArr[1] : null,
                "City" => $shippingAddress->getCity(),
                "State" => $shippingAddress->getRegionCode(),
                "ZipCode" => $shippingAddress->getPostcode(),
                "Country" => $shippingAddress->getCountryId()
            ),
            "ShipToContact" => array(
                "ContactName" => $shippingAddress->getFirstname() . $shippingAddress->getLastname(),
                "PhoneNumber" => $shippingAddress->getTelephone(),
                "EmailAddress" => $shippingAddress->getEmail()
            ),
            "ShipMethod" => array(
                "Code" => null,
                "Description" => null,
            ),
            "FreightAccountNumber" => null
		);
		return $shipment;
	}

	/**
     * Map order billing to array for xml
     *
     * @param object
     * @return array
     */
	public function buildPayment($order)
	{
		$origin = Mage::getStoreConfig('shipping/origin', $this->getStore());
		$region = Mage::getModel('directory/region')->load($origin["region_id"]);
		$payment = array(
			"BillTo" => array(
                "AddressName1" => Mage::app()->getStore()->getFrontendName(),
                "AddressName2" => null,
                "AddressLine1" => $origin['street_line1'],
                "AddressLine2" => $origin['street_line2'],
                "City" => $origin['city'],
                "State" => $region->getCode(),
                "ZipCode" => $origin['postcode'],
                "Country" => $origin['country_id'],
                "SynnexLocationNumber" => null
			)
		);
		return $payment;
	}

	/**
     * Map order items to array for xml
     *
     * @param array
     * @return array
     */
	public function buildItems($shipment)
	{
		$items = array();
		foreach ($shipment->getAllItems() as $itemObj) {
			$item = array();
			$item['sku'] = $itemObj->getSku();
			$item['customerPartNumber'] = $itemObj->getSku();
			$item['name'] = $itemObj->getName();
			$item['price'] = $itemObj->getPrice();
			$item['qty'] = (int)$itemObj->getQty();
			$item['comment1'] = null;
			$item['comment2'] = null;
			$item['shipfrom'] = null;
			$item['specialPriceRef'] = null;
			$items[] = $item;
		}

		return $items;
	}

	/**
     * Map order details to array for xml
     *
     * @param object
     * @return array
     */
	public function buildOrderReq($shipment)
	{
		// build the root xml nodes for order request
		$orderReq = array(
			"CustomerNumber" => trim($this->synnexAccount),
			"PONumber" => $shipment->getIncrementId(),
			"PODateTime" => $shipment->getCreatedAt(),
			"XMLPOSubmitDateTime" => null,
			"ExpectedDate" => null,
			"ExpectedShipDate" => null,
			"DropShipFlag" => 'TBD', // to be determined 
			"SpecialHandle" => 'N',
			"BackOrderFlag" => 'N',
			"BackOrderWarehouseSelection" => null,
			"ShipComplete" => 'Y',
			"POLineShipComplete" => 'Y',
			"WarehouseSplit" => 'N',
			"ShipFromWarehouse" => null,
			"SpecialPriceType" => null,
			"SpecialPriceReferenceNumber" => null,
			"SynnexB2BAssignedID" => '6',
			"Comment_1" => null,
			"Comment_2" => null,
			"ShipComment_1" => null,
			"ShipComment_2" => null
		);

		return $orderReq;
	}

    /**
     * Process the order response
     *
     * @param $xmlResponse
     * @param $order
     * @param $xmlData
     * @internal param $object
     * @return array
     */
	public function processOrderResponse($xmlResponse, $order, $xmlData)
	{
		$this->order = $order;
		$xmlResponseObject = simplexml_load_string($xmlResponse);
        if($xmlResponseObject === false){
            Mage::log('Synnex: The response was not a xml string', null, $this->logFile);
            return;
        }

		$orderResponse = $xmlResponseObject->OrderResponse;
		if(is_null($orderResponse)){
			Mage::log('Synnex: The response xml does not contain the OrderResponse node', null, $this->logFile);
			return;
		}

		if(isset($xmlResponse->errorMessage)){
			Mage::log('Order Response Error: ' .
				$xmlResponse->errorMessage . 
				'Order Response Error Details' .
				$xmlResponse->errorDetail, null, $this->logFile);
			$this->errorResponse($xmlResponseObject, $xmlData);
			return;
		}

		$poCode = $xmlResponseObject->OrderResponse->Code;
		if($poCode == 'accepted'){
			$this->acceptedResponse($xmlResponseObject);
		} else {
			$this->rejectedResponse($xmlResponseObject);
		}

		return;
	}

	/**
     * The accepted response handle
     *
     * @param object
     */
	public function acceptedResponse($xmlObject)
	{ 
		$orderResponse = $xmlObject->OrderResponse;
		Mage::log('Synnex PO accepted: '. $orderResponse->PONumber, null, $this->logFile);

        $this->order->addStatusHistoryComment(
                        $this->order->getStatus(), 
                        "Synnex PO Number: " . $orderResponse->Items->Item->OrderNumber
                        );
		$shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($orderResponse->PONumber);
		$shipment->addComment('Purchase Order Accepted');
        $shipment->setShipmentStatus(Osf_Synnex_Model_Source::SHIPMENT_STATUS_READY);
		try{
			$shipment->save();
		} catch (Exception $e){
			Mage::log('Synnex: Response Error: ', null, $this->logFile);
		}

		return;
	}

	/**
     * The rejected response handle
     *
     * @param object
     */
	public function rejectedResponse($xmlObject)
	{
		$orderResponse = $xmlObject->OrderResponse;
		Mage::log('Synnex PO rejected: '. $orderResponse->Reason, null, $this->logFile);
		// load the shipment and cancel it
		$shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($orderResponse->PONumber);
        $shipment->addComment('Purchase Order Rejected: ' . $orderResponse->Reason);
        $shipment->setShipmentStatus(Osf_Synnex_Model_Source::SHIPMENT_STATUS_CANCELED);
		try{
			$shipment->save();
		} catch (Exception $e){
			Mage::log('Synnex: Response Error: '.$e->getMessage(), null, $this->logFile);
		}

		return;
	}

    /**
     * The error response handle
     *
     * @param $orderResponse
     * @param $xmlData
     * @throws Exception
     * @internal param $object
     * @return null
     */
	public function errorResponse($xmlResponseObject,$xmlData)
	{
		$xmlObject = simplexml_load_string($xmlData);
		$queue = Mage::getModel('synnex/queue')->loadByField('order_id', $this->order->getId());
		if($queue->getId() === null){
			$queue = Mage::getModel('synnex/queue');
			$queue->setOrderId($this->order->getId());
			$queue->setPoId($xmlObject->OrderRequest->PONumber);
			$queue->setOrderXml(trim($xmlData));
			$queue->setRetry(0);
			$queue->setPrevError($xmlResponseObject->errorMessage . ' : ' . $xmlResponseObject->errorDetail);
		} else {
			$retry = $queue->getRetry();
			if($retry >= 5){
				$queue->delete();
			}
			$retry++;
			$queue->setRetry($retry);
		}

		if(in_array($xmlResponseObject->errorMessage, $this->knownErrors)){
			$queue->save();
		} else {
            $shipment->addComment($xmlResponseObject->errorDetail);
            $shipment->setShipmentStatus(Osf_Synnex_Model_Source::SHIPMENT_STATUS_ONHOLD);
			try{
                $shipment->save();
			} catch (Exception $e){
				Mage::log('Synnex: Response Error: unknown error order on hold', null, $this->logFile);
			}
		}

		return;
	}

	/**
     * The retry the orders gave known errors
     */
	public function retry()
	{
		$jobs = Mage::getModel('synnex/queue')->getCollection();
		foreach ($jobs as $job) {
            Mage::log('Synnex: Retry: ' . $job->getOrderId(), null, $this->logFile);
			$order = Mage::getModel('sales/order')->load($job->getOrderId());
			$xmlResponse = Mage::helper('synnex/connect')->sendXMLRequest($job->getOrderXml());
			$response = Mage::getModel('synnex/order')->processOrderResponse($xmlResponse, $order, $job->getOrderXml());
		}

		return;
	}

}

/* Filename: Order.php */
/* Location: app/code/local/Osf/Synnex/Model/Order.php */