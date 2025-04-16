jQuery(document).ready(function($) {
    const modal = document.getElementById('lwp-store-selector-modal');
    const modalDropdown = document.getElementById('lwp-store-selector-modal-dropdown');
    const modalSubmit = document.getElementById('lwp-store-selector-submit');

    // Function to check if the cart has products
    function checkCartHasProducts(callback) {
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: { action: 'check_cart_products' },
            success: function(response) {
                callback(response.success ? response.data.cartHasProducts : false);
            },
            error: function() {
                callback(false);
            }
        });
    }

    // Function to clear the cart and reload the page
    function clearCartAndReload() {
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: { action: 'clear_cart' },
            success: function() {
                window.location.href = window.location.href.split('?')[0];
            },
            error: function() {
                alert('Failed to clear the cart. Please try again.');
            }
        });
    }

    // Modal logic for changing store location
    if (modal && modalDropdown && modalSubmit) {
        modalSubmit.addEventListener('click', function() {
            const selectedStore = modalDropdown.value;
            if (selectedStore) {
                document.cookie = "store_location=" + selectedStore + "; path=/";
                modal.style.display = 'none';
                location.reload();
            } else {
                alert('Please select a store.');
            }
        });
    }

    $('#lwp-shortcode-selector-form').on('change', function() {
        const dropdown = $(this).find('.lwp-location-dropdown');
        const selectedStore = dropdown.val();

        if (!selectedStore) {
            alert('Please select a store location.');
            return;
        }

        if (selectedStore === 'all-products') {
            document.cookie = "store_location=all-products; path=/";
            location.reload();
            return;
        }

        // Check if the cart has products before changing the store location
        checkCartHasProducts(function(cartHasProducts) {
            if (cartHasProducts) {
                const confirmChange = confirm("Do you want to change the store location? Your cart will be emptied.");
                if (!confirmChange) {
                    dropdown.val(getCookie('store_location') || '');
                    return;
                }
            }

            // Set the cookie and clear the cart
            document.cookie = "store_location=" + selectedStore + "; path=/";
            clearCartAndReload();
        });
    });

    // Helper function to get cookie value
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : '';
    }
});

jQuery(document).ready(function($) {
    // Listen for variation change events
    $('.variations_form').on('found_variation', function(event, variation) {
        console.log(variation);
        if (variation.location_data) {
            var location_data = variation.location_data;
            var location_info_html = '';

            location_info_html += '<h4>' + 'Information for' + location_data.location_name + '</h4>';

            // Stock info
            if (location_data.location_stock !== '') {
                location_info_html += '<p class="location-stock"><strong>Stock</strong> ';

                if (parseInt(location_data.location_stock) > 0) {
                    location_info_html += '<span class="in-stock">' + location_data.location_stock + ' in stock</span>';
                } else {
                    if (location_data.location_backorders === 'no') {
                        location_info_html += '<span class="out-of-stock">Out of stock</span>';
                    } else {
                        location_info_html += '<span class="on-backorder">Available on backorder</span>';
                    }
                }
                location_info_html += '</p>';
            }

            // Price info
            if (location_data.location_regular_price) {
                location_info_html += '<p class="location-price"><strong>Price at this location:</strong> ';

                if (location_data.location_sale_price) {
                    location_info_html += '<del>' + location_data.location_regular_price + '</del> <ins>' + location_data.location_sale_price + '</ins>';
                } else {
                    location_info_html += location_data.location_regular_price;
                }
                location_info_html += '</p>';
            }

            $('.location-specific-info').hide().html(location_info_html).fadeIn(500);
        } else {
            // Hide location info when no variation is selected
            $('.location-specific-info').fadeOut(500);
        }            
    });
});