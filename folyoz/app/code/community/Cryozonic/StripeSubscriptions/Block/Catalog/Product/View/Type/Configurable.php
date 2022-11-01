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

class Cryozonic_StripeSubscriptions_Block_Catalog_Product_View_Type_Configurable extends Mage_Catalog_Block_Product_View_Type_Configurable
{
    public function getConfigurableInputType($product)
    {
        $type = $product->getCryozonicConfigurableInput();

        // Use config setting
        if (empty($type))
            $type = Mage::getStoreConfig('payment/cryozonic_stripesubscriptions/configurable_products_input');

        // Default
        if (empty($type))
            $type = Cryozonic_StripeSubscriptions_Model_Source_Configurable::DROPDOWN;

        return $type;
    }

    public function getOptionsFromJson($json, $attributeId)
    {
        $data = json_decode($json, true);
        $options = $data['attributes'][$attributeId]['options'];

        foreach ($options as $option)
        {
            $label = $option['label'];
            $price = false;

            if ($option['price'] && $option['price'] != 0)
            {
                $sign = ($option['price'] > 0) ? '+' : '-';
                $price = " $sign" . str_replace('#{price}', money_format('%i', abs($option['price'])), $data['template']);
            }

            $formattedOptions[] = array(
                'value' => $option['id'],
                'label' => $label,
                'price' => $option['price'],
                'formattedPrice' => $price
            );
        }

        return $formattedOptions;
    }
}
