<?php 
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Queue Model
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */

class Osf_Synnex_Model_Resource_Queue_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract 
{
	protected function _construct()
	{
		$this->_init('synnex/queue');
	}
}

/* Filename: Collection.php */
/* Location: app/code/local/Osf/Synnex/Model/Queue/Collection.php */