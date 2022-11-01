<?php
/**
 * Webkul Marketplace Order Shipment PDF model
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited 
 */
class Webkul_Marketplace_Model_Order_Pdf_Shipment extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Draw table header for product items
     *
     * @param  Zend_Pdf_Page $page
     * @return void
     */
    protected function _drawHeader(Zend_Pdf_Page $page)
    {
        /* Add table head */
        $this->_setFontRegular($page, 10);
        $page->setFillColor(new Zend_Pdf_Color_RGB(0.93, 0.92, 0.92));
        $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y-15);
        $this->y -= 10;
        $page->setFillColor(new Zend_Pdf_Color_RGB(0, 0, 0));

        //columns headers
        $lines[0][] = array(
            'text' => Mage::helper('sales')->__('Products'),
            'feed' => 100,
        );

        $lines[0][] = array(
            'text'  => Mage::helper('sales')->__('Qty'),
            'feed'  => 35
        );

        $lines[0][] = array(
            'text'  => Mage::helper('sales')->__('SKU'),
            'feed'  => 565,
            'align' => 'right'
        );

        $lineBlock = array(
            'lines'  => $lines,
            'height' => 10
        );

        $this->drawLineBlocks($page, array($lineBlock), array('table_header' => true));
        $page->setFillColor(new Zend_Pdf_Color_GrayScale(0));
        $this->y -= 20;
    }

    /**
     * Return PDF document
     *
     * @param  array $shipments
     * @return Zend_Pdf
     */
    public function getPdf($shipments = array())
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);
        foreach ($shipments as $shipment) {
            if ($shipment->getStoreId()) {
                Mage::app()->getLocale()->emulate($shipment->getStoreId());
                Mage::app()->setCurrentStore($shipment->getStoreId());
            }
            $page  = $this->newPage();
            $order = $shipment->getOrder();
            /* Add image */
            $this->insertLogo($page, $shipment->getStore());
            /* Add address */
            $this->insertAddress($page, $shipment->getStore());
            /* Add head */
            $this->insertOrder(
                $page,
                $shipment,
                Mage::getStoreConfigFlag(self::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID, $order->getStoreId())
            );
            /* Add document text and number */
            $this->insertDocumentNumber(
                $page,
                Mage::helper('sales')->__('Packingslip # ') . $shipment->getIncrementId()
            );
            /* Add table */
            $this->_drawHeader($page);
            /* Add body */
            foreach ($shipment->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $page, $order);
                $page = end($pdf->pages);
            }
        }
        $this->_afterGetPdf();
        if ($shipment->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }
        return $pdf;
    }

    /**
     * Create new page and assign to PDF object
     *
     * @param  array $settings
     * @return Zend_Pdf_Page
     */
    public function newPage(array $settings = array())
    {
        /* Add new table head */
        $page = $this->_getPdf()->newPage(Zend_Pdf_Page::SIZE_A4);
        $this->_getPdf()->pages[] = $page;
        $this->y = 800;
        if (!empty($settings['table_header'])) {
            $this->_drawHeader($page);
        }
        return $page;
    }

    /**
     * Insert logo to PDF object
     *
     * @param Zend_Pdf_Page $page
     * @param null $store
     */
    protected function insertLogo(&$page, $store = null)
    {
        $this->y = $this->y ? $this->y : 815;
        $image = Mage::getStoreConfig('sales/identity/logo', $store);

        $collection = Mage::getModel('marketplace/userprofile')->getCollection()
                                ->addFieldToFilter('mageuserid',array('eq'=>Mage::getSingleton('customer/session')->getCustomerId()));
        foreach ($collection as $row) {
            $image=$row->getLogopic();
        }

        if ($image) {
            $image = Mage::getBaseDir('media') . '/avatar/' . $image;
            if (is_file($image)) {

                $extension = pathinfo($image, PATHINFO_EXTENSION);

                $extension_allowed = array("jpeg", "tiff", "png");

                if (in_array($extension, $extension_allowed)){

                    $image       = Zend_Pdf_Image::imageWithPath($image);
                    $top         = 830; //top border of the page
                    $widthLimit  = 270; //half of the page width
                    $heightLimit = 270; //assuming the image is not a "skyscraper"
                    $width       = $image->getPixelWidth();
                    $height      = $image->getPixelHeight();

                    //preserving aspect ratio (proportions)
                    $ratio = $width / $height;
                    if ($ratio > 1 && $width > $widthLimit) {
                        $width  = $widthLimit;
                        $height = $width / $ratio;
                    } elseif ($ratio < 1 && $height > $heightLimit) {
                        $height = $heightLimit;
                        $width  = $height * $ratio;
                    } elseif ($ratio == 1 && $height > $heightLimit) {
                        $height = $heightLimit;
                        $width  = $widthLimit;
                    }

                    $y1 = $top - $height;
                    $y2 = $top;
                    $x1 = 25;
                    $x2 = $x1 + $width;

                    //coordinates after transformation are rounded by Zend
                    $page->drawImage($image, $x1, $y1, $x2, $y2);

                    $this->y = $y1 - 10;
                }
            }
        }
    }

    /**
     * Insert address to pdf page
     *
     * @param Zend_Pdf_Page $page
     * @param null $store
     */
    protected function insertAddress(&$page, $store = null)
    {
        $page->setFillColor(new Zend_Pdf_Color_GrayScale(0));
        $font = $this->_setFontRegular($page, 10);
        $page->setLineWidth(0);
        $this->y = $this->y ? $this->y : 815;
        $top = 815;

        $collection = Mage::getModel('marketplace/userprofile')->getCollection()
                                ->addFieldToFilter('mageuserid',array('eq'=>Mage::getSingleton('customer/session')->getCustomerId()));
        foreach ($collection as $row) {
            $address=$row->getOthersInfo();
        }

        foreach (explode("\n", $address) as $value){
            if ($value !== '') {
                $value = preg_replace('/<br[^>]*>/i', "\n", $value);
                foreach (Mage::helper('core/string')->str_split($value, 45, true, true) as $_value) {
                    $page->drawText(trim(strip_tags($_value)),
                        $this->getAlignRight($_value, 130, 440, $font, 10),
                        $top,
                        'UTF-8');
                    $top -= 10;
                }
            }
        }
        $this->y = ($this->y > $top) ? $top : $this->y;
    }
}