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
 * @version    3.2.2
 * @build      {{BUILD}}
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

var stripeTokens = {};

var initStripe = function(apiKey, securityMethod)
{
    cryozonic.securityMethod = securityMethod;
    cryozonic.apiKey = apiKey;

    // Load Stripe.js dynamically
    if (!cryozonic.stripeJsV2 && !cryozonic.stripeJsV3)
    {
        cryozonic.loadStripeJsV2(function()
        {
            if (cryozonic.securityMethod == 2 || cryozonic.isApplePayEnabled())
                cryozonic.loadStripeJsV3();
        });
    }
    else
    {
        if (cryozonic.stripeJsV2)
            cryozonic.onLoadStripeJsV2();

        if (cryozonic.stripeJsV3)
            cryozonic.onLoadStripeJsV3();
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

    // Integrate Stripe.js with various One Step Checkout modules
    initOSCModules();
    cryozonic.onWindowLoaded(initOSCModules); // Run it again after the page has loaded in case we missed an ajax based OSC module

    // Integrate Stripe.js with the multi-shipping payment form
    cryozonic.onWindowLoaded(initMultiShippingForm);

    // Errors at the checkout review step should send the customer back to the payment section
    cryozonic.initReviewStepErrors();
    cryozonic.onWindowLoaded(cryozonic.initReviewStepErrors); // For OSC modules

    // Integrate Stripe.js with the admin area
    cryozonic.onWindowLoaded(initAdmin); // Needed when refreshing the browser
    initAdmin(); // Needed when the payment method is loaded through an ajax call after adding the shipping address
};

var cryozonic = {
    // Properties
    billingInfo: null,
    multiShippingFormInitialized: false,
    oscInitialized: false,
    applePayButton: null,
    applePaySuccess: false,
    applePayResponse: null,
    securityMethod: 0,
    card: null,
    paymentFormValidator: null,
    stripeJsV2: null,
    stripeJsV3: null,
    apiKey: null,
    iconsContainer: null,

    // Methods
    shouldLoadStripeJsV2: function()
    {
        return (cryozonic.securityMethod == 1 || (cryozonic.securityMethod == 2 && cryozonic.isApplePayEnabled()));
    },
    loadStripeJsV2: function(callback)
    {
        if (!cryozonic.shouldLoadStripeJsV2())
            return callback();

        var script = document.getElementsByTagName('script')[0];
        var stripeJsV2 = document.createElement('script');
        stripeJsV2.src = "https://js.stripe.com/v2/";
        stripeJsV2.onload = function()
        {
            cryozonic.onLoadStripeJsV2();
            callback();
        };
        stripeJsV2.onerror = function(evt) {
            console.warn("Stripe.js v2 could not be loaded");
            console.error(evt);
            callback();
        };
        script.parentNode.insertBefore(stripeJsV2, script);
    },
    loadStripeJsV3: function(callback)
    {
        var script = document.getElementsByTagName('script')[0];
        var stripeJsV3 = document.createElement('script');
        stripeJsV3.src = "https://js.stripe.com/v3/";
        stripeJsV3.onload = function()
        {
            cryozonic.onLoadStripeJsV3();
            if (typeof callback === 'function') {
                callback();
            }
        };
        stripeJsV3.onerror = function(evt) {
            console.warn("Stripe.js v3 could not be loaded");
            console.error(evt);
        };
        // Do this on the next cycle so that cryozonic.onLoadStripeJsV2() finishes first
        script.parentNode.insertBefore(stripeJsV3, script);
    },
    onLoadStripeJsV2: function()
    {
        if (!cryozonic.stripeJsV2)
        {
            Stripe.setPublishableKey(cryozonic.apiKey);
            cryozonic.stripeJsV2 = Stripe;
        }
    },
    onLoadStripeJsV3: function()
    {
        if (!cryozonic.stripeJsV3)
        {
            cryozonic.stripeJsV3 = Stripe(cryozonic.apiKey);
        }

        cryozonic.initLoadedStripeJsV3();
    },
    initLoadedStripeJsV3: function()
    {
        cryozonic.initStripeElements();
        cryozonic.onWindowLoaded(cryozonic.initStripeElements);

        cryozonic.initPaymentRequestButton();
        cryozonic.onWindowLoaded(cryozonic.initPaymentRequestButton);
    },
    onWindowLoaded: function(callback)
    {
        if (window.attachEvent)
            window.attachEvent("onload", callback); // IE
        else
            window.addEventListener("load", callback); // Other browsers
    },
    initReviewStepErrors: function()
    {
        if (typeof Review == 'undefined' || typeof Review.prototype.nextStep == 'undefined')
            return;

        var nextStep = Review.prototype.nextStep;
        Review.prototype.nextStep = function(transport){
            if (transport) {
                var response = transport.responseJSON || transport.responseText.evalJSON(true) || {};

                if (response.redirect) {
                    this.isSuccess = true;
                    location.href = encodeURI(response.redirect);
                    return;
                }
                if (response.success) {
                    this.isSuccess = true;
                    location.href = encodeURI(this.successUrl);
                }
                else{
                    var msg = response.error_messages;
                    if (Object.isArray(msg)) {
                        msg = msg.join("\n").stripTags().toString();
                    }
                    if (msg) {
                        cryozonic.displayReviewStepError(msg);
                    }
                }

                if (response.update_section) {
                    $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
                }

                if (response.goto_section) {
                    checkout.gotoSection(response.goto_section, true);
                }
            }
        };
        if (typeof review != 'undefined')
            review.nextStep = Review.prototype.nextStep;
    },
    displayReviewStepError: function(msg)
    {
        if (msg.indexOf("reusable source you provided is consumed") >= 0)
        {
            alert("Your card was declined");
            checkout.gotoSection("payment");
            cryozonic.displayCardError("Your card was declined", true);
            deleteStripeToken();
        }
        else if (msg.indexOf("card was declined") >= 0)
        {
            alert(msg);
            checkout.gotoSection("payment");
            cryozonic.displayCardError(msg, true);
            deleteStripeToken();
        }
        else
            alert(msg);
    },
    validatePaymentForm: function(callback)
    {
        if (!this.paymentFormValidator)
            this.paymentFormValidator = new Validation('payment_form_cryozonic_stripe');

        if (!this.paymentFormValidator.form)
            this.paymentFormValidator = new Validation('new-card');

        if (!this.paymentFormValidator.form)
            return true;

        this.paymentFormValidator.reset();

        result = this.paymentFormValidator.validate();

        // The Magento validator will try to pass over injected Stripe Elements, so to exclude those,
        // check if any of the form elements have a validation-failed class
        if (!result)
        {
            var failedElements = Form.getElements('payment_form_cryozonic_stripe').findAll(function(elm){
                return $(elm).hasClassName('validation-failed');
            });
            if (failedElements.length === 0)
                return true;
        }

        return result;
    },
    placeAdminOrder: function(e)
    {
        var radioButton = document.getElementById('p_method_cryozonic_stripe');
        if (radioButton && !radioButton.checked)
            return order.submit();

        createStripeToken(function(err)
        {
            if (err)
                alert(err);
            else
                order.submit();
        });
    },
    addAVSFieldsTo: function(cardDetails)
    {
        var owner = cryozonic.getSourceOwner();
        cardDetails.name = owner.name;
        cardDetails.address_line1 = owner.address.line1;
        cardDetails.address_line2 = owner.address.line2;
        cardDetails.address_zip = owner.address.postal_code;
        cardDetails.address_city = owner.address.city;
        cardDetails.address_state = owner.address.state;
        cardDetails.address_country = owner.address.country;
        return cardDetails;
    },
    getSourceOwner: function()
    {
        // Format is
        var owner = {
            name: null,
            email: null,
            phone: null,
            address: {
                city: null,
                country: null,
                line1: null,
                line2: null,
                postal_code: null,
                state: null
            }
        };

        // If there is an address select dropdown, don't read the address from the input fields in case
        // the customer changes the address from the dropdown. Dropdown value changes do not update the
        // plain input fields
        if (!document.getElementById('billing-address-select'))
        {
            // Scenario 1: We are in the admin area creating an order for a guest who has no saved address yet
            var line1 = document.getElementById('order-billing_address_street0');
            var postcode = document.getElementById('order-billing_address_postcode');
            var email = document.getElementById('order-billing_address_email');

            // Scenario 2: Checkout page with an OSC module and a guest customer
            if (!line1)
                line1 = document.getElementById('billing:street1');

            if (!postcode)
                postcode = document.getElementById('billing:postcode');

            if (!email)
                email = document.getElementById('billing:email');

            if (line1)
                owner.address.line1 = line1.value;

            if (postcode)
                owner.address.postal_code = postcode.value;

            if (email)
                owner.email = email.value;

            // New fields
            if (document.getElementById('billing:firstname'))
                owner.name = document.getElementById('billing:firstname').value + ' ' + document.getElementById('billing:lastname').value;

            if (document.getElementById('billing:telephone'))
                owner.phone = document.getElementById('billing:telephone').value;

            if (document.getElementById('billing:city'))
                owner.address.city = document.getElementById('billing:city').value;

            if (document.getElementById('billing:country_id'))
                owner.address.country = document.getElementById('billing:country_id').value;

            if (document.getElementById('billing:street2'))
                owner.address.line2 = document.getElementById('billing:street2').value;

            if (document.getElementById('billing:region'))
                owner.address.state = document.getElementById('billing:region').value;
        }

        // Scenario 3: Checkout or admin area and a registered customer already has a pre-loaded billing address
        if (cryozonic.billingInfo !== null)
        {
            if (owner.email === null && cryozonic.billingInfo.email !== null)
                owner.email = cryozonic.billingInfo.email;

            if (owner.address.line1 === null && cryozonic.billingInfo.line1 !== null)
                owner.address.line1 = cryozonic.billingInfo.line1;

            if (owner.address.postal_code === null && cryozonic.billingInfo.postcode !== null)
                owner.address.postal_code = cryozonic.billingInfo.postcode;

            // New fields
            if (owner.name === null && cryozonic.billingInfo.name !== null)
                owner.name = cryozonic.billingInfo.name;

            if (owner.phone === null && cryozonic.billingInfo.phone !== null)
                owner.phone = cryozonic.billingInfo.phone;

            if (owner.address.city === null && cryozonic.billingInfo.city !== null)
                owner.address.city = cryozonic.billingInfo.city;

            if (owner.address.country === null && cryozonic.billingInfo.country !== null)
                owner.address.country = cryozonic.billingInfo.country;

            if (owner.address.line2 === null && cryozonic.billingInfo.line2 !== null)
                owner.address.line2 = cryozonic.billingInfo.line2;

            if (owner.address.state === null && cryozonic.billingInfo.state !== null)
                owner.address.state = cryozonic.billingInfo.state;
        }

        if (!owner.phone)
            delete owner.phone;

        return owner;
    },
    displayCardError: function(message, inline)
    {
        // Some OSC modules have the Place Order button away from the payment form
        if (cryozonic.oscInitialized && typeof inline == 'undefined')
        {
            alert(message);
            return;
        }

        // When we use a saved card, display the message as an alert
        var newCardRadio = document.getElementById('new_card');
        if (newCardRadio && !newCardRadio.checked)
        {
            alert(message);
            return;
        }

        var box = $('cryozonic-stripe-card-errors');

        if (box)
        {
            box.update(message);
            box.addClassName('populated');
        }
        else
            alert(message);
    },
    clearCardErrors: function()
    {
        var box = $('cryozonic-stripe-card-errors');

        if (box)
        {
            box.update("");
            box.removeClassName('populated');
        }
    },
    is3DSecureEnabled: function()
    {
        return (typeof params3DSecure != 'undefined' && params3DSecure && !isNaN(params3DSecure.amount));
    },
    isApplePayEnabled: function()
    {
        // Some OSC modules will refuse to reload the payment method when the billing address is changed for a customer.
        // We can't use Apple Pay without a billing address
        if (typeof paramsApplePay == "undefined" || !paramsApplePay)
            return false;

        return true;
    },
    hasNoCountryCode: function()
    {
        return (typeof paramsApplePay.country == "undefined" || !paramsApplePay.country || paramsApplePay.country.length === 0);
    },
    getCountryElement: function()
    {
        var element = document.getElementById('billing:country_id');

        if (!element)
            element = document.getElementById('billing_country_id');

        if (!element)
        {
            var selects = document.getElementsByName('billing[country_id]');
            if (selects.length > 0)
                element = selects[0];
        }

        return element;
    },
    getCountryCode: function()
    {
        var element = cryozonic.getCountryElement();

        if (!element)
            return null;

        if (element.value && element.value.length > 0)
            return element.value;

        return null;
    },
    initResetButton: function()
    {
        var resetButton = document.getElementById('apple-pay-reset');
        resetButton.addEventListener('click', resetApplePayToken);
    },
    getStripeElementsStyle: function()
    {
        // Custom styling can be passed to options when creating an Element.
        return {
            base: {
                // Add your base input styles here. For example:
                fontSize: '16px',
                lineHeight: '24px'
                // iconColor: '#c4f0ff',
                // color: '#31325F'
        //         fontWeight: 300,
        //         fontFamily: '"Helvetica Neue", Helvetica, sans-serif',

        //         '::placeholder': {
        //             color: '#CFD7E0'
        //         }
            }
        };
    },
    getStripeElementCardNumberOptions: function()
    {
        return {
            // iconStyle: 'solid',
            // hideIcon: false,
            style: cryozonic.getStripeElementsStyle()
        };
    },
    getStripeElementCardExpiryOptions: function()
    {
        return {
            style: cryozonic.getStripeElementsStyle()
        };
    },
    getStripeElementCardCvcOptions: function()
    {
        return {
            style: cryozonic.getStripeElementsStyle()
        };
    },
    getStripeElementsOptions: function()
    {
        return {
            locale: 'auto'
        };
    },
    initStripeElements: function()
    {
        if (cryozonic.securityMethod != 2)
            return;

        if (document.getElementById('cryozonic-stripe-card-number') === null)
            return;

        var elements = cryozonic.stripeJsV3.elements(cryozonic.getStripeElementsOptions());

        var cardNumber = cryozonic.card = elements.create('cardNumber', cryozonic.getStripeElementCardNumberOptions());
        cardNumber.mount('#cryozonic-stripe-card-number');
        cardNumber.addEventListener('change', cryozonic.stripeElementsOnChange);

        var cardExpiry = cryozonic.card = elements.create('cardExpiry', cryozonic.getStripeElementCardExpiryOptions());
        cardExpiry.mount('#cryozonic-stripe-card-expiry');
        cardExpiry.addEventListener('change', cryozonic.stripeElementsOnChange);

        var cardCvc = cryozonic.card = elements.create('cardCvc', cryozonic.getStripeElementCardCvcOptions());
        cardCvc.mount('#cryozonic-stripe-card-cvc');
        cardCvc.addEventListener('change', cryozonic.stripeElementsOnChange);
    },
    stripeElementsOnChange: function(event)
    {
        if (typeof event.brand != 'undefined')
            cryozonic.onCardNumberChanged(event.brand);

        if (event.error)
            cryozonic.displayCardError(event.error.message, true);
        else
            cryozonic.clearCardErrors();
    },
    onCardNumberChanged: function(cardType)
    {
        cryozonic.onCardNumberChangedFade(cardType);
        cryozonic.onCardNumberChangedSwapIcon(cardType);
    },
    resetIconsFade: function()
    {
        cryozonic.iconsContainer.className = 'input-box';
        var children = cryozonic.iconsContainer.getElementsByTagName('img');
        for (var i = 0; i < children.length; i++)
            children[i].className = '';
    },
    onCardNumberChangedFade: function(cardType)
    {
        if (!cryozonic.iconsContainer)
            cryozonic.iconsContainer = document.getElementById('cryozonic-stripe-accepted-cards');

        if (!cryozonic.iconsContainer)
            return;

        cryozonic.resetIconsFade();

        if (!cardType || cardType == "unknown") return;

        var img = document.getElementById('cryozonic_stripe_' + cardType + '_type');
        if (!img) return;

        img.className = 'active';
        cryozonic.iconsContainer.className = 'input-box cryozonic-stripe-detected';
    },
    cardBrandToPfClass: {
        'visa': 'pf-visa',
        'mastercard': 'pf-mastercard',
        'amex': 'pf-american-express',
        'discover': 'pf-discover',
        'diners': 'pf-diners',
        'jcb': 'pf-jcb',
        'unknown': 'pf-credit-card',
    },
    onCardNumberChangedSwapIcon: function(cardType)
    {
        var brandIconElement = document.getElementById('cryozonic-stripe-brand-icon');
        var pfClass = 'pf-credit-card';
        if (cardType in cryozonic.cardBrandToPfClass)
            pfClass = cryozonic.cardBrandToPfClass[cardType];

        for (var i = brandIconElement.classList.length - 1; i >= 0; i--)
            brandIconElement.classList.remove(brandIconElement.classList[i]);

        brandIconElement.classList.add('pf');
        brandIconElement.classList.add(pfClass);
    },
    initPaymentRequestButton: function()
    {
        if (!cryozonic.isApplePayEnabled())
            return;

        if (cryozonic.hasNoCountryCode())
            paramsApplePay.country = cryozonic.getCountryCode();

        if (cryozonic.hasNoCountryCode())
            return;

        var paymentRequest;
        try
        {
            paymentRequest = cryozonic.stripeJsV3.paymentRequest(paramsApplePay);
            var elements = cryozonic.stripeJsV3.elements();
            var prButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest,
            });
        }
        catch (e)
        {
            console.warn(e.message);
            return;
        }

        // Check the availability of the Payment Request API first.
        paymentRequest.canMakePayment().then(function(result)
        {
            if (result)
            {
                prButton.mount('#payment-request-button');
                $('payment_form_cryozonic_stripe').addClassName('payment-request-api-supported');
                cryozonic.initResetButton();
            }
        });

        paymentRequest.on('token', function(result)
        {
            try
            {
                setStripeToken(result.token.id);
                setApplePayToken(result.token);
                result.complete('success');
            }
            catch (e)
            {
                result.complete('fail');
            }
        });
    },
    isPaymentMethodSelected: function()
    {
        if (typeof payment != 'undefined' && typeof payment.currentMethod != 'undefined' && payment.currentMethod.length > 0)
            return payment.currentMethod == 'cryozonic_stripe';
        else
        {
            var radioButton = document.getElementById('p_method_cryozonic_stripe');
            if (radioButton && !radioButton.checked)
                return false;

            return true;
        }
    },
    selectMandate: function()
    {
        document.getElementById('cryozonic_europayments_sepa_iban').classList.remove("required-entry");
    },
    selectNewIBAN: function()
    {
        document.getElementById('new_mandate').checked = 1;
        document.getElementById('cryozonic_europayments_sepa_iban').classList.add("required-entry");
    },
    setLoadWaiting: function(section)
    {
        // Check if defined first in case of an OSC module rewriting the whole thing
        if (typeof checkout != 'undefined' && checkout && checkout.setLoadWaiting)
        {
            try
            {
                // OSC modules may also cause crashes if they have stripped away the html elements
                checkout.setLoadWaiting(section);
            }
            catch (e) {}
        }
        else
            cryozonicToggleAdminSave(section);
    }
};

var initAdmin = function()
{
    var btn = document.getElementById('order-totals');
    if (btn) btn = btn.getElementsByTagName('button');
    if (btn && btn[0]) btn = btn[0];
    if (btn) btn.onclick = cryozonic.placeAdminOrder;

    var topBtns = document.getElementsByClassName('save');
    for (var i = 0; i < topBtns.length; i++)
    {
        topBtns[i].onclick = cryozonic.placeAdminOrder;
    }
};

var shouldUse3DSecure = function(response)
{
    return (cryozonic.is3DSecureEnabled() &&
            typeof response.card.three_d_secure != 'undefined' &&
            ['optional', 'required'].indexOf(response.card.three_d_secure) >= 0);
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

var beginApplePay = function()
{
    var paymentRequest = paramsApplePay;

    var countryCode = cryozonic.getCountryCode();
    if (countryCode && countryCode != paymentRequest.countryCode)
    {
        // In some cases with OSC modules, the country may change without having the payment form reloaded
        paymentRequest.countryCode = countryCode;
    }

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
    cryozonic.applePaySuccess = true;
    cryozonic.applePayToken = token;
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
    cryozonic.applePaySuccess = false;
    cryozonic.applePayToken = null;
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

    cardDetails = cryozonic.addAVSFieldsTo(cardDetails);

    return cardDetails;
};

var createStripeToken = function(callback)
{
    cryozonic.clearCardErrors();

    if (!cryozonic.validatePaymentForm())
        return;

    cryozonic.setLoadWaiting('payment');
    var done = function(err)
    {
        cryozonic.setLoadWaiting(false);
        return callback(err);
    };

    if (cryozonic.applePaySuccess)
        return done();

    // First check if the "Use new card" radio is selected, return if not
    var cardDetails, newCardRadio = document.getElementById('new_card');
    if (newCardRadio && !newCardRadio.checked)
        return done();

    // Stripe.js v3 + Stripe Elements
    if (cryozonic.securityMethod == 2)
    {
        try
        {
            cryozonic.stripeJsV3.createSource(cryozonic.card, { owner: cryozonic.getSourceOwner() }).then(function(result)
            {
                if (result.error)
                    return done(result.error.message);

                var cardKey = result.source.id;
                var token = result.source.id + ':' + result.source.card.brand + ':' + result.source.card.last4;
                stripeTokens[cardKey] = token;
                setStripeToken(token);

                return done();
            });
        }
        catch (e)
        {
            return done(e.message);
        }
    }
    // Stripe.js v2
    else
    {
        cardDetails = getCardDetails();

        if (!cardDetails)
            return done('Invalid card details');

        var cardKey = JSON.stringify(cardDetails);

        if (stripeTokens[cardKey])
        {
            setStripeToken(stripeTokens[cardKey]);
            return done();
        }
        else
            deleteStripeToken();

        try
        {
            Stripe.card.createToken(cardDetails, function (status, response)
            {
                if (response.error)
                    return done(response.error.message);

                var token = response.id + ':' + response.card.brand + ':' + response.card.last4;
                stripeTokens[cardKey] = token;
                setStripeToken(token);
                return done();
            });
        }
        catch (e)
        {
            return done(e.message);
        }
    }
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
    } catch (e) {}
}

function deleteStripeToken()
{
    var input, inputs = document.getElementsByClassName('cryozonic-stripejs-token');
    if (inputs && inputs[0]) input = inputs[0];
    if (input && input.parentNode) input.parentNode.removeChild(input);
}

// Multi-shipping form support for Stripe.js
var multiShippingForm = null, multiShippingFormSubmitButton = null;

function submitMultiShippingForm(e)
{
    if (!cryozonic.isPaymentMethodSelected())
        return true;

    if (e.preventDefault) e.preventDefault();

    if (!multiShippingFormSubmitButton) multiShippingFormSubmitButton = document.getElementById('payment-continue');
    if (multiShippingFormSubmitButton) multiShippingFormSubmitButton.disabled = true;

    createStripeToken(function(err)
    {
        if (multiShippingFormSubmitButton) multiShippingFormSubmitButton.disabled = false;

        if (err)
            cryozonic.displayCardError(err);
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
        cryozonic.multiShippingFormInitialized)
        return;

    multiShippingForm = document.getElementById(payment.formId);
    if (!multiShippingForm) return;

    if (multiShippingForm.attachEvent)
        multiShippingForm.attachEvent("submit", submitMultiShippingForm);
    else
        multiShippingForm.addEventListener("submit", submitMultiShippingForm);

    cryozonic.multiShippingFormInitialized = true;
};

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
    {
        deleteStripeToken();
        disablePaymentFormValidation();
    }
};

var toggleValidation = function(evt)
{
    $('new_card').removeClassName('validate-one-required-by-name');
    if (evt.target.value == 'cryozonic_stripe')
        $('new_card').addClassName('validate-one-required-by-name');
};

var initSavedCards = function(isAdmin)
{
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

            if (err)
                cryozonic.displayCardError(err);
            else
                document.getElementById("new-card").submit();

        });
        return false;
    }

    return true;
};

var initOSCModules = function()
{
    if (cryozonic.oscInitialized) return;

    // Front end bindings
    if (typeof IWD != "undefined" && typeof IWD.OPC != "undefined")
    {
        // IWD OnePage Checkout override, which is a tad of a mess
        var proceed = function()
        {
            if (typeof $j == 'undefined') // IWD 4.0.4
                $j = $j_opc; // IWD 4.0.8

            var form = $j('#co-payment-form').serializeArray();
            IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
        };

        IWD.OPC.savePayment = function()
        {
            if (!IWD.OPC.saveOrderStatus)
                return;

            if (IWD.OPC.Checkout.xhr !== null)
                IWD.OPC.Checkout.xhr.abort();

            IWD.OPC.Checkout.lockPlaceOrder();

            if (!cryozonic.isPaymentMethodSelected())
                return proceed();

            createStripeToken(function(err)
            {
                IWD.OPC.Checkout.xhr = null;
                IWD.OPC.Checkout.unlockPlaceOrder();

                if (err)
                {
                    IWD.OPC.Checkout.hideLoader();
                    cryozonic.displayCardError(err);
                }
                else
                    proceed();
            });
        };
        cryozonic.oscInitialized = true;
    }
    // Magik OneStepCheckout v1.0.1
    else if (typeof MGKOSC != "undefined")
    {
        window.addEventListener("load", function()
        {
            var proceed = checkout.save.bind(checkout);
            checkout.save = function(element)
            {
                if (!cryozonic.isPaymentMethodSelected())
                    return proceed();

                createStripeToken(function(err)
                {
                    if (err)
                        cryozonic.displayCardError(err);
                    else
                        proceed();
                });
            };
            cryozonic.oscInitialized = true;
        });
    }
    // MageCloud Clarion OSC v1.0.2
    else if ($('onestepcheckout_orderform') && $$('.btn-checkout').length > 0)
    {
        var checkoutButton = $$('.btn-checkout').pop();
        checkoutButton.onclick = function(e)
        {
            e.preventDefault();

            if (!cryozonic.isPaymentMethodSelected())
                return checkout.save();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    checkout.save();
            });
        };
        cryozonic.oscInitialized = true;
    }
    else if ($('onestep_form'))
    {
        // MageWorx OneStepCheckoutPro v3.4.4
        var initOSC = function()
        {
            OneStep.Views.Init.prototype._updateOrder = OneStep.Views.Init.prototype.updateOrder;
            OneStep.Views.Init.prototype.updateOrder = function()
            {
                if (!cryozonic.isPaymentMethodSelected())
                    return this._updateOrder();

                var self = this;

                this.$el.find("#review-please-wait").show();
                window.OneStep.$('.btn-checkout').attr('disabled','disabled');

                createStripeToken(function(err)
                {
                    if (err)
                    {
                        self.$el.find("#review-please-wait").hide();
                        window.OneStep.$('.btn-checkout').removeAttr('disabled');
                        cryozonic.displayCardError(err);
                    }
                    else
                        self._updateOrder();
                });

            };
        };

        window.addEventListener("load", initOSC);

        cryozonic.oscInitialized = true;
    }
    // FancyCheckout 1.2.6
    else if ($('fancycheckout_orderform'))
    {
        var placeOrderButton = $$('button.btn-checkout')[0];
        if (!placeOrderButton)
            return;

        placeOrderButton.onclick = function(e)
        {
            if(!billingForm.validator.validate())
                return;

            jQuery('#control_overlay').show();
            jQuery('.opc_wrapper').css('opacity','0.5');

            e.preventDefault();

            if (!cryozonic.isPaymentMethodSelected())
                return billingForm.submit();

            createStripeToken(function(err)
            {
                if (err)
                {
                    cryozonic.displayCardError(err);
                    jQuery('#control_overlay').hide();
                    jQuery('.opc_wrapper').css('opacity','1');
                }
                else
                    billingForm.submit();
            });
        };

        cryozonic.oscInitialized = true;
    }
    else if ($('onestepcheckout-form'))
    {
        // MageBay OneStepCheckout 1.1.5
        // Idev OneStepCheckout 4.5.4
        var setLoading = function(flag)
        {
            if (typeof jQuery == 'undefined')
                return;

            var placeOrderButton = $('onestepcheckout-place-order');
            if (!placeOrderButton)
                return;

            var loading = jQuery('.onestepcheckout-place-order-loading');

            if (flag == true)
            {
                if (loading.length > 0)
                    loading.remove();

                /* Disable button to avoid multiple clicks */
                placeOrderButton.removeClassName('orange').addClassName('grey');
                placeOrderButton.disabled = true;

                var loaderelement = new Element('span').
                    addClassName('onestepcheckout-place-order-loading').
                    update('Please wait, processing your order...');

                placeOrderButton.parentNode.appendChild(loaderelement);
            }
            else
            {
                location.reload();

                if (loading.length > 0)
                    loading.remove();

                placeOrderButton.disabled = false;
            }
        }

        var initIdevOSC = function()
        {
            if (typeof $('onestepcheckout-form').proceed != 'undefined')
                return;

            $('onestepcheckout-form').proceed = $('onestepcheckout-form').submit;
            $('onestepcheckout-form').submit = function(e)
            {
                if (!cryozonic.isPaymentMethodSelected())
                    return $('onestepcheckout-form').proceed();

                setLoading(true);

                createStripeToken(function(err)
                {
                    if (err)
                    {
                        cryozonic.displayCardError(err);
                        setLoading(false);
                    }
                    else
                        $('onestepcheckout-form').proceed();
                });
            };

            // Idev OneStepCheckout 4.1.0
            if (typeof submitOsc != 'undefined' && typeof $('onestepcheckout-form')._submitOsc == 'undefined')
            {
                $('onestepcheckout-form')._submitOsc = submitOsc;
                submitOsc = function(form, url, message, image)
                {
                    if (!cryozonic.isPaymentMethodSelected())
                        return $('onestepcheckout-form')._submitOsc(form, url, message, image);

                    setLoading(true);

                    createStripeToken(function(err)
                    {
                        if (err)
                        {
                            cryozonic.displayCardError(err);
                            setLoading(false);
                        }
                        else
                            $('onestepcheckout-form')._submitOsc(form, url, message, image);
                    });
                };
            }
        };

        // This is triggered when the billing address changes and the payment method is refreshed
        window.addEventListener("load", initIdevOSC);

        cryozonic.oscInitialized = true;
    }
    else if (typeof AWOnestepcheckoutForm != 'undefined')
    {
        // AheadWorks OneStepCheckout 1.3.5
        AWOnestepcheckoutForm.prototype.__sendPlaceOrderRequest = AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest;
        AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return this.__sendPlaceOrderRequest();

            var me = this;
            createStripeToken(function(err)
            {
                if (err)
                {
                    cryozonic.displayCardError(err);
                    try
                    {
                        me.enablePlaceOrderButton();
                        me.hidePleaseWaitNotice();
                        me.hideOverlay();
                    }
                    catch (e) {}
                }
                else
                    me.__sendPlaceOrderRequest();
            });
        };
        AWOnestepcheckoutPayment.prototype._savePayment = AWOnestepcheckoutPayment.prototype.savePayment;
        AWOnestepcheckoutPayment.prototype.savePayment = function() {
            if (cryozonic.isPaymentMethodSelected())
                return;
            else
                return this._savePayment();
        };
        cryozonic.oscInitialized = true;
    }
    // NextBits OneStepCheckout 1.0.3
    else if (typeof checkoutnext != 'undefined' && typeof Review.prototype.proceed == 'undefined')
    {
        Review.prototype.proceed = Review.prototype.save;
        Review.prototype.save = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return this.proceed();

            var me = this;
            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    me.proceed();
            });
        };

        if (typeof review != 'undefined')
            review.save = Review.prototype.save;

        cryozonic.oscInitialized = true;
    }
    // Magecheckout OSC 2.2.1
    else if (typeof MagecheckoutSecuredCheckoutPaymentMethod != 'undefined')
    {
        // Disable saving of payment details
        MagecheckoutSecuredCheckoutPaymentMethod.prototype._savePaymentMethod = MagecheckoutSecuredCheckoutPaymentMethod.prototype.savePaymentMethod;
        MagecheckoutSecuredCheckoutPaymentMethod.prototype.savePaymentMethod = function()
        {
            if (this.currentMethod != 'cryozonic_stripe')
                return this._savePaymentMethod();
        };

        if (typeof securedCheckoutPaymentMethod != 'undefined')
        {
            securedCheckoutPaymentMethod._savePaymentMethod = MagecheckoutSecuredCheckoutPaymentMethod.prototype._savePaymentMethod;
            securedCheckoutPaymentMethod.savePaymentMethod = MagecheckoutSecuredCheckoutPaymentMethod.prototype.savePaymentMethod;
        }

        MagecheckoutSecuredCheckoutForm.prototype._placeOrderProcess = MagecheckoutSecuredCheckoutForm.prototype.placeOrderProcess;
        MagecheckoutSecuredCheckoutForm.prototype.placeOrderProcess = function ()
        {
            var self = this;

            if (!cryozonic.isPaymentMethodSelected())
                return this._placeOrderProcess();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    self._placeOrderProcess();
            });
        };

        if (typeof securedCheckoutForm != 'undefined')
        {
            securedCheckoutForm._placeOrderProcess = MagecheckoutSecuredCheckoutForm.prototype._placeOrderProcess;
            securedCheckoutForm.placeOrderProcess = MagecheckoutSecuredCheckoutForm.prototype.placeOrderProcess;
        }

        cryozonic.oscInitialized = true;
    }
    // Lotusbreath OneStepCheckout 4.2.0
    else if (typeof oscObserver != 'undefined' && typeof oscObserver.register != 'undefined')
    {
        window.validToken = false;

        oscObserver.register('beforeSubmitOrder', function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return;

            if (window.validToken)
                return;

            oscObserver.stopSubmittingOrder = true;

            createStripeToken(function(err)
            {
                oscObserver.stopSubmittingOrder = false;

                if (err)
                {
                    window.validToken = false;
                    cryozonic.displayCardError(err, true);
                }
                else
                {
                    window.validToken = true;
                    $('lbonepage-place-order-btn').click();
                }
            });

        });

        cryozonic.oscInitialized = true;
    }
    // FireCheckout 3.2.0
    else if ($('firecheckout-form'))
    {
        var fireCheckoutPlaceOrder = function()
        {
            var self = this;

            if (!cryozonic.isPaymentMethodSelected())
                return self.proceed();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err, true);
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
                    var button = btnCheckout[i];
                    button.proceed = button.onclick;
                    button.onclick = fireCheckoutPlaceOrder;
                }
            }
        });
        cryozonic.oscInitialized = true;
    }
    else if (typeof MagegiantOneStepCheckoutForm != 'undefined')
    {
        // MageGiant OneStepCheckout 4.0.0
        MagegiantOneStepCheckoutForm.prototype.__placeOrderRequest = MagegiantOneStepCheckoutForm.prototype._placeOrderRequest;
        MagegiantOneStepCheckoutForm.prototype._placeOrderRequest = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return this.__placeOrderRequest();

            var me = this;
            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    me.__placeOrderRequest();
            });
        };

        // Disable savePaymentMethod ajax call
        MagegiantOnestepcheckoutPaymentMethod.prototype._savePaymentMethod = MagegiantOnestepcheckoutPaymentMethod.prototype.savePaymentMethod;
        MagegiantOnestepcheckoutPaymentMethod.prototype.savePaymentMethod = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return this._savePaymentMethod();
        };
        cryozonic.oscInitialized = true;
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
                if (err)
                    cryozonic.displayCardError(err);
                else
                    proceed(element);
            });
        };
        cryozonic.oscInitialized = true;
    }
    // GoMage LightCheckout 5.9
    else if (typeof checkout != 'undefined' && typeof checkout.LightcheckoutSubmit != 'undefined')
    {
        checkout._LightcheckoutSubmit = checkout.LightcheckoutSubmit;
        checkout.LightcheckoutSubmit = function()
        {

            if (!cryozonic.isPaymentMethodSelected())
                return checkout._LightcheckoutSubmit();

            createStripeToken(function(err)
            {
                if (err)
                {
                    cryozonic.displayCardError(err);
                    checkout.showLoadinfo();
                    location.reload();
                }
                else
                    checkout._LightcheckoutSubmit();
            });
        };
        cryozonic.oscInitialized = true;
    }
    // Amasty OneStepCheckout 3.0.5
    else if ($('amscheckout-submit') && typeof completeCheckout != 'undefined')
    {
        $('amscheckout-submit').onclick = function(el)
        {
            if (!cryozonic.isPaymentMethodSelected())
                return completeCheckout();

            showLoading();
            createStripeToken(function(err)
            {
                hideLoading();
                if (err)
                    cryozonic.displayCardError(err);
                else
                    completeCheckout();
            });
        };
        cryozonic.oscInitialized = true;
    }
    else if ((typeof Review != 'undefined' && typeof Review.prototype.proceed == 'undefined') && (
        // Magesolution Athlete Ultimate Magento Theme v1.1.2
        $('oscheckout-form') ||
        // PlumRocket OneStepCheckout 1.3.4
        ($('submit-chackout') && $('submit-chackout-top')) ||
        // Apptha 1StepCheckout v1.9
        (typeof closeLink1 != 'undefined')
    ))
    {
        Review.prototype.proceed = Review.prototype.save;

        Review.prototype.save = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return review.proceed();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    review.proceed();
            });
        };

        if (typeof review != 'undefined')
            review.save = Review.prototype.save;
    }
    else if (typeof OSCheckout != 'undefined' && typeof OSCheckout.prototype.proceed == 'undefined')
    {
        // AdvancedCheckout OSC 2.5.0
        OSCheckout.prototype.proceed = OSCheckout.prototype.placeOrder;
        OSCheckout.prototype.placeOrder = function()
        {
            // Payment is not defined
            if (!cryozonic.isPaymentMethodSelected())
                return this.proceed();

            var me = this;
            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    me.proceed();
            });
        };

        if (typeof oscheckout != 'undefined')
        {
            oscheckout.proceed = OSCheckout.prototype.proceed;
            oscheckout.placeOrder = OSCheckout.prototype.placeOrder;
        }

        cryozonic.oscInitialized = true;
    }
    // Aitoc All-In-One Checkout v1.1.0
    else if ($('aitcheckout-place-order'))
    {
        var proceed = $('aitcheckout-place-order').onclick;

        $('aitcheckout-place-order').onclick = function()
        {
            if (!cryozonic.isPaymentMethodSelected())
                return proceed();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    proceed();
            });
        }

        cryozonic.oscInitialized = true;
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
            if (!cryozonic.isPaymentMethodSelected())
                return payment.proceed();

            createStripeToken(function(err)
            {
                if (err)
                    cryozonic.displayCardError(err);
                else
                    payment.proceed();
            });
        };

        if (typeof payment != 'undefined')
            payment.save = Payment.prototype.save;
    }
};

