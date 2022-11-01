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

class Cryozonic_Stripe_Model_Method_ThreeDSecure
{
    public function charge($order, $object)
    {
        $orderId = $order->getIncrementId();

        $payment = $order->getPayment();
        if (!$payment)
            throw new Exception("Could not load payment method for order #$orderId", 202);

        $customerStripeId = $payment->getAdditionalInformation('customer_stripe_id');

        $orderSourceId = $payment->getAdditionalInformation('source_id');
        $webhookSourceId = $object['id'];
        if ($orderSourceId != $webhookSourceId)
            throw new Exception("Received source.chargeable webhook for order #$orderId but the source ID on the webhook $webhookSourceId was different than the one on the order $orderSourceId", 202);

        $stripe = Mage::getModel('cryozonic_stripe/standard');
        $stripe->setInfoInstance($payment);

        try
        {
            // Charge the card
            if (Mage::getStoreConfig('payment/cryozonic_stripe/payment_action') == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE)
            {
                Mage::helper('cryozonic_stripe')->captureOrder($order);
                $comment = "Payment authorized and captured in Stripe";
            }
            else
            {
                $stripe->createCharge($payment, false);
                $comment = "Payment authorized in Stripe";
            }
            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, false, $comment);
            $transaction->setIsClosed(0);
            $transaction->save();
            $payment->setIsTransactionPending(false);
            $payment->save();

            if ($order->getState() != Mage_Sales_Model_Order::STATE_PROCESSING)
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, "Transaction created in Stripe", false);

            // Save the card
            if ($payment->getAdditionalInformation('save_card'))
                $stripe->addCardToCustomer($object['three_d_secure']['card'], null, $customerStripeId);

            // Send the order email
            if ($order->getCanSendNewEmailFlag()) {
                try {
                    $order->sendNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $order->save();
        }
        catch (\Stripe\Error\Card $e)
        {
            $comment = "Order could not be charged because of a card error: " . $e->getMessage();
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
        catch (\Stripe\Error $e)
        {
            Mage::logException($e);
            $comment = "Order could not be charged because of a Stripe error.";
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
        catch (\Exception $e)
        {
            Mage::logException($e);
            $comment = "Order could not be charged because of server side error.";
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }
}