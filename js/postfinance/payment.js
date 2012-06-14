Event.observe(window, 'load', function() {
    payment.save = payment.save.wrap(function(originalSaveMethod) {
        payment.originalSaveMethod = originalSaveMethod;

        var postfinanceValidator = new Validation($('co-payment-form'));
        if (!postfinanceValidator.validate()) {
            return;
        }
        if ('postfinance_cc' == payment.currentMethod) {
            payment.savePostFinanceCcBrand();
        }

        originalSaveMethod();
    });

    payment.savePostFinanceCcBrand = function() {
        checkout.setLoadWaiting('payment');
        var owner = $('POSTFINANCE_CC_CN').value;
        new Ajax.Request(postfinanceSaveCcBrandUrl, {
            method: 'post',
            parameters: { brand : $('POSTFINANCE_CC_BRAND').value, cn: owner },
            onSuccess: function(transport) {
                if (-1 < postfinanceCcBrandsForAliasInterface.indexOf($('POSTFINANCE_CC_BRAND').value)) {
                    payment.requestPostFinanceCcAlias();
                } else {
                    checkout.setLoadWaiting(false);
                }
                payment.originalSaveMethod();
            },
            onFailure: function(transport) {
                alert(Translator.translate('Payment failed. Please select another payment method.'));
            }
        });
    };

    payment.requestPostFinanceCcAlias = function() {
        checkout.setLoadWaiting('payment');

        var iframe = $('postfinance_iframe_' + payment.currentMethod);
        var doc = null;

        if(iframe.contentDocument) {
            doc = iframe.contentDocument;
        } else if(iframe.contentWindow) {
            doc = iframe.contentWindow.document;
        } else if(iframe.document) {
            doc = iframe.document;
        }

        doc.body.innerHTML="";
        iframe.alreadySet = false;

        if ('true' != iframe.alreadySet) {
            form = doc.createElement('form');
            form.id = 'postfinance_request_form';
            form.method = 'post';
            form.action = url; 
            submit = doc.createElement('submit');
            form.appendChild(submit);
                                                    
            var cardholder = doc.createElement('input');
            cardholder.id = 'CN';
            cardholder.name = 'CN';
            cardholder.value = $('POSTFINANCE_CC_CN').value;

            var cardnumber = doc.createElement('input');
            cardnumber.id = 'CARDNO';
            cardnumber.name = 'CARDNO';
            cardnumber.value = $('POSTFINANCE_CC_CARDNO').value;

            var verificationCode = doc.createElement('input');
            verificationCode.id = 'CVC';
            verificationCode.name = 'CVC';
            verificationCode.value = $('POSTFINANCE_CC_CVC').value;

            var brandElement = doc.createElement('input');
            brandElement.id = 'BRAND';
            brandElement.name = 'BRAND';
            brandElement.value = $('POSTFINANCE_CC_BRAND').value;

            var edElement = doc.createElement('input');
            edElement.id = 'ED';
            edElement.name = 'ED';
            edElement.value = $('POSTFINANCE_CC_ECOM_CARDINFO_EXPDATE_MONTH').value + $('POSTFINANCE_CC_ECOM_CARDINFO_EXPDATE_YEAR').value;

            var pspidElement = doc.createElement('input');
            pspidElement.id = 'PSPID';
            pspidElement.name = 'PSPID';
            pspidElement.value = pspid;

            var orderIdElement = doc.createElement('input');
            orderIdElement.name = 'ORDERID';
            orderIdElement.id = 'ORDERID';
            orderIdElement.value = orderId;

            var acceptUrlElement = doc.createElement('input');
            acceptUrlElement.name = 'ACCEPTURL';
            acceptUrlElement.id = 'ACCEPTURL';
            acceptUrlElement.value = acceptUrl;

            var exceptionUrlElement = doc.createElement('input');
            exceptionUrlElement.name = 'EXCEPTIONURL';
            exceptionUrlElement.id = 'EXCEPTIONURL';
            exceptionUrlElement.value = exceptionUrl;

            var paramplusElement = doc.createElement('input');
            paramplusElement.name = 'PARAMPLUS';
            paramplusElement.id = 'PARAMPLUS';
            paramplusElement.value = 'RESPONSEFORMAT=JSON';

            var aliasElement = doc.createElement('input');
            aliasElement.name = 'ALIAS';
            aliasElement.id = 'ALIAS';
            aliasElement.value = alias;

            form.appendChild(pspidElement);
            form.appendChild(brandElement);
            form.appendChild(cardholder);
            form.appendChild(cardnumber);
            form.appendChild(verificationCode);
            form.appendChild(edElement);
            form.appendChild(acceptUrlElement);
            form.appendChild(exceptionUrlElement);
            form.appendChild(orderIdElement);
            form.appendChild(paramplusElement);
            form.appendChild(aliasElement);

            var hash = doc.createElement('input');
            hash.id = 'SHASIGN';
            hash.name = 'SHASIGN';

            new Ajax.Request(hashUrl, {
                method: 'get',
                parameters: { brand: brandElement.value, orderid: orderId, paramplus: paramplusElement.value, alias: aliasElement.value },
                onSuccess: function(transport) {
                    var data = transport.responseText.evalJSON();
                    hash.value = data.hash;

                    form.appendChild(hash);
                    doc.body.appendChild(form);
                    iframe.alreadySet = 'true';

                    form.submit();

                    doc.body.innerHTML = '{ "result" : "waiting" }';
                    setTimeout("payment.processPostFinanceResponse()", 5000);
                }
            });
        }
    };

    payment.processPostFinanceResponse = function() {
        var responseIframe = $('postfinance_iframe_' + payment.currentMethod);
        var responseResult;
        if(responseIframe.contentDocument) {
            responseResult = responseIframe.contentDocument;
        } else if(responseIframe.contentWindow) {
            responseResult = responseIframe.contentWindow.document;
        } else if(responseIframe.document) {
            responseResult = responseIframe.document;
        }
        
        //Remove links in JSON response
        //can happen f.e. on iPad <a href="tel:0301125679">0301125679</a> if alias is interpreted as a phone number
        var htmlResponse = responseResult.body.innerHTML.replace(/<a\b[^>]*>/i, '');
        htmlResponse = htmlResponse.replace(/<\/a>/i, '');

        if ("undefined" == typeof(responseResult)) {
            currentStatus = '{ "result" : "failure" }'.evalJSON();
        } else {
            var currentStatus = htmlResponse.evalJSON();
            if ("undefined" == typeof(currentStatus.result)) {
                currentStatus = '{ "result" : "failure" }'.evalJSON();
            }
        }

        if ('success' == currentStatus.result) {

            new Ajax.Request(postfinanceCcSaveAliasUrl, {
                method: 'post',
                parameters: { alias : currentStatus.alias },
                onSuccess: function(transport) {
                    var data = transport.responseText;
                    checkout.setLoadWaiting(false);
                    payment.stashCcData();
                    payment.originalSaveMethod();
                },
                onFailure: function(transport) {
                    payment.applyStashedCcData();
                }
            });

            return true;
        }

        alert(Translator.translate('Payment failed. Please review your input or select another payment method.'));
        checkout.setLoadWaiting(false);
        return false;
    };

    payment.criticalPostFinanceCcData = ['CN', 'CARDNO', 'CVC'];
    payment.stashedPostFinanceCcData = new Array();

    payment.stashCcData = function() {
        payment.criticalPostFinanceCcData.each(function(item) {
            if (!payment.stashedPostFinanceCcData[item] || $('POSTFINANCE_CC_' + item).value.length) {
                payment.stashedPostFinanceCcData[item] = $('POSTFINANCE_CC_' + item).value;
                $('POSTFINANCE_CC_' + item).removeClassName('required-entry');
                $('POSTFINANCE_CC_' + item).value = '';
                $('POSTFINANCE_CC_' + item).disable();
            }
        });
    };

    payment.applyStashedCcData = function() {
        payment.criticalPostFinanceCcData.each(function(item) {
            if ($('POSTFINANCE_CC_' + item)) {
                if (payment.stashedPostFinanceCcData[item] && 0 < payment.stashedPostFinanceCcData[item].length) {
                    $('POSTFINANCE_CC_' + item).value = payment.stashedPostFinanceCcData[item];
                }
                $('POSTFINANCE_CC_' + item).addClassName('required-entry');
                $('POSTFINANCE_CC_' + item).enable();
            }
        });
    };

    payment.togglePostFinanceDirectDebitInputs = function(country) {
        var cn = 'postfinance_directdebit_CN';
        var bankcode = 'postfinance_directdebit_bank_code';
        var showInput = function(id) {
            $$('#' + id)[0].up().show();
            $(id).addClassName('required-entry');
        };
        var hideInput = function(id) {
            $$('#' + id)[0].up().hide();
            $(id).removeClassName('required-entry');
        };
        if ('NL' == country) {
            showInput(cn);
            hideInput(bankcode);
        }
        if ('DE' == country || 'AT' == country) {
            hideInput(cn);
            showInput(bankcode);
        }
    };

    payment.togglePostFinanceCcInputs = function() {
        if (-1 < postfinanceCcBrandsForAliasInterface.indexOf($('POSTFINANCE_CC_BRAND').value)) {
            $('postfinance_cc_data').show();
        } else {
            $('postfinance_cc_data').hide();
        }
    };

    accordion.openSection = accordion.openSection.wrap(function(originalOpenSectionMethod, section) {
        if (section.id == 'opc-payment') {
            payment.applyStashedCcData();
        }
        originalOpenSectionMethod(section);
    });
});

