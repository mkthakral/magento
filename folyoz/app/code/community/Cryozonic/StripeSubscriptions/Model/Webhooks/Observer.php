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

class Cryozonic_StripeSubscriptions_Model_Webhooks_Observer
{
    public function cryozonic_stripe_webhook_invoice_payment_succeeded($observer)
    {
        $event = $observer->getData('stdEvent');
        $profile = $this->loadProfileFrom($event);
        $this->ignoreIfJustCreated($profile);

        // If a customer pauses and then resumes a subscription, he is re-subscribed to the plan with a trial
        // until the next billing cycle. The initial re-subscription to the plan will trigger a
        // invoice.payment_succeeded event with an amount of 0. We do not want to create a new order for that event.
        if ($event->data->object->amount_due == 0) return;

        // Create a new order
        if (!empty($profile) && !empty($profile['increment_id']))
            $this->reOrder($profile, $event->data->object);
    }

    public function cryozonic_stripe_webhook_invoice_payment_failed($observer)
    {
        $event = $observer->getData('stdEvent');
        $profile = $this->loadProfileFrom($event);
        $this->ignoreIfJustCreated($profile);

        // Switch the customer to a different group
        $group = Mage::getStoreConfig("payment/cryozonic_stripesubscriptions/failed_payments_group");
        $this->setCustomerGroup($profile['customer_id'], $group);
    }

    public function cryozonic_stripe_webhook_customer_subscription_deleted($observer)
    {
        $event = $observer->getData('stdEvent');
        $profile = $this->loadProfileFrom($event);

        // We get here when the Stripe account is configured to cancel subscriptions after X failed payment attempts
        $profileModel = Mage::getModel('sales/recurring_profile')->load($profile['profile_id']);
        if ($profileModel->getState() == 'active')
            $profileModel->cancel();
    }

    //============= Helpers =============

    protected function loadProfileFrom($event)
    {
        $id = $this->getSubscriptionID($event);
        if (!$id)
            throw new Exception("The subscription ID could not be inferred from the event data.");

        return $this->getProfileByReferenceId($id);
    }

    protected function ignoreIfJustCreated($profile)
    {
        if (empty($profile['created_at']))
            throw new Exception("The local recurring profile does not have a created_at field.", 202);

        // The first time that the subscription is created, an invoice.payment_succeeded is created.
        // Ignore this event by checking it's creation time, otherwise duplicate orders will be created
        $age = $this->minutesFromNow($profile['created_at']);
        if ($age < 720)
            throw new Exception("This is a freshly created subscription. I will not create a new order.", 202); // Don't do anything if its less than 12 hours old
    }

    private function hasRecurringProducts($order)
    {
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            if ($item->getData('is_nominal'))
                return true;
        }
        return false;
    }

    private function reOrder($profile, $stripeObject)
    {
        $orderId = $profile['increment_id'];
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        try {
            if ($this->hasRecurringProducts($order))
            {
                $params = array(
                    'order' => $order,
                    'stripeObject' => $stripeObject,
                );
                $recurringOrder = Mage::getModel('cryozonic_stripesubscriptions/recurring_order', $params);
                $recurringOrder->setCustomer($order->getCustomerId());
                $recurringOrder->createOrder();
                if (Mage::getStoreConfig("payment/cryozonic_stripesubscriptions/invoice_order") == Cryozonic_StripeSubscriptions_Model_Source_Invoice::AUTOMATIC)
                    Mage::helper('cryozonic_stripesubscriptions')->invoice($recurringOrder->getOrder());
                $recurringOrder->setOrderStatus($profile);
                $recurringOrder->sendEmails();

                $recurringProfile = Mage::getModel('sales/recurring_profile');
                $recurringProfile->load($profile['profile_id']);
                $recurringProfile->addOrderRelation($recurringOrder->getOrder()->getId());
                $recurringProfile->save();

                // $queue = new Mage_Core_Model_Email_Queue();
                // $queue->send();
            }
        }
        catch (\Exception $e)
        {
            Mage::log($e->getMessage());
        }
    }

    private function getProfileByReferenceId($referenceId)
    {
        if (empty($referenceId))
            return null;

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $tablePrefix = (string)Mage::getConfig()->getTablePrefix();
        $query = $connection->select()
            ->from($tablePrefix.'sales_recurring_profile', array('*'))
            ->joinLeft(array('o' => $tablePrefix.'sales_recurring_profile_order'), $tablePrefix.'sales_recurring_profile.profile_id = o.profile_id', array('order_id'))
            ->joinLeft(array('s' => $tablePrefix.'sales_flat_order'), 'order_id = s.entity_id', array('increment_id'))
            ->where('reference_id=?', $referenceId);

        return $connection->fetchRow($query);
    }

    private function setCustomerGroup($customerId, $groupId)
    {
        if (!is_numeric($customerId) || $customerId <= 0)
            return;

        if (is_numeric($groupId) && $groupId > 0)
        {
            try
            {
                $magentoCustomer = Mage::getModel('customer/customer')->load($customerId);
                if ($magentoCustomer)
                {
                    $magentoCustomer->setGroupId($groupId);
                    $magentoCustomer->save();
                }
            }
            catch (\Exception $e)
            {
                Mage::log('Could not set customer group: '.$e->getMessage());
            }
        }
    }

    private function minutesFromNow($strTime)
    {
        $time = strtotime($strTime);
        $now = time();
        return round(abs($now - $time) / 60,0);
    }

    private function getCouponID($event)
    {
        try
        {
            $discount = $event->data->object->discount;
            if (!empty($discount) && !empty($discount->coupon) && !empty($discount->coupon->id))
                return $discount->coupon->id;
        }
        catch (\Exception $e)
        {
            Mage::log($e->getMessage());
        }
        return false;
    }

    private function getSubscriptionID($event)
    {
        if (empty($event->type))
            throw new Exception("Invalid event data");

        switch ($event->type)
        {
            case 'invoice.payment_succeeded':
            case 'invoice.payment_failed':
                if (!empty($event->data->object->lines->data[0]->subscription))
                    return $event->data->object->lines->data[0]->subscription;
                else if (!empty($event->data->object->lines->data[0]->id))
                    return $event->data->object->lines->data[0]->id;
                break;

            case 'customer.subscription.deleted':
                if (!empty($event->data->object->id))
                    return $event->data->object->id;
                break;

            default:
                return false;
        }
    }
}
