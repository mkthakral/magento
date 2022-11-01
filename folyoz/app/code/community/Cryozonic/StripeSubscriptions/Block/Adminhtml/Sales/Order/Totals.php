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

class Cryozonic_StripeSubscriptions_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    protected function _initTotals()
    {
        parent::_initTotals();

        $items = $this->getSource()->getAllVisibleItems();
        if (Mage::helper('cryozonic_stripesubscriptions')->hasRecurringProducts($items))
        {
            $this->removeInvoicingTotals();

            if (!Mage::helper('cryozonic_stripesubscriptions')->isRecurringOrder($this->getSource()))
                Mage::helper('cryozonic_stripesubscriptions')->addInitialFeeTo($this);
        }

        return $this;
    }

    private function removeInvoicingTotals()
    {
        // These totals are wrong and irrelevant (there is no capturing/refunding implementation for subscriptions)
        unset($this->_totals['paid']);
        unset($this->_totals['refunded']);
        unset($this->_totals['due']);
    }
}