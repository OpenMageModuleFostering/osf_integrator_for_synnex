<?php
/**
 * Osf Global Services
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * 
 * Notice Block
 *
 * @category    Osf Synnex
 * @package     Osf_Synnex
 * @author      Osf Global Services
 */
class Osf_Synnex_Block_Adminhtml_System_Config_Button_Notice extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('synnex/system/config/notice.phtml');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl()
    {
        return Mage::helper('adminhtml')->getUrl('synnex/index/shipNoticeCron');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'id' => 'synnex_import_button',
                    'label' => $this->helper('adminhtml')->__('Manual Import'),
                    'onclick' => 'javascript:startNotice(); return false;'
                ));

        return $button->toHtml();
    }

}

/* Filename: Notice.php */
/* Location: app/code/community/Osf/Synnex/Block/Adminhtml/System/Config/Button/Notice.php */