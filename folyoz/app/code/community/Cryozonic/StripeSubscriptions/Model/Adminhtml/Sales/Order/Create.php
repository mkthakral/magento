<?php
/**
 * Cryozonic
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Single Domain License
 * that is available through the world-wide-web at this URL:
 * http://cryozonic.com/licenses/stripe.html
 * If you are unable to obtain it through the world-wide-web,
 * please send an email to info@cryozonic.com so we can send
 * you a copy immediately.
 *
 * @category   Cryozonic
 * @package    Cryozonic_StripeSubscriptions
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

class Cryozonic_StripeSubscriptions_Model_Adminhtml_Sales_Order_Create extends Mage_Adminhtml_Model_Sales_Order_Create
{
    protected $_totalItems;

    protected function isSubscriptionOrder()
    {
        $quote = $this->getQuote();
        $items = $quote->getAllItems();
        $this->_totalItems = count($items);

        foreach ($items as $item)
            if ($item->isNominal())
                return true;

        return false;
    }

    /**
     * Create new order
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        if (!$this->isSubscriptionOrder())
            return parent::createOrder();

        if ($this->_totalItems > 1)
            Mage::throwException('Sorry, you can only have a single subscription per order.');

        $this->_prepareCustomer();
        $this->_validate();
        $quote = $this->getQuote();
        $this->_prepareQuoteItems();

        $service = Mage::getModel('sales/service_quote', $quote);
        if ($this->getSession()->getOrder()->getId())
        {
            $oldOrder = $this->getSession()->getOrder();
            $originalId = $oldOrder->getOriginalIncrementId();

            if (!$originalId)
                $originalId = $oldOrder->getIncrementId();

            $orderData = array(
                'original_increment_id'     => $originalId,
                'relation_parent_id'        => $oldOrder->getId(),
                'relation_parent_real_id'   => $oldOrder->getIncrementId(),
                'edit_increment'            => $oldOrder->getEditIncrement()+1,
                'increment_id'              => $originalId.'-'.($oldOrder->getEditIncrement()+1)
            );
            $quote->setReservedOrderId($orderData['increment_id']);
            $service->setOrderData($orderData);
        }

        $service->submitAll();

        $collection = Mage::getResourceModel('sales/order_collection')
            ->setOrder('created_at', 'desc')
            ->setPageSize(1)
            ->load();

        foreach ($collection as $order)
            return $order;
    }
}