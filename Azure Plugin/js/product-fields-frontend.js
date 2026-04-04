/**
 * Product Fields – child profile auto-population.
 *
 * When a logged-in user selects a child from the dropdown,
 * we match the child's stored meta keys against field labels
 * and fill in the values automatically.
 */
jQuery(function ($) {
    var $selector = $('#azure-pf-select-child');
    if (!$selector.length || typeof azureChildProfiles === 'undefined') {
        return;
    }

    $selector.on('change', function () {
        var childId = parseInt($(this).val(), 10);

        if (!childId) {
            clearFields();
            return;
        }

        var child = null;
        for (var i = 0; i < azureChildProfiles.length; i++) {
            if (azureChildProfiles[i].id === childId) {
                child = azureChildProfiles[i];
                break;
            }
        }

        if (!child) {
            return;
        }

        populateFields(child);
    });

    function populateFields(child) {
        var meta = child.meta || {};

        $('.azure-product-fields .azure-pf-field').each(function () {
            var $field = $(this);
            var $label = $field.find('label').first();
            var labelText = $.trim($label.text().replace(/\*$/, '').replace(/\s+/g, ' '));

            var value = findMetaValue(meta, labelText, child.name);
            if (value === null) {
                return;
            }

            var $input = $field.find('input, textarea, select').first();
            if (!$input.length) {
                return;
            }

            if ($input.is(':checkbox')) {
                $input.prop('checked', value === 'Yes' || value === '1' || value === 'true');
            } else {
                $input.val(value).trigger('change');
            }
        });
    }

    function findMetaValue(meta, labelText, childName) {
        var lower = labelText.toLowerCase();

        if (lower.indexOf('child') !== -1 && lower.indexOf('name') !== -1) {
            return childName;
        }

        if (meta[labelText] !== undefined) {
            return meta[labelText];
        }

        for (var key in meta) {
            if (meta.hasOwnProperty(key) && key.toLowerCase() === lower) {
                return meta[key];
            }
        }

        return null;
    }

    function clearFields() {
        $('.azure-product-fields .azure-pf-field').each(function () {
            var $input = $(this).find('input, textarea, select').first();
            if (!$input.length) {
                return;
            }
            if ($input.is(':checkbox')) {
                $input.prop('checked', false);
            } else {
                $input.val('');
            }
        });
    }
});
