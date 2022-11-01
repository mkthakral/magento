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

class Cryozonic_StripeSubscriptions_Model_Observer
{
    public function unbundler($observer)
    {
        $quote = Mage::getModel('checkout/session')->getQuote();
        $items = $quote->getAllItems();
        $recurringChildrenProducts = array();
        $helper = Mage::helper('catalog/product_configuration');

        // Unbundle bundled products and split configurable products from their children
        foreach ($items as $item)
        {
            $product = $item->getProduct();

            if ($product->getTypeId() == 'bundle')
            {
                $options = $item->getQtyOptions();

                // We may get this if the user selected only subscriptions from a bundled product, and we separated them out into normal cart items
                if (empty($options))
                    $quote->removeItem($item->getItemId())->save();
                $product = $item->getProduct();
            }
            else if ($product->getTypeId() == 'configurable')
            {
                if (Mage::registry("configurable_drop_{$product->getId()}"))
                {
                    $recurringProductId = $this->getRecurringProductId($item);
                    if ($recurringProductId)
                    {
                        // Copy the custom options of this product for later use
                        $recurringChildrenProducts[$recurringProductId] = array(
                            'custom_price' => $item->getPrice() * Mage::app()->getStore()->getCurrentCurrencyRate(),
                            'qty_options' => $item->getQtyOptions(),
                            'custom_options' => $helper->getCustomOptions($item),
                            'item_id' => ($item->getItemId() + 1) // The next cart item is the child
                        );
                        // Drop the parent configurable product from the cart
                        $quote->removeItem($item->getItemId())->save();
                        $quote->collectTotals()->save();
                    }
                }
            }
        }

        // Loop for a second time to configure children items of configurable products
        foreach ($items as $item)
        {
            // Set the custom product options on the children configurable products
            if (isset($recurringChildrenProducts[$item->getProductId()]))
            {
                $data = $recurringChildrenProducts[$item->getProductId()];
                if ($data['item_id'] != $item->getItemId()) continue;

                $item->setOriginalCustomPrice($data['custom_price']);
                $item->setQtyOptions($data['qty_options']);

                // Copy custom options from the parent configurable product into the child recurring product
                if (count($data['custom_options']) > 0)
                {
                    foreach ($item->getOptions() as $option)
                    {
                        if ($option->getCode() == 'additional_options')
                        {
                            $value = unserialize($option->getValue());

                            foreach ($data['custom_options'] as $customOption)
                                $value[] = array('label' => $customOption['label'], 'value' => $customOption['value']);

                            $option->setValue(serialize($value));
                        }
                    }
                }

                $item->save();
            }
        }
    }

    protected function getRecurringProductId($configurableCartItem)
    {
        $qtyOptions = $configurableCartItem->getQtyOptions();
        foreach ($qtyOptions as $qtyOption)
        {
            $product = Mage::getModel('catalog/product')->load($qtyOption->getProductId());
            if ($product->getIsRecurring())
                return $qtyOption->getProductId();
        }
        return false;
    }

    // Called after a successful subscription order
    public function sales_order_place_after($observer)
    {
        if ($observer->getEvent()->getSubscribed())
        {
            $customerId = $observer->getCustomerId();
            if (empty($customerId)) return;
            $customer = Mage::getModel('customer/customer')->load($customerId);
            if (empty($customer)) return;
            $customerEmail = $customer->getEmail();

            if (!empty($customerEmail))
            {
                // Assign the customer to a group
                $group = Mage::getStoreConfig("payment/cryozonic_stripesubscriptions/scgroup");
                if ($group)
                    $customer->setGroupId($group)->save();

                try
                {
                    $resource = Mage::getSingleton('core/resource');
                    $connection = $resource->getConnection('core_write');
                    $fields = array();
                    $fields['customer_id'] = $customerId;
                    $guestSelect = $connection->quoteInto('customer_email=?', $customerEmail) . ' and ' . $connection->quoteInto('session_id=?', Mage::getSingleton("core/session")->getEncryptedSessionId());
                    $result = $connection->update('cryozonic_stripesubscriptions_customers', $fields, $guestSelect);
                }
                catch (\Exception $e) {}
            }
        }
    }
}