<?php

class Cryozonic_StripeSubscriptions_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template
{
    public $requiredStripePaymentsVersion = "2.8.0";

    public function isStripePaymentsMissing()
    {
        if (!class_exists("Cryozonic_Stripe_Model_Standard"))
            return true;

        return false;
    }

    public function areDependenciesUpdated()
    {
        $version = Cryozonic_Stripe_Model_Standard::MODULE_VERSION;

        if ($version[0] == "{")
            return true;

        return version_compare($version, $this->requiredStripePaymentsVersion, ">=");
    }

    public function getStripePaymentsLink()
    {
        return "https://store.cryozonic.com/magento-extensions/stripe-payments.html";
    }

    public function getStripePaymentsUpgradeLink()
    {
        return "https://store.cryozonic.com/documentation/magento-1-stripe-payments#stripe-payments-upgrading";
    }

    public function getRequiredVersion()
    {
        return $this->requiredStripePaymentsVersion;
    }
}
