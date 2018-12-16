class Groups {

    /**
     * Construct Groups
     */
    constructor() {

        /**
         * Holds the timeout object
         * @type {Object}
         */
        this.searchTimeout = null;

        /**
         * The delay in ms before a search is triggered
         * @type {Number}
         */
        this.searchDelay = 150;

        if ($('.group-accounts.groups.edit').length) {
            this.toggleSuperuser();
            this.togglePermissions();
            this.permissionSearch();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Toggles the permission tables, hidden for super users
     * @return {void}
     */
    toggleSuperuser() {
        $('.field.boolean .toggle')
            .on('toggle', function(e, active) {
                if (active) {
                    $('#adminPermissions').hide();
                } else {
                    $('#adminPermissions').show();
                }
            });
    }

    // --------------------------------------------------------------------------

    /**
     * Toggles all the permissions within a particular group on or off
     * @return {void}
     */
    togglePermissions() {
        $('.permission-group tbody td.permission')
            .on('click', (e, element) => {
                $(element)
                    .closest('tr')
                    .find('input[type=checkbox]')
                    .click();
            });

        $('.permission-group tbody td.enabled input')
            .on('change', (e, element) => {
                let $element = $(element);
                let td = $element.closest('td');
                if ($element.is(':checked')) {
                    td.removeClass('error');
                    td.addClass('success');
                } else {
                    td.addClass('error');
                    td.removeClass('success');
                }
            });

        $('.permission-group .toggleAll')
            .on('click', (e, element) => {
                let $element = $(element);
                let $inputs = $element.closest('table').find('tbody td.enabled input');
                let checked = $element.is(':checked');

                $inputs
                    .each((index, element) => {

                        let $element = $(element);
                        $element.prop('checked', checked);

                        let td = $element.closest('td');

                        if ($element.is(':checked')) {
                            td.removeClass('error');
                            td.addClass('success');
                        } else {
                            td.addClass('error');
                            td.removeClass('success');
                        }
                    });
            });
    }

    // --------------------------------------------------------------------------

    /**
     * Binds to the permission search input and triggers a search after a delay
     * @return {void}
     */
    permissionSearch() {
        $('#permissionSearch input')
            .on('keyup', (e, element) => {

                let $element = $(element);
                let keywords = $.trim($element.val());

                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(function() {
                    this.doPermissionSearch(keywords);
                }, this.searchDelay);
            });
    }

    // --------------------------------------------------------------------------

    /**
     * Performs a search of the permissions
     * @param  {String} keywords The keywords to search for
     * @return {void}
     */
    doPermissionSearch(keywords) {
        let regex, searchMe, result;

        if (keywords === '') {
            $('.permission-group').show();
            $('.permission-group tbody tr').show();
            return;
        }

        /**
         * Search through all the filters
         */
        regex = new RegExp($.trim(keywords), 'i');

        $('.permission-group td.permission')
            .each((index, element) => {

                let $element = $(element);
                searchMe = $.trim($element.text());
                result = regex.test(searchMe);

                if (result) {
                    $element.closest('tr').show();
                } else {
                    $element.closest('tr').hide();
                }
            });

        /**
         * If a group's name matches the search term then show all the permissions
         * regardless of what was done above
         */
        $('.permission-group legend')
            .each((index, element) => {

                let $element = $(element);
                searchMe = $.trim($element.text());
                result = regex.test(searchMe);

                if (result) {
                    $element.parent().find('tbody tr').show();
                }
            });

        /**
         * Only show groups where there are visible filters
         */
        $('.permission-group').show();
        $('.permission-group')
            .each((index, element) => {

                let $element = $(element);
                let visible = $element.find('td.permission:visible').length;

                if (visible > 0) {
                    $element.show();
                } else {
                    $element.hide();
                }
            });
    }
}

export default Groups;
