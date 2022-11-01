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
 * @package    Cryozonic_Stripe
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

class Cryozonic_Stripe_Model_Webhooks_Observer
{
    /*************
     * 3D Secure *
     *************/

    // source.chargeable
    public function cryozonic_stripe_webhook_source_chargeable_three_d_secure($observer)
    {
        $event = $observer->getEvent();
        $order = Mage::helper('cryozonic_stripe/webhooks')->loadOrderFromEvent($event);
        Mage::getModel('cryozonic_stripe/method_threeDSecure')->charge($order, $event['data']['object']);
    }

    // source.canceled
    public function cryozonic_stripe_webhook_source_canceled_three_d_secure($observer)
    {
        $event = $observer->getEvent();
        $order = Mage::helper('cryozonic_stripe/webhooks')->loadOrderFromEvent($event);
        $cancelled = Mage::helper('cryozonic_stripe')->cancelOrCloseOrder($order);

        // The order will not be cancelled if it has already been charged, only if it is still pending
        if ($cancelled)
        {
            $comment = "Sorry, your order has been cancelled because a 3D Secure session was initiated, but we did not receive a successful or failed authorization message from your bank. Please place your order again.";
            $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = true);
            $order->sendOrderUpdateEmail($notify = true, $comment);
            $order->save();
        }
    }

    // source.failed
    public function cryozonic_stripe_webhook_source_failed_three_d_secure($observer)
    {
        $event = $observer->getEvent();
        $order = Mage::helper('cryozonic_stripe/webhooks')->loadOrderFromEvent($event);
        Mage::helper('cryozonic_stripe')->cancelOrCloseOrder($order);

        $comment = "Your order has been cancelled because the card 3D Secure authorization failed.";
        $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = true);
        $order->sendOrderUpdateEmail($notify = true, $comment);
        $order->save();
    }

    // charge.failed - may happen if 3DS succeeded but the CVC was wrong or there were insufficient funds in the card
    public function cryozonic_stripe_webhook_charge_failed_three_d_secure($observer)
    {
        $event = $observer->getEvent();
        $order = Mage::helper('cryozonic_stripe/webhooks')->loadOrderFromEvent($event);
        Mage::helper('cryozonic_stripe')->cancelOrCloseOrder($order);

        $comment = "Your order has been cancelled. The card 3D Secure authorization succeeded, however your bank declined the payment when a charge was attempted.";
        $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = true);
        $order->sendOrderUpdateEmail($notify = true, $comment);
        $order->save();
    }

    // charge.refunded
    public function cryozonic_stripe_webhook_charge_refunded_card($observer)
    {
        $event = $observer->getEvent();
        $object = $observer->getObject();
        $order = Mage::helper('cryozonic_stripe/webhooks')->loadOrderFromEvent($event);
        Mage::helper('cryozonic_stripe/webhooks')->refund($order, $object);
    }
}
