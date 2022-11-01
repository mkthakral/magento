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

class Cryozonic_StripeSubscriptions_Model_Quote extends Mage_Sales_Model_Quote
{
    public static $working = false;

    public function addItem(Mage_Sales_Model_Quote_Item $item)
    {
        $item->setQuote($this);
        if (!$item->getId()) {
            $this->getItemsCollection()->addItem($item);
            Mage::dispatchEvent('sales_quote_add_item', array('quote_item' => $item));
        }
        return $this;
    }

    public function setCustomerId($customerId)
    {
        if (self::$working)
            return $this;

        self::$working = true;

        // Magento bugfix: When purchasing products with a recurring profile, Magento >= 1.9 does not save the customer object before submitting the recurring profile.
        // We want a $customer->save() on line 703 of app/code/core/Mage/Checkout/Model/Type/Onepage.php without overriding Onepage (don't break OnePageCheckout modules)
        parent::setCustomerId($customerId);

        $onepage = Mage::getSingleton('checkout/type_onepage');

        if ($onepage->getCheckoutMethod() == $onepage::METHOD_REGISTER && $this->getCustomer()->getEmail())
            $this->getCustomer()->save();

        self::$working = false;

        return $this;
    }
}