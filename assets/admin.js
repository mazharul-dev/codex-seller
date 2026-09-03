jQuery(function ($) {
    var products = [];
    var activeFilter = 'all';

    function text(key, fallback) {
        return (window.CodexSeller && CodexSeller.strings && CodexSeller.strings[key]) || fallback;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function getErrorMessage(xhr, response) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }

        return 'Request failed. Please try again.';
    }

    function setOutput($target, message, isError) {
        $target
            .toggleClass('is-error', Boolean(isError))
            .addClass('is-visible')
            .html(message);
    }

    function resetOutput($target) {
        $target.removeClass('is-visible is-error').empty();
    }

    function setButtonState($button, disabled, label) {
        $button.prop('disabled', disabled);
        if (label) {
            $button.text(label);
        }
    }

    function productType(product) {
        return (product.package_type || product.type || 'plugin').toLowerCase() === 'theme' ? 'theme' : 'plugin';
    }

    function productStatus(product) {
        if (product.has_update) {
            return {
                label: 'Update available',
                className: 'is-warning',
                progress: 68
            };
        }

        if (product.installed) {
            return {
                label: 'Up to date',
                className: 'is-success',
                progress: 100
            };
        }

        return {
            label: 'Not installed',
            className: 'is-muted',
            progress: 18
        };
    }

    function renderProducts() {
        var filtered = products.filter(function (product) {
            return activeFilter === 'all' || productType(product) === activeFilter;
        });

        if (!filtered.length) {
            $('#codex-seller-products').html('<div class="codex-seller-empty">No products match this filter.</div>');
            return;
        }

        var html = '';
        filtered.forEach(function (product) {
            var type = productType(product);
            var status = productStatus(product);
            var name = product.name || 'CodeX Seller Product';
            var initials = name.trim().slice(0, 2).toUpperCase() || 'CS';
            var remoteVersion = product.current_version || 'N/A';
            var installedVersion = product.installed_version || 'None';
            var action = '';

            if (product.download_url && (product.has_update || !product.installed)) {
                action = '<button class="button codex-seller-update-button" type="button"'
                    + ' data-url="' + escapeAttr(product.download_url) + '"'
                    + ' data-name="' + escapeAttr(name) + '"'
                    + ' data-slug="' + escapeAttr(product.slug || '') + '"'
                    + ' data-current-version="' + escapeAttr(remoteVersion) + '"'
                    + ' data-package-type="' + escapeAttr(type) + '">'
                    + escapeHtml(product.installed ? text('update', 'Update') : text('install', 'Install'))
                    + '</button>';
            } else if (product.download_url && !product.has_update) {
                action = '<button class="button codex-seller-update-button" type="button" disabled>No update</button>';
            } else {
                action = '<span class="codex-seller-no-update">No file available</span>';
            }

            html += '<div class="codex-seller-product-row">';
            html += '<div class="codex-seller-product-title">';
            html += '<span class="codex-seller-product-icon ' + (type === 'theme' ? 'is-theme' : '') + '">' + escapeHtml(initials) + '</span>';
            html += '<div><strong>' + escapeHtml(name) + '</strong><small>' + escapeHtml(product.slug || type) + '</small></div>';
            html += '</div>';
            html += '<div class="codex-seller-version"><small>Remote</small>' + escapeHtml(remoteVersion) + '</div>';
            html += '<div class="codex-seller-version"><small>Installed</small>' + escapeHtml(installedVersion) + '</div>';
            html += '<div>';
            html += '<span class="codex-seller-status ' + status.className + '">' + escapeHtml(status.label) + '</span>';
            html += '<div class="codex-seller-progress"><div class="codex-seller-progress-bar"><span style="--progress:' + status.progress + '%"></span></div><small>' + status.progress + '%</small></div>';
            html += '</div>';
            html += '<div>' + action + '</div>';
            html += '</div>';
        });

        $('#codex-seller-products').html(html);
    }

    function fetchProducts(showOutput) {
        var $button = $('#codex-seller-fetch-products');
        setButtonState($button, true, text('loading', 'Loading...'));

        if (showOutput !== false) {
            resetOutput($('#codex-seller-run-output'));
        }

        return $.post(CodexSeller.ajaxUrl, {
            action: 'codex_seller_fetch_products',
            nonce: CodexSeller.nonce
        }).done(function (response) {
            setButtonState($button, false, text('fetchProducts', 'Fetch Products'));

            if (!response.success) {
                $('#codex-seller-products').html('<div class="codex-seller-error">' + escapeHtml(getErrorMessage(null, response)) + '</div>');
                return;
            }

            products = response.data.products || [];

            if (!products.length) {
                $('#codex-seller-products').html('<div class="codex-seller-empty">No purchased products were found.</div>');
                return;
            }

            renderProducts();
        }).fail(function (xhr) {
            setButtonState($button, false, text('fetchProducts', 'Fetch Products'));
            $('#codex-seller-products').html('<div class="codex-seller-error">' + escapeHtml(getErrorMessage(xhr)) + '</div>');
        });
    }

    function renderSummary(summary) {
        var html = '<strong>'
            + escapeHtml(summary.checked || 0) + ' checked, '
            + escapeHtml(summary.updated || 0) + ' updated, '
            + escapeHtml(summary.skipped || 0) + ' skipped, '
            + escapeHtml(summary.failed || 0) + ' failed.'
            + '</strong>';

        if (summary.items && summary.items.length) {
            html += '<ul>';
            summary.items.slice(0, 8).forEach(function (item) {
                html += '<li>' + escapeHtml(item.name || '') + ': ' + escapeHtml(item.message || item.status || '') + '</li>';
            });
            html += '</ul>';
        }

        return html;
    }

    function renderHealth(health) {
        if (!health || !health.checks) {
            return;
        }

        var html = '';
        health.checks.forEach(function (check) {
            var icon = check.status === 'failed' ? 'dashicons-warning' : 'dashicons-yes-alt';
            html += '<li class="is-' + escapeAttr(check.status) + '">';
            html += '<span class="dashicons ' + icon + '"></span>';
            html += '<span>' + escapeHtml(check.label) + '</span>';
            html += '<strong>' + escapeHtml(check.message) + '</strong>';
            html += '</li>';
        });

        $('#codex-seller-health-list').html(html);
    }

    $('.codex-seller-nav').on('click', function () {
        var $button = $(this);
        var target = $button.data('target');
        var $target = $(target);

        if (!target) {
            return;
        }

        $('.codex-seller-nav').removeClass('is-active');
        $button.addClass('is-active');

        if ($target.length) {
            $('html, body').animate({
                scrollTop: Math.max(0, $target.offset().top - 48)
            }, 180);
        }
    });

    $('.codex-seller-tabs button').on('click', function () {
        $('.codex-seller-tabs button').removeClass('is-active');
        $(this).addClass('is-active');
        activeFilter = $(this).data('filter') || 'all';

        if (products.length) {
            renderProducts();
        }
    });

    $('#codex-seller-fetch-products').on('click', function (event) {
        event.preventDefault();
        fetchProducts(true);
    });

    $('#codex-seller-run-now').on('click', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $output = $('#codex-seller-run-output');
        setButtonState($button, true, text('running', 'Running...'));
        resetOutput($output);

        $.post(CodexSeller.ajaxUrl, {
            action: 'codex_seller_run_now',
            nonce: CodexSeller.nonce
        }).done(function (response) {
            setButtonState($button, false, text('runNow', 'Run Now'));

            if (!response.success) {
                if (response.data && response.data.health) {
                    renderHealth(response.data.health);
                }
                setOutput($output, escapeHtml(getErrorMessage(null, response)), true);
                return;
            }

            setOutput($output, renderSummary(response.data.summary || {}), Boolean(response.data.summary && response.data.summary.failed));
            fetchProducts(false);
        }).fail(function (xhr) {
            setButtonState($button, false, text('runNow', 'Run Now'));

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.health) {
                renderHealth(xhr.responseJSON.data.health);
            }

            setOutput($output, escapeHtml(getErrorMessage(xhr)), true);
        });
    });

    $('#codex-seller-health-check').on('click', function (event) {
        event.preventDefault();

        var $button = $(this);
        setButtonState($button, true, 'Checking...');

        $.post(CodexSeller.ajaxUrl, {
            action: 'codex_seller_health_check',
            nonce: CodexSeller.nonce
        }).done(function (response) {
            setButtonState($button, false, 'Check');

            if (response.success) {
                renderHealth(response.data.health);
            }
        }).fail(function () {
            setButtonState($button, false, 'Check');
        });
    });

    $('#codex-seller-rollback-latest').on('click', function (event) {
        event.preventDefault();

        if (!window.confirm(text('rollbackConfirm', 'Restore the latest CodeX Seller backup now?'))) {
            return;
        }

        var $button = $(this);
        var $output = $('#codex-seller-rollback-output');
        setButtonState($button, true, text('rollbacking', 'Restoring...'));
        resetOutput($output);

        $.post(CodexSeller.ajaxUrl, {
            action: 'codex_seller_rollback_latest',
            nonce: CodexSeller.nonce
        }).done(function (response) {
            setButtonState($button, false, text('rollback', 'Rollback Now'));

            if (!response.success) {
                setOutput($output, escapeHtml(getErrorMessage(null, response)), true);
                return;
            }

            setOutput($output, escapeHtml(response.data.message || 'Rollback completed successfully.'), false);
        }).fail(function (xhr) {
            setButtonState($button, false, text('rollback', 'Rollback Now'));
            setOutput($output, escapeHtml(getErrorMessage(xhr)), true);
        });
    });

    $(document).on('click', '.codex-seller-update-button:not(:disabled)', function (event) {
        event.preventDefault();

        var $button = $(this);
        var originalLabel = $button.text();
        var productName = $button.data('name');
        var currentVersion = $button.data('current-version');
        var slug = $button.data('slug');
        var $output = $('#codex-seller-run-output');

        setButtonState($button, true, text('updating', 'Updating...'));
        resetOutput($output);

        $.post(CodexSeller.ajaxUrl, {
            action: 'codex_seller_update_product',
            nonce: CodexSeller.nonce,
            download_url: $button.data('url'),
            product_name: productName,
            slug: slug,
            current_version: currentVersion,
            package_type: $button.data('package-type')
        }).done(function (response) {
            if (!response.success) {
                if (response.data && response.data.health) {
                    renderHealth(response.data.health);
                }
                setButtonState($button, false, originalLabel);
                setOutput($output, escapeHtml(getErrorMessage(null, response)), true);
                return;
            }

            products = products.map(function (product) {
                if ((product.slug || '') === slug) {
                    product.installed = true;
                    product.has_update = false;
                    product.installed_version = currentVersion || product.current_version || product.installed_version;
                }
                return product;
            });

            renderProducts();
            setOutput($output, escapeHtml(response.data.message || 'Update installed successfully.'), false);
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.health) {
                renderHealth(xhr.responseJSON.data.health);
            }

            setButtonState($button, false, originalLabel);
            setOutput($output, escapeHtml(getErrorMessage(xhr)), true);
        });
    });
});
