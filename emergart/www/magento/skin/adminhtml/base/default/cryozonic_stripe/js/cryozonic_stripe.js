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
 * @version    2.4.1
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

var stripeTokens = {};
var three_d_secure_canceled = 'Bank authentication canceled.';

var initStripe = function(apiKey)
{
    // Load Stripe.js dynamically
    if (typeof Stripe == "undefined")
    {
        var resource = document.createElement('script');
        resource.src = "https://js.stripe.com/v2/";
        resource.onload = function() {
            Stripe.setPublishableKey(apiKey);
            initLoadedStripe();
        };
        resource.onerror = function(evt) {
            console.warn("Stripe.js could not be loaded");
            console.error(evt);
        };
        var script = document.getElementsByTagName('script')[0];
        script.parentNode.insertBefore(resource, script);
    }
    else
    {
        Stripe.setPublishableKey(apiKey);
        initLoadedStripe();
    }

    // Disable server side card validation when Stripe.js is enabled
    if (typeof AdminOrder != 'undefined' && AdminOrder.prototype.loadArea && typeof AdminOrder.prototype._loadArea == 'undefined')
    {
        AdminOrder.prototype._loadArea = AdminOrder.prototype.loadArea;
        AdminOrder.prototype.loadArea = function(area, indicator, params)
        {
            if (typeof area == "object" && area.indexOf('card_validation') >= 0)
                area = area.splice(area.indexOf('card_validation'), 0);

            if (area.length > 0)
                this._loadArea(area, indicator, params);
        };
    }

    // Re-enable form inputs when the customer returns to previous checkout steps
    initPaymentForm(); // For the default Magento One Page Checkout that loads the payment form through ajax only
    cryozonicStripe.onWindowLoaded(initPaymentForm); // For OSC modules that load the whole page at the initial hit

    // Integrate Stripe.js with various One Step Checkout modules
    initOSCModules();
    cryozonicStripe.onWindowLoaded(initOSCModules); // Run it again after the page has loaded in case we missed an ajax based OSC module

    // Integrate Stripe.js with the multi-shipping payment form
    cryozonicStripe.onWindowLoaded(initMultiShippingForm);

    // Integrate Stripe.js with the admin area
    cryozonicStripe.onWindowLoaded(initAdmin); // Needed when refreshing the browser
    initAdmin(); // Needed when the payment method is loaded through an ajax call after adding the shipping address
};

// Initializations that depend on Stripe.js being loaded and ready
var initLoadedStripe = function()
{
    // Enable Apple Pay for supported devices
    initApplePay();
    cryozonicStripe.onWindowLoaded(initApplePay);
};

// A bit of namespacing
var cryozonicStripe = {
    // Properties
    multiShippingFormInitialized: false,
    oscInitialized: false,
    applePayButton: null,
    applePaySuccess: false,
    applePayResponse: null,

    // Methods
    onWindowLoaded: function(callback)
    {
        if (window.attachEvent)
            window.attachEvent("onload", callback); // IE
        else
            window.addEventListener("load", callback); // Other browsers
    }
};

var initAdmin = function()
{
    var btn = document.getElementById('order-totals');
    if (btn) btn = btn.getElementsByTagName('button');
    if (btn && btn[0]) btn = btn[0];
    if (btn) btn.onclick = function()
    {
        var radioButton = document.getElementById('p_method_cryozonic_stripe');
        if (radioButton && !radioButton.checked)
            return order.submit();

        createStripeToken(function(err)
        {
            if (err && err == three_d_secure_canceled)
                console.error(three_d_secure_canceled);
            else if (err)
                alert(err);
            else
                order.submit();
        });
    };
};

var initPaymentForm = function()
{
    var form = document.getElementById('payment_form_cryozonic_stripe');
    if (form) form.onclick = enableInputs;
};

var is3DSecureEnabled = function()
{
    return (typeof params3DSecure != 'undefined' && params3DSecure && !isNaN(params3DSecure.amount));
};

var shouldUse3DSecure = function(response)
{
    return (is3DSecureEnabled() &&
            typeof response.card.three_d_secure != 'undefined' &&
            typeof response.card.three_d_secure.supported != 'undefined' &&
            ['optional', 'required'].indexOf(response.card.three_d_secure.supported) >= 0);
};

var cryozonicSetLoadWaiting = function(section)
{
    // Check if defined first in case of an OSC module rewriting the whole thing
    if (typeof checkout != 'undefined' && checkout && checkout.setLoadWaiting)
        checkout.setLoadWaiting(section);
    else
        cryozonicToggleAdminSave(section);
};

var cryozonicToggleAdminSave = function(disable)
{
    if (typeof disableElements != 'undefined' && typeof enableElements != 'undefined')
    {
        if (disable)
            disableElements('save');
        else
            enableElements('save');
    }
};

var initApplePay = function()
{
    // Some OSC modules will refuse to reload the payment method when the billing address is changed for a customer.
    // We can't use Apple Pay without a billing address
    if (!paramsApplePay)
        return;

    // For OSC modules that do reload the payment form, more than once, we deduplicate event binding here
    var payButton = document.getElementById('apple-pay-button');
    if (payButton == cryozonicStripe.applePayButton)
        return;
    else
        cryozonicStripe.applePayButton = payButton;

    // Binding the event from javascript instead of markup can capture both clicks and touch events
    payButton.addEventListener('click', beginApplePay);

    var resetButton = document.getElementById('apple-pay-reset');
    resetButton.addEventListener('click', resetApplePayToken);

    Stripe.applePay.checkAvailability(function(available)
    {
        if (available)
            $('payment_form_cryozonic_stripe').addClassName('apple-pay-supported');
    });
};

var beginApplePay = function()
{
    var paymentRequest = paramsApplePay;

    var session = Stripe.applePay.buildSession(paymentRequest, function(result, completion)
    {
        setStripeToken(result.token.id);

        completion(ApplePaySession.STATUS_SUCCESS);

        setApplePayToken(result.token);
    },
    function(error)
    {
        alert(error.message);
    });

    session.begin();
};

var setApplePayToken = function(token)
{
    var radio = document.querySelector('input[name="payment[cc_saved]"]:checked');
    if (!radio || (radio && radio.value == 'new_card'))
        disablePaymentFormValidation();

    if ($('new_card'))
        $('new_card').removeClassName('validate-one-required-by-name');

    $('apple-pay-result-brand').update(token.card.brand);
    $('apple-pay-result-last4').update(token.card.last4);
    $('payment_form_cryozonic_stripe').addClassName('apple-pay-success');
    $('apple-pay-result-brand').className = "type " + token.card.brand;
    cryozonicStripe.applePaySuccess = true;
    cryozonicStripe.applePayToken = token;
};

var resetApplePayToken = function()
{
    var radio = document.querySelector('input[name="payment[cc_saved]"]:checked');
    if (!radio || (radio && radio.value == 'new_card'))
        enablePaymentFormValidation();

    if ($('new_card'))
        $('new_card').addClassName('validate-one-required-by-name');

    $('payment_form_cryozonic_stripe').removeClassName('apple-pay-success');
    $('apple-pay-result-brand').update();
    $('apple-pay-result-last4').update();
    $('apple-pay-result-brand').className = "";
    deleteStripeToken();
    cryozonicStripe.applePaySuccess = false;
    cryozonicStripe.applePayToken = null;
};

var addAVSFieldsTo = function(cardDetails)
{
    if (typeof avs_address_line1 != 'undefined')
    {
        cardDetails.address_line1 = avs_address_line1;
        cardDetails.address_zip = avs_address_zip;
    }
    else if (avs_enabled)
    {
        // Scenario 1: When an admin places a MOTO order for a guest who has no saved address yet
        var elementAddressStreet = document.getElementById('order-billing_address_street0');
        var elementAddressZip = document.getElementById('order-billing_address_postcode');

        // Scenario 2: We are using a OSC module at checkout which does not refresh the
        // payment form after the billing address changes.
        if (!elementAddressStreet || !elementAddressZip)
        {
            elementAddressStreet = document.getElementById('billing:street1');
            elementAddressZip = document.getElementById('billing:postcode');
        }

        if (!elementAddressStreet || !elementAddressZip)
        {
            // Scenario 3: The customer is adding a saved card from their account but they have no billing address
            return done('You must first enter your billing address.');
        }

        cardDetails.address_line1 = elementAddressStreet.value;
        cardDetails.address_zip = elementAddressZip.value;
    }

    return cardDetails;
};

var getCardDetails = function()
{
    // Validate the card
    var cardName = document.getElementById('cryozonic_stripe_cc_owner');
    var cardNumber = document.getElementById('cryozonic_stripe_cc_number');
    var cardCvc = document.getElementById('cryozonic_stripe_cc_cid');
    var cardExpMonth = document.getElementById('cryozonic_stripe_expiration');
    var cardExpYear = document.getElementById('cryozonic_stripe_expiration_yr');

    var isValid = cardName && cardName.value && cardNumber && cardNumber.value && cardCvc && cardCvc.value && cardExpMonth && cardExpMonth.value && cardExpYear && cardExpYear.value;

    if (!isValid) return null;

    var cardDetails = {
        name: cardName.value,
        number: cardNumber.value,
        cvc: cardCvc.value,
        exp_month: cardExpMonth.value,
        exp_year: cardExpYear.value
    };

    cardDetails = addAVSFieldsTo(cardDetails);

    return cardDetails;
};

var createStripeToken = function(callback)
{
    cryozonicSetLoadWaiting('payment');
    var done = function(err)
    {
        cryozonicSetLoadWaiting(false);
        return callback(err);
    };

    if (cryozonicStripe.applePaySuccess)
    {
        if (is3DSecureEnabled() && shouldUse3DSecure(cryozonicStripe.applePayToken))
            create3DSecureToken(cryozonicStripe.applePayToken.id, cryozonicStripe.applePayToken.card, cryozonicStripe.applePayToken.id, done);
        else
            done();
        return;
    }

    // First check if the "Use new card" radio is selected, return if not
    var cardDetails, newCardRadio = document.getElementById('new_card');
    if (newCardRadio && !newCardRadio.checked)
    {
        if (!is3DSecureEnabled()) return done();

        var i, savedCards = document.querySelectorAll("#saved-cards input.select");
        for (i = 0; i < savedCards.length; i++)
        {
            if (savedCards[i].checked && savedCards[i].value)
            {
                var params = savedCards[i].value.split(':');

                if (stripeTokens[params[0]])
                {
                    setStripeToken(stripeTokens[params[0]]);
                    return done();
                }
                else
                    deleteStripeToken();

                var card = {
                    id: params[0],
                    brand: params[1],
                    last4: params[2]
                };
                create3DSecureToken(params[0], card, params[0], done);

                return;
            }
        }
        return done('Please select a card.');
    }
    else
    {
        cardDetails = getCardDetails();

        if (!cardDetails)
            return done('Invalid card details');
    }

    var cardKey = JSON.stringify(cardDetails);

    if (stripeTokens[cardKey])
    {
        setStripeToken(stripeTokens[cardKey]);
        return done();
    }
    else
        deleteStripeToken();

    Stripe.card.createToken(cardDetails, function (status, response)
    {
        if (response.error)
            return done(response.error.message);

        // 3D Secure
        if (shouldUse3DSecure(response))
        {
            create3DSecureToken(response.id, response.card, cardKey, done);
        }
        else
        {
            var token = response.id + ':' + response.card.brand + ':' + response.card.last4;
            stripeTokens[cardKey] = token;
            setStripeToken(token);
            return done();
        }
    });
};

var handle3DSecureResponse = function(three_d_response, cardId, cardBrand, cardLast4, cardKey, done)
{
    var success3DSecure = function(token)
    {
        if (!token)
            token = three_d_response.id;

        three_d_secure_success = true;
        var fullToken = token + ':' + cardBrand + ':' + cardLast4;
        stripeTokens[cardKey] = fullToken;
        setStripeToken(fullToken);
    };

    if (three_d_response.status.indexOf('succe') >= 0)
    {
        success3DSecure(three_d_response.id);
        return done();
    }
    else if (three_d_response.status == 'redirect_pending')
    {
        three_d_secure_success = false;
        three_d_secure_error = false;
        open3DSecureModal(three_d_response.redirect_url,
            // Success
            function(token)
            {
                success3DSecure(token);
                modal3DSecure.close(); // This calls the Cancel callback below which calls done()
            },
            // Failed
            function(error_message)
            {
                if (error_message)
                    three_d_secure_error = error_message;
                else
                    three_d_secure_error = 'Sorry, authentication with your bank has failed.';

                modal3DSecure.close(); // This calls the Cancel callback below which calls done()
            },
            // Cancel
            function()
            {
                // Wait for the modal to close before displaying any errors
                setTimeout(function()
                {
                    if (three_d_secure_error)
                        done(three_d_secure_error);
                    else if (three_d_secure_success)
                        done();
                    else
                        done(three_d_secure_canceled);
                }, 200);

                return true;
            });
    }
    else if (three_d_response.status.indexOf('fail') >= 0)
    {
        return done('Sorry, your bank does not allow this card to be used for payments.');
    }
    else
    {
        return done('Sorry, an unknown bank authentication error has occured. Please try again.');
    }
};

var create3DSecureToken = function(stripeJsToken, card, cardKey, done)
{
    new Ajax.Request(params3DSecure.initiate_three_d_secure_url,
    {
        method: 'POST',
        parameters: {
            token: stripeJsToken,
            fingerprint: card.brand + ':' + card.exp_month + ':' + card.exp_year + ':' + card.last4
        },
        onSuccess: function(response)
        {
            var data = JSON.parse(response.responseText);
            if (!data.id || !data.status)
                return done('Sorry, an unknown error has occured during authentication with your bank');

            handle3DSecureResponse(data, card.id, card.brand, card.last4, cardKey, done);
        },
        onFailure: function(response)
        {
            try
            {
                var data = JSON.parse(response.responseText);
                if (data.error && data.error.length)
                    return done(data.error);
                throw new Exception('Could not parse API response');
            }
            catch (e)
            {
                return done('Sorry, an unknown error has occured during authentication with your bank');
            }
        }
    });
};

function setStripeToken(token)
{
    try
    {
        var input, inputs = document.getElementsByClassName('cryozonic-stripejs-token');
        if (inputs && inputs[0]) input = inputs[0];
        else input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", "payment[cc_stripejs_token]");
        input.setAttribute("class", 'cryozonic-stripejs-token');
        input.setAttribute("value", token);
        input.disabled = false; // Gets disabled when the user navigates back to shipping method
        var form = document.getElementById('payment_form_cryozonic_stripe');
        if (!form) form = document.getElementById('co-payment-form');
        if (!form) form = document.getElementById('order-billing_method_form');
        if (!form) form = document.getElementById('onestepcheckout-form');
        if (!form && typeof payment != 'undefined') form = document.getElementById(payment.formId);
        if (!form)
        {
            form = document.getElementById('new-card');
            input.setAttribute("name", "newcard[cc_stripejs_token]");
        }
        form.appendChild(input);
        disableInputs(true);
    } catch (e) {}
}

function deleteStripeToken()
{
    var input, inputs = document.getElementsByClassName('cryozonic-stripejs-token');
    if (inputs && inputs[0]) input = inputs[0];
    if (input && input.parentNode) input.parentNode.removeChild(input);
}

function disableInputs(disabled)
{
    var elements = document.getElementsByClassName('stripe-input');
    for (var i = 0; i < elements.length; i++)
    {
        // Don't disable the save cards checkbox
        if (elements[i].type == "checkbox") continue;

        // Don't disable the stripejs token
        if (elements[i].type == "hidden" && disabled) continue;

        elements[i].disabled = disabled;
    }
}

var enableInputs = function()
{
    disableInputs(false);
};

// Multi-shipping form support for Stripe.js
var multiShippingForm = null, multiShippingFormSubmitButton = null;

function submitMultiShippingForm(e)
{
    if (payment.currentMethod != 'cryozonic_stripe')
        return true;

    if (e.preventDefault) e.preventDefault();

    if (!multiShippingFormSubmitButton) multiShippingFormSubmitButton = document.getElementById('payment-continue');
    if (multiShippingFormSubmitButton) multiShippingFormSubmitButton.disabled = true;

    createStripeToken(function(err)
    {
        if (multiShippingFormSubmitButton) multiShippingFormSubmitButton.disabled = false;

        if (err && err == three_d_secure_canceled)
            console.error(three_d_secure_canceled);
        else if (err)
            alert(err);
        else
            multiShippingForm.submit();
    });

    return false;
}

// Multi-shipping form
var initMultiShippingForm = function()
{
    if (typeof payment == 'undefined' ||
        payment.formId != 'multishipping-billing-form' ||
        cryozonicStripe.multiShippingFormInitialized)
        return;

    multiShippingForm = document.getElementById(payment.formId);
    if (!multiShippingForm) return;

    if (multiShippingForm.attachEvent)
        multiShippingForm.attachEvent("submit", submitMultiShippingForm);
    else
        multiShippingForm.addEventListener("submit", submitMultiShippingForm);

    cryozonicStripe.multiShippingFormInitialized = true;
};

// 3D Secure
function open3DSecureModal(redirectUrl, success, failed, cancel)
{
    var width, w = document.documentElement.clientWidth || window.innerWidth || 0;
    if (w > 0)
        width = Math.min(w, 400);

    modal3DSecure = new Window({
        title: 'Your bank requires additional authentication.',
        minWidth: width,
        minHeight: 400,
        minimizable: false,
        maximizable: false,
        showEffectOptions: { duration: 0.2 },
        hideEffectOptions: { duration:0.2 }
    });
    modal3DSecure.successCallback = success;
    modal3DSecure.failedCallback = failed;
    modal3DSecure.setZIndex(100);
    modal3DSecure.setCloseCallback(cancel);

    var container = document.getElementById('three_d_secure_container');
    modal3DSecure.setContent('three_d_secure_container', true);

    Stripe.threeDSecure.createIframe(redirectUrl, container, function(result)
    {
        cryozonicSetLoadWaiting('payment');
        // Handle response status wording in different API versions
        if (result.status.indexOf('succe') >= 0)
            success(result.id ? result.id : null); // result.id is in the new API
        else if (result.status.indexOf('fail') >= 0)
            failed(result.error_message ? result.error_message : null); // result.error_message is in the new API
        else
            modal3DSecure.close();
    });

    cryozonicSetLoadWaiting(false);
    modal3DSecure.showCenter(true);
}

var isCheckbox = function(input)
{
    return input.attributes && input.attributes.length > 0 &&
        (input.type === "checkbox" || input.attributes[0].value === "checkbox" || input.attributes[0].nodeValue === "checkbox");
};

var disablePaymentFormValidation = function()
{
    var i, inputs = document.querySelectorAll(".stripe-input");
    var parentId = 'payment_form_cryozonic_stripe';

    $(parentId).removeClassName("stripe-new");
    for (i = 0; i < inputs.length; i++)
    {
        if (isCheckbox(inputs[i])) continue;
        $(inputs[i]).removeClassName('required-entry');
    }
};

var enablePaymentFormValidation = function()
{
    var i, inputs = document.querySelectorAll(".stripe-input");
    var parentId = 'payment_form_cryozonic_stripe';

    $(parentId).addClassName("stripe-new");
    for (i = 0; i < inputs.length; i++)
    {
        if (isCheckbox(inputs[i])) continue;
        $(inputs[i]).addClassName('required-entry');
    }
};

// Triggered when the user clicks a saved card radio button
var useCard = function(evt)
{
    // User wants to use a new card
    if (this.value == 'new_card')
        enablePaymentFormValidation();
    // User wants to use a saved card
    else
        disablePaymentFormValidation();
};

var toggleValidation = function(evt)
{
    $('new_card').removeClassName('validate-one-required-by-name');
    if (evt.target.value == 'cryozonic_stripe')
        $('new_card').addClassName('validate-one-required-by-name');
};

var initSavedCards = function(isAdmin)
{
    var inputs = document.querySelectorAll('#saved-cards input');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onclick = useCard;
    }

    // If there are no saved cards, display the new card form
    if (inputs.length === 0)
        $('payment_form_cryozonic_stripe').addClassName('stripe-new');

    if (isAdmin)
    {
        // Adjust validation if necessary
        var newCardRadio = document.getElementById('new_card');
        if (newCardRadio)
        {
            var methods = document.getElementsByName('payment[method]');
            for (var j = 0; j < methods.length; j++)
                methods[j].addEventListener("click", toggleValidation);
        }
    }
};

var saveNewCard = function()
{
    var saveButton = document.getElementById('cryozonic-savecard-button');
    var wait = document.getElementById('cryozonic-savecard-please-wait');
    saveButton.style.display = "none";
    wait.style.display = "block";

    if (typeof Stripe != 'undefined')
    {
        createStripeToken(function(err)
        {
            saveButton.style.display = "block";
            wait.style.display = "none";

            if (err && err == three_d_secure_canceled)
                console.error(three_d_secure_canceled);
            else if (err)
                alert(err);
            else
                document.getElementById("new-card").submit();

        });
        return false;
    }

    return true;
};

var initOSCModules = function()
{
    if (cryozonicStripe.oscInitialized) return;

    // Front end bindings
    if (typeof IWD != "undefined" && typeof IWD.OPC != "undefined")
    {
        // IWD OnePage Checkout override, which is a tad of a mess
        var placeOrder = function()
        {
            if (typeof $j == 'undefined') // IWD 4.0.4
                $j = $j_opc; // IWD 4.0.8

            var form = $j('#co-payment-form').serializeArray();
            IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
        };

        IWD.OPC.savePayment = function()
        {
            if (IWD.OPC.Checkout.xhr !== null)
                IWD.OPC.Checkout.xhr.abort();

            IWD.OPC.Checkout.lockPlaceOrder();
            if (payment.currentMethod == 'cryozonic_stripe')
            {
                createStripeToken(function(err)
                {
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.xhr = null;
                    IWD.OPC.Checkout.unlockPlaceOrder();

                    if (err && err == three_d_secure_canceled)
                        console.error(three_d_secure_canceled);
                    else if (err)
                        alert(err);
                    else
                        placeOrder();
                });
            }
            else
                placeOrder();
        };
        cryozonicStripe.oscInitialized = true;
    }
    else if ($('onestepcheckout-form'))
    {
        // Idev OneStepCheckout 4.5.4
        var initIdevOSC = function()
        {
            if (typeof $('onestepcheckout-form').proceed != 'undefined')
                return;

            $('onestepcheckout-form').proceed = $('onestepcheckout-form').submit;
            $('onestepcheckout-form').submit = function(e)
            {
                if (payment.currentMethod != 'cryozonic_stripe' && payment.currentMethod !== "")
                    return $('onestepcheckout-form').proceed();

                createStripeToken(function(err)
                {
                    if (err && err == three_d_secure_canceled)
                        console.error(three_d_secure_canceled);
                    else if (err)
                        alert(err);
                    else
                        $('onestepcheckout-form').proceed();
                });
            };
        };

        // This is triggered when the billing address changes and the payment method is refreshed
        window.addEventListener("load", initIdevOSC);

        cryozonicStripe.oscInitialized = true;
    }
    else if (typeof AWOnestepcheckoutForm != 'undefined')
    {
        // AheadWorks OneStepCheckout 1.3.5
        AWOnestepcheckoutForm.prototype.__sendPlaceOrderRequest = AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest;
        AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest = function()
        {
            if (payment.currentMethod != 'cryozonic_stripe')
                return this.__sendPlaceOrderRequest();

            var me = this;
            createStripeToken(function(err)
            {
                if (err && err == three_d_secure_canceled)
                    console.error(three_d_secure_canceled);
                else if (err)
                    alert(err);
                else
                    me.__sendPlaceOrderRequest();
            });
        };
        AWOnestepcheckoutPayment.prototype._savePayment = AWOnestepcheckoutPayment.prototype.savePayment;
        AWOnestepcheckoutPayment.prototype.savePayment = function() {
            if (payment.currentMethod == 'cryozonic_stripe')
                return;
            else
                return this._savePayment();
        };
        cryozonicStripe.oscInitialized = true;
    }
    // FireCheckout 3.2.0
    else if ($('firecheckout-form'))
    {
        var fireCheckoutPlaceOrder = function()
        {
            var self = this;

            if (payment.currentMethod != 'cryozonic_stripe' && payment.currentMethod !== "")
                return self.proceed();

            createStripeToken(function(err)
            {
                if (err && err == three_d_secure_canceled)
                    console.error(three_d_secure_canceled);
                else if (err)
                    alert(err);
                else
                    self.proceed();
            });
        };

        window.addEventListener("load", function()
        {
            var btnCheckout = document.getElementsByClassName('btn-checkout');
            if (btnCheckout && btnCheckout.length)
            {
                for (var i = 0; i < btnCheckout.length; i++)
                {
                    var button = btnCheckout.item(i);
                    button.proceed = button.onclick;
                    button.onclick = fireCheckoutPlaceOrder;
                }
            }
        });
        cryozonicStripe.oscInitialized = true;
    }
    else if (typeof MagegiantOneStepCheckoutForm != 'undefined')
    {
        // MageGiant OneStepCheckout 4.0.0
        MagegiantOneStepCheckoutForm.prototype.__placeOrderRequest = MagegiantOneStepCheckoutForm.prototype._placeOrderRequest;
        MagegiantOneStepCheckoutForm.prototype._placeOrderRequest = function()
        {
            if (payment.currentMethod != 'cryozonic_stripe')
                return this.__placeOrderRequest();

            var me = this;
            createStripeToken(function(err)
            {
                if (err && err == three_d_secure_canceled)
                    console.error(three_d_secure_canceled);
                else if (err)
                    alert(err);
                else
                    me.__placeOrderRequest();
            });
        };

        // Disable savePaymentMethod ajax call
        MagegiantOnestepcheckoutPaymentMethod.prototype._savePaymentMethod = MagegiantOnestepcheckoutPaymentMethod.prototype.savePaymentMethod;
        MagegiantOnestepcheckoutPaymentMethod.prototype.savePaymentMethod = function()
        {
            if (payment.currentMethod != 'cryozonic_stripe')
                return this._savePaymentMethod();
        };
        cryozonicStripe.oscInitialized = true;
    }
    else if (typeof oscPlaceOrder != 'undefined')
    {
        // Magestore OneStepCheckout 3.5.0
        var proceed = oscPlaceOrder;
        oscPlaceOrder = function(element)
        {
            var payment_method = $RF(form, 'payment[method]');
            if (payment_method != 'cryozonic_stripe')
                return proceed(element);

            createStripeToken(function(err)
            {
                if (err && err == three_d_secure_canceled)
                    console.error(three_d_secure_canceled);
                else if (err)
                    alert(err);
                else
                    proceed(element);
            });
        };
    }
    else if (typeof Payment != 'undefined' && typeof Payment.prototype.proceed == 'undefined')
    {
        // Default Magento Onepage checkout
        // Awesome Checkout 1.5.0
        // Other OSC modules

        /* The Awesome Checkout 1.5.0 configuration whitelist files are:
         *   cryozonic_stripe/js/cryozonic_stripe.js
         *   cryozonic_stripe/js/cctype.js
         *   cryozonic_stripe/css/cctype.css
         *   cryozonic_stripe/css/savedcards.css
         *   prototype/window.js
         *   prototype/windows/themes/default.css
        */

        Payment.prototype.proceed = Payment.prototype.save;

        Payment.prototype.save = function()
        {
            if (payment.currentMethod != 'cryozonic_stripe')
                return payment.proceed();

            createStripeToken(function(err)
            {
                if (err && err == three_d_secure_canceled)
                    console.error(three_d_secure_canceled);
                else if (err)
                    alert(err);
                else
                    payment.proceed();
            });
        };
    }
};
