/**
 * Frontend Product Filter - AJAX functionality
 */
(function($) {
    'use strict';

    const FrontendFilter = {
        $container: null,
        $productsContainer: null,
        $paginationContainer: null,
        isLoading: false,
        currentPage: 1,

        init: function() {
            this.$container = $('#mulopimfwc-product-filter');
            
            if (!this.$container.length) {
                return;
            }

            // Find products container (WooCommerce shop loop container)
            this.$productsContainer = $('.woocommerce-products-header').next('.products').length 
                ? $('.woocommerce-products-header').next('.products').parent()
                : $('.woocommerce-shop').length 
                    ? $('.woocommerce-shop')
                    : $('main').find('.products').parent().length
                        ? $('main').find('.products').parent()
                        : $('body');

            // Find pagination container
            this.$paginationContainer = $('.woocommerce-pagination').parent().length
                ? $('.woocommerce-pagination').parent()
                : this.$productsContainer;

            // Bind events
            this.bindEvents();

            // Auto-filter on page load if filters are present in URL
            if (this.hasActiveFilters()) {
                this.performFilter();
            }
        },

        bindEvents: function() {
            const self = this;

            // Filter button click
            $('#mulopimfwc-filter-button').on('click', function(e) {
                e.preventDefault();
                self.currentPage = 1;
                self.performFilter();
            });

            // Clear button click
            $('#mulopimfwc-clear-button').on('click', function(e) {
                e.preventDefault();
                self.clearFilters();
            });

            // Enter key on selects
            $('.mulopimfwc-filter-select').on('change', function() {
                // Auto-filter on change if auto-filter is enabled (can be toggled)
                if (self.$container.data('auto-filter') === true) {
                    self.currentPage = 1;
                    self.performFilter();
                }
            });

            // Pagination click (delegate to handle AJAX-loaded pagination)
            $(document).on('click', '.woocommerce-pagination a', function(e) {
                if (self.hasActiveFilters()) {
                    e.preventDefault();
                    const href = $(this).attr('href');
                    const pageMatch = href.match(/paged[=\/](\d+)/);
                    if (pageMatch) {
                        self.currentPage = parseInt(pageMatch[1], 10);
                        self.performFilter();
                    }
                }
            });

            // Handle browser back/forward buttons
            $(window).on('popstate', function(e) {
                if (e.originalEvent.state && e.originalEvent.state.filters) {
                    self.applyFiltersFromState(e.originalEvent.state.filters);
                    self.performFilter();
                }
            });
        },

        hasActiveFilters: function() {
            const location = $('#mulopimfwc-filter-location-select').val();
            const stock = $('#mulopimfwc-filter-stock-select').val();
            return !!(location || stock) || this.getFiltersFromURL().length > 0;
        },

        getFiltersFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return {
                location: urlParams.get('location') || '',
                stock: urlParams.get('stock') || '',
            };
        },

        getCurrentFilters: function() {
            return {
                location: $('#mulopimfwc-filter-location-select').val() || '',
                stock: $('#mulopimfwc-filter-stock-select').val() || '',
                category: this.getCurrentCategory(),
                tag: this.getCurrentTag(),
                search: this.getCurrentSearch(),
                page: this.currentPage,
            };
        },

        getCurrentCategory: function() {
            const categoryMatch = window.location.pathname.match(/\/product-category\/([^\/]+)/);
            return categoryMatch ? categoryMatch[1] : '';
        },

        getCurrentTag: function() {
            const tagMatch = window.location.pathname.match(/\/product-tag\/([^\/]+)/);
            return tagMatch ? tagMatch[1] : '';
        },

        getCurrentSearch: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('s') || '';
        },

        performFilter: function() {
            if (this.isLoading) {
                return;
            }

            const filters = this.getCurrentFilters();
            this.isLoading = true;

            // Update URL without reload
            this.updateURL(filters);

            // Show loading state
            this.showLoading();

            // Get current orderby and order from WooCommerce
            const orderby = $('.woocommerce-ordering select').val() || 'menu_order';
            const order = orderby.includes('price-desc') ? 'DESC' : 'ASC';

            // AJAX request
            $.ajax({
                url: mulopimfwcFilter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_filter_products',
                    nonce: mulopimfwcFilter.nonce,
                    location: filters.location,
                    stock: filters.stock,
                    category: filters.category,
                    tag: filters.tag,
                    search: filters.search,
                    page: filters.page,
                    orderby: orderby,
                    order: order,
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.displayResults(response.data);
                        // Update browser history
                        window.history.pushState(
                            { filters: filters },
                            '',
                            this.buildFilterURL(filters)
                        );
                    } else {
                        this.showError(mulopimfwcFilter.i18n.error);
                    }
                },
                error: () => {
                    this.showError(mulopimfwcFilter.i18n.error);
                },
                complete: () => {
                    this.isLoading = false;
                    this.hideLoading();
                }
            });
        },

        displayResults: function(data) {
            // Replace result count
            const $resultCount = $('.woocommerce-result-count');
            if ($resultCount.length && data.result_count) {
                $resultCount.replaceWith(data.result_count);
            } else if (data.result_count) {
                // If result count doesn't exist, find where it should be (usually before ordering)
                const $ordering = $('.woocommerce-ordering');
                if ($ordering.length) {
                    $ordering.before(data.result_count);
                } else {
                    // Find woocommerce-products-header and append
                    const $header = $('.woocommerce-products-header');
                    if ($header.length) {
                        $header.append(data.result_count);
                    }
                }
            }

            // Replace products - preserve WooCommerce structure exactly
            // Find existing products container
            let $existingProducts = $('ul.products.woocommerce-loop, ul.products, .products.woocommerce-loop, .products, .wc-block-grid__products').first();
            
            if (!$existingProducts.length) {
                // Try finding by parent container
                $existingProducts = $('.woocommerce-loop-product__link').first().closest('ul.products, .products, .wc-block-grid__products');
            }
            
            if ($existingProducts.length) {
                // Parse the new products HTML
                const $temp = $('<div>').html(data.products);
                let $newProducts = $temp.find('ul.products.woocommerce-loop, ul.products, .products.woocommerce-loop, .products, .wc-block-grid__products').first();
                
                // If we found a products wrapper in the response
                if ($newProducts.length) {
                    // Preserve classes and attributes from existing wrapper
                    const existingClasses = $existingProducts.attr('class');
                    const existingAttrs = {};
                    $existingProducts.each(function() {
                        $.each(this.attributes, function() {
                            if (this.specified && this.name !== 'class') {
                                existingAttrs[this.name] = this.value;
                            }
                        });
                    });
                    
                    // Copy attributes to new wrapper
                    if (existingClasses) {
                        $newProducts.attr('class', existingClasses);
                    }
                    $.each(existingAttrs, function(key, value) {
                        $newProducts.attr(key, value);
                    });
                    
                    // Replace the entire wrapper
                    $existingProducts.replaceWith($newProducts);
                } else {
                    // No wrapper found, just replace content
                    $existingProducts.html($temp.html());
                }
            } else {
                // Fallback: try to insert products in expected location
                const $shopLoopArea = $('.woocommerce-products-header').next('.products, ul.products');
                if ($shopLoopArea.length) {
                    const $temp = $('<div>').html(data.products);
                    const $newProducts = $temp.find('ul.products, .products, .wc-block-grid__products').first();
                    if ($newProducts.length) {
                        $shopLoopArea.replaceWith($newProducts);
                    } else {
                        $shopLoopArea.replaceWith(data.products);
                    }
                }
            }

            // Replace pagination
            const $existingPagination = $('.woocommerce-pagination');
            if ($existingPagination.length && data.pagination) {
                $existingPagination.replaceWith(data.pagination);
            } else if (data.pagination) {
                // Find where pagination should be (usually after products)
                const $productsAfter = $('.products, .wc-block-grid__products').last();
                if ($productsAfter.length) {
                    $productsAfter.after(data.pagination);
                } else {
                    this.$paginationContainer.append(data.pagination);
                }
            } else {
                $existingPagination.remove();
            }

            // Scroll to top of results
            $('html, body').animate({
                scrollTop: this.$container.offset().top - 100
            }, 300);

            // Trigger WooCommerce events for compatibility
            $(document.body).trigger('mulopimfwc_products_updated', [data]);
        },

        clearFilters: function() {
            $('#mulopimfwc-filter-location-select').val('');
            $('#mulopimfwc-filter-stock-select').val('');

            this.currentPage = 1;

            // Remove filter params from URL and reload
            const url = new URL(window.location.href);
            url.searchParams.delete('location');
            url.searchParams.delete('stock');
            url.searchParams.delete('paged');

            window.location.href = url.toString();
        },

        updateURL: function(filters) {
            const url = new URL(window.location.href);
            
            if (filters.location) {
                url.searchParams.set('location', filters.location);
            } else {
                url.searchParams.delete('location');
            }

            if (filters.stock) {
                url.searchParams.set('stock', filters.stock);
            } else {
                url.searchParams.delete('stock');
            }

            if (filters.page > 1) {
                url.searchParams.set('paged', filters.page);
            } else {
                url.searchParams.delete('paged');
            }

            window.history.replaceState({ filters: filters }, '', url.toString());
        },

        buildFilterURL: function(filters) {
            const url = new URL(window.location.href);
            
            if (filters.location) {
                url.searchParams.set('location', filters.location);
            } else {
                url.searchParams.delete('location');
            }

            if (filters.stock) {
                url.searchParams.set('stock', filters.stock);
            } else {
                url.searchParams.delete('stock');
            }

            if (filters.page > 1) {
                url.searchParams.set('paged', filters.page);
            } else {
                url.searchParams.delete('paged');
            }

            return url.toString();
        },

        applyFiltersFromState: function(filters) {
            if (filters.location) {
                $('#mulopimfwc-filter-location-select').val(filters.location);
            }
            if (filters.stock) {
                $('#mulopimfwc-filter-stock-select').val(filters.stock);
            }
            if (filters.page) {
                this.currentPage = filters.page;
            }
        },

        showLoading: function() {
            const $products = $('.products, .wc-block-grid__products');
            if ($products.length) {
                $products.css('opacity', '0.5').css('pointer-events', 'none');
                $products.prepend('<div class="mulopimfwc-filter-loading" style="text-align:center;padding:40px;position:absolute;top:0;left:0;right:0;z-index:9999;"><span>' + mulopimfwcFilter.i18n.loading + '</span></div>');
            }
        },

        hideLoading: function() {
            $('.products, .wc-block-grid__products').css('opacity', '1').css('pointer-events', 'auto');
            $('.mulopimfwc-filter-loading').remove();
        },

        showError: function(message) {
            const $products = $('.products, .wc-block-grid__products');
            if ($products.length) {
                $products.html('<li class="product"><div class="woocommerce-info">' + message + '</div></li>');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FrontendFilter.init();
    });

})(jQuery);

