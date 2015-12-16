var NAILS_Admin_Auth_Groups_Edit;
NAILS_Admin_Auth_Groups_Edit = function()
{
    /**
     * Avoid scope issues in callbacks and anonymous functions by referring to `this` as `base`
     * @type {Object}
     */
    var base = this;

    // --------------------------------------------------------------------------

    /**
     * Holds the timeout object
     * @type {Object}
     */
    base.searchTimeout = null;

    /**
     * The delay in ms before a search is triggered
     * @type {Number}
     */
    base.searchDelay   = 150;

    // --------------------------------------------------------------------------

    /**
     * Construct the class
     */
    base.__construct = function()
    {
        base.toggleSuperuser();
        base.togglePermissions();
        base.permissionSearch();
    };

    // --------------------------------------------------------------------------

    /**
     * Toggles the permission tables, hidden for super users
     * @return {Void}
     */
    base.toggleSuperuser = function()
    {
        $('.field.boolean .toggle').on('toggle', function(e, active) {

            if (active) {

                $('#adminPermissions').hide();

            } else {

                $('#adminPermissions').show();
            }
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Toggles all the permissions within a particular group on or off
     * @return {Void}
     */
    base.togglePermissions = function()
    {
        $('.permission-group tbody td.permission').on('click', function()
        {
            $(this).closest('tr').find('input[type=checkbox]').click();
        });

        $('.permission-group tbody td.enabled input').on('change', function() {

            var td = $(this).closest('td');
            if ($(this).is(':checked')) {

                td.removeClass('error');
                td.addClass('success');

            } else {

                td.addClass('error');
                td.removeClass('success');
            }
        });

        $('.permission-group .toggleAll').on('click', function()
        {
            var inputs  = $(this).closest('table').find('tbody td.enabled input');
            var checked = $(this).is(':checked');

            inputs.each(function() {

                $(this).prop('checked', checked);

                var td = $(this).closest('td');

                if ($(this).is(':checked')) {

                    td.removeClass('error');
                    td.addClass('success');

                } else {

                    td.addClass('error');
                    td.removeClass('success');
                }
            });
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Binds to the permission search input and triggers a search after a delay
     * @return {Void}
     */
    base.permissionSearch = function()
    {
        $('#permissionSearch input').on('keyup', function() {

            var keywords = $.trim($(this).val());

            clearTimeout(base.searchTimeout);
            base.searchTimeout = setTimeout(function() {

                base.doPermissionSearch(keywords);

            }, base.searchDelay);
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Performs a search of the permissions
     * @param  {String} keywords The keywords to search for
     * @return {Void}
     */
    base.doPermissionSearch = function(keywords)
    {
        var regex, searchMe, result;

        if (keywords === '') {

            $('.permission-group').show();
            $('.permission-group tbody tr').show();
            return;
        }

        /**
         * Search through all the filters
         */
        regex = new RegExp($.trim(keywords), 'i');

        $('.permission-group td.permission').each(function() {

            searchMe = $.trim($(this).text());
            result   = regex.test(searchMe);

            if (result) {

                $(this).closest('tr').show();

            } else {

                $(this).closest('tr').hide();
            }
        });

        /**
         * If a group's name matches the search term then show all the permissions
         * regardless of what was done above
         */
        $('.permission-group legend').each(function() {

            searchMe = $.trim($(this).text());
            result   = regex.test(searchMe);

            if (result) {

                $(this).parent().find('tbody tr').show();
            }
        });

        /**
         * Only show groups where there are visible filters
         */
        $('.permission-group').show();
        $('.permission-group').each(function() {

            var visible = $(this).find('td.permission:visible').length;

            if (visible > 0) {

                $(this).show();

            } else {

                $(this).hide();
            }
        });
    };

    // --------------------------------------------------------------------------

    return base.__construct();
};