(function($) {
    'use strict';

    let postalCodeData = {};
    const config = window.niyagoPostcodesConfig || { pluginUrl: '', enabledCountries: ['MY'] };

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Load postal code data for a country
     */
    async function loadPostalCodeData(country) {
        country = country.toLowerCase().replace(/[^a-z]/g, "");
        if (!country || country.length !== 2) { return null; }

        if (postalCodeData[country]) {
            return postalCodeData[country];
        }

        try {
            const url = config.pluginUrl + 'assets/data/' + country + '.json';
            const response = await fetch(url);
            if (response.ok) {
                postalCodeData[country] = await response.json();
                return postalCodeData[country];
            }
        } catch (e) {
        }
        return null;
    }

    /**
     * Check if country is enabled
     */
    function isCountryEnabled(country) {
        if (!country) return false;
        return config.enabledCountries.includes(country.toUpperCase());
    }

    /**
     * Find field by multiple possible selectors
     */
    function findField(selectors) {
        for (const selector of selectors) {
            const field = document.querySelector(selector);
            if (field) return field;
        }
        return null;
    }

    /**
     * Get current country
     */
    function getCurrentCountry(prefix) {
        // Try multiple selectors for country field
        const selectors = [
            `#${prefix}_country`,
            `#${prefix}-country`,
            `[name="${prefix}_country"]`,
            `[name="${prefix}[country]"]`,
            `[id*="${prefix}"] [autocomplete="country"]`,
            `.wc-block-components-address-form [id*="country"]`
        ];

        const field = findField(selectors);
        return field ? field.value : 'MY'; // Default to MY if not found
    }

    /**
     * Get city field
     */
    function getCityField(prefix) {
        const selectors = [
            `#${prefix}_city`,
            `#${prefix}-city`,
            `[name="${prefix}_city"]`,
            `[name="${prefix}[city]"]`,
            `#${prefix}-address_city`,
            `[id*="${prefix}"][id*="city"]`,
            `.wc-block-components-address-form input[id*="city"]`
        ];
        return findField(selectors);
    }

    /**
     * Get state field
     */
    function getStateField(prefix) {
        const selectors = [
            `#${prefix}_state`,
            `#${prefix}-state`,
            `[name="${prefix}_state"]`,
            `[name="${prefix}[state]"]`,
            `#${prefix}-address_state`,
            `[id*="${prefix}"][id*="state"]`,
            `.wc-block-components-address-form select[id*="state"]`,
            `.wc-block-components-address-form input[id*="state"]`
        ];
        return findField(selectors);
    }

    /**
     * Get postcode field
     */
    function getPostcodeField(prefix) {
        const selectors = [
            `#${prefix}_postcode`,
            `#${prefix}-postcode`,
            `[name="${prefix}_postcode"]`,
            `[name="${prefix}[postcode]"]`,
            `#${prefix}-address_postcode`,
            `[id*="${prefix}"][id*="postcode"]`,
            `.wc-block-components-address-form input[id*="postcode"]`
        ];
        return findField(selectors);
    }

    /**
     * Lookup postcode and autofill city/state
     */
    async function lookupPostalCode(postcode, prefix) {
        const country = getCurrentCountry(prefix);


        if (!isCountryEnabled(country)) {
            return;
        }

        if (!postcode || postcode.length < 5) {
            return;
        }

        const data = await loadPostalCodeData(country);
        if (!data || !data.data) {
            return;
        }

        const match = data.data[postcode];
        if (!match) {
            return;
        }

        const [city, stateIndex] = match;
        const stateName = data.states[stateIndex];


        // Auto-fill city field (always fill when postcode matches)
        const cityInput = getCityField(prefix);
        if (cityInput) {
            setNativeValue(cityInput, city);
            highlightField(cityInput);
        }

        // Auto-fill state field (always fill when postcode matches)
        const stateField = getStateField(prefix);
        if (stateField) {
            if (stateField.tagName === 'SELECT') {
                const options = stateField.querySelectorAll('option');
                let matched = false;
                for (const opt of options) {
                    const optText = opt.textContent.toLowerCase().trim();
                    const stateNameLower = stateName.toLowerCase().trim();
                    if (optText === stateNameLower || optText.includes(stateNameLower) || stateNameLower.includes(optText)) {
                        setNativeValue(stateField, opt.value);
                        highlightField(stateField);
                        matched = true;
                        break;
                    }
                }
                if (!matched) {
                }
            } else {
                setNativeValue(stateField, stateName);
                highlightField(stateField);
            }
        } else {
        }

        // Trigger WooCommerce update
        $(document.body).trigger('update_checkout');
    }

    /**
     * Set value using native setter (for React/WC Blocks compatibility)
     */
    function setNativeValue(element, value) {
        const valueSetter = Object.getOwnPropertyDescriptor(element, 'value')?.set;
        const prototype = Object.getPrototypeOf(element);
        const prototypeValueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;

        if (valueSetter && valueSetter !== prototypeValueSetter) {
            prototypeValueSetter.call(element, value);
        } else if (valueSetter) {
            valueSetter.call(element, value);
        } else {
            element.value = value;
        }

        // Trigger events for React and vanilla JS
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
        $(element).trigger('change');
    }

    /**
     * Highlight auto-filled field
     */
    function highlightField(field) {
        field.classList.add('niyago-postcodes-highlight');
        setTimeout(() => {
            field.classList.remove('niyago-postcodes-highlight');
        }, 1500);
    }

    /**
     * Initialize postcode autofill for a field group
     */
    function initAutofill(prefix) {
        const postcodeInput = getPostcodeField(prefix);
        if (!postcodeInput) {
            return;
        }

        // Check if already initialized
        if (postcodeInput.dataset.niyagoInit) {
            return;
        }
        postcodeInput.dataset.niyagoInit = 'true';


        // Listen for input changes
        postcodeInput.addEventListener('input', debounce(function() {
            lookupPostalCode(this.value.trim(), prefix);
        }, 300));

        // Also handle paste events
        postcodeInput.addEventListener('paste', function() {
            setTimeout(() => {
                lookupPostalCode(this.value.trim(), prefix);
            }, 100);
        });
    }

    /**
     * Initialize for WooCommerce Blocks
     */
    function initBlocksAutofill() {
        // Find all postcode inputs in blocks checkout
        const postcodeInputs = document.querySelectorAll(
            '.wc-block-components-address-form input[id*="postcode"], ' +
            'input[id*="billing-postcode"], ' +
            'input[id*="shipping-postcode"]'
        );

        postcodeInputs.forEach(input => {
            if (input.dataset.niyagoInit) return;
            input.dataset.niyagoInit = 'true';

            // Determine prefix from input id
            const prefix = input.id.includes('shipping') ? 'shipping' : 'billing';


            input.addEventListener('input', debounce(function() {
                lookupPostalCode(this.value.trim(), prefix);
            }, 300));

            input.addEventListener('paste', function() {
                setTimeout(() => {
                    lookupPostalCode(this.value.trim(), prefix);
                }, 100);
            });
        });
    }

    /**
     * Initialize on page load and AJAX updates
     */
    function init() {

        // Classic WooCommerce checkout
        initAutofill('billing');
        initAutofill('shipping');

        // WooCommerce Blocks checkout
        initBlocksAutofill();
    }

    // Initialize on document ready
    $(document).ready(function() {
        init();

        // Re-initialize after WooCommerce AJAX updates
        $(document.body).on('updated_checkout', init);
        $(document.body).on('country_to_state_changed', init);

        // Watch for DOM changes (for Blocks checkout)
        const observer = new MutationObserver(debounce(function() {
            init();
        }, 500));

        const checkoutForm = document.querySelector('.wc-block-checkout, .woocommerce-checkout, #checkout');
        if (checkoutForm) {
            observer.observe(checkoutForm, { childList: true, subtree: true });
        }
    });

    // Also handle WooCommerce Blocks checkout hooks
    if (typeof wp !== 'undefined' && wp.hooks) {
        wp.hooks.addAction('experimental__woocommerce_blocks-checkout-render-checkout-form', 'niyago-postcodes', function() {
            setTimeout(init, 500);
        });
    }

    // Re-init after a short delay for SPA-style checkouts
    setTimeout(init, 1000);
    setTimeout(init, 2000);

})(jQuery);
