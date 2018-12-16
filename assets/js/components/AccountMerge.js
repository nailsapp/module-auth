class AccountMerge {

    /**
     * Construct AccountMerge
     */
    constructor() {
        if ($('.group-accounts.merge').length) {

            //  Construct searchers
            $('#userId')
                .select2({
                    placeholder: 'Search for a user',
                    minimumInputLength: 1,
                    ajax: {
                        url: window.SITE_URL + 'api/admin/users/search',
                        dataType: 'json',
                        quietMillis: 250,
                        data: function(term) {
                            return {
                                term: term
                            };
                        },
                        results: function(data) {

                            let returnData = {results: []};
                            let userId = '';
                            let userName = '';

                            for (let key in data.users) {
                                if (data.users.hasOwnProperty(key)) {
                                    userId = data.users[key].id;
                                    userName = '#' + userId + ' - ' + data.users[key].first_name + ' ' + data.users[key].last_name;
                                    returnData.results.push({'text': userName, 'id': userId});
                                }
                            }

                            return returnData;
                        },
                        cache: true
                    }
                });

            $('#mergeIds')
                .select2({
                    placeholder: 'Search for users',
                    minimumInputLength: 1,
                    multiple: true,
                    ajax: {
                        url: window.SITE_URL + 'api/admin/users/search',
                        dataType: 'json',
                        quietMillis: 250,
                        data: function(term) {
                            return {
                                term: term
                            };
                        },
                        results: function(data) {

                            let returnData = {results: []};
                            let userId = '';
                            let userName = '';

                            for (let key in data.users) {
                                if (data.users.hasOwnProperty(key)) {
                                    userId = data.users[key].id;
                                    userName = '#' + userId + ' - ' + data.users[key].first_name + ' ' + data.users[key].last_name;
                                    returnData.results.push({'text': userName, 'id': userId});
                                }
                            }

                            return returnData;
                        },
                        cache: true
                    }
                });

            $('#theForm')
                .on('submit', () => {
                    return this.validate();
                });
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validate the input
     * @returns {boolean}
     */
    validate() {

        let errors = false;
        let errorMsg = [];
        let userId = parseInt($('#userId').val(), 10);
        let mergeIdsSrc = $('#mergeIds').val().split(',');
        let mergeIds = [];
        let i;

        for (i = 0; i < mergeIdsSrc.length; i++) {
            if (mergeIdsSrc[i]) {
                mergeIds.push(parseInt(mergeIdsSrc[i], 10));
            }
        }

        if (isNaN(userId)) {
            errors = true;
            errorMsg.push('You must specify the user to keep.');
        }

        if (mergeIds.length === 0) {
            errors = true;
            errorMsg.push('You must specify at least one user to merge.');
        }

        for (i = 0; i < mergeIds.length; i++) {
            if (mergeIds[i] === userId) {
                errors = true;
                errorMsg.push('The user to merge into cannot be listed as a merge user.');
                break;
            }
        }

        for (i = 0; i < mergeIds.length; i++) {
            if (mergeIds[i] === window.NAILS.USER.ID) {
                errors = true;
                errorMsg.push('You cannot specify yourself as a merge user.');
                break;
            }
        }

        if (errors === false) {
            return true;
        }

        let message;
        message = '<p>The following errors occurred:</p>';
        message += '<ul>';

        for (let key in errorMsg) {
            if (errorMsg.hasOwnProperty(key)) {
                message += '<li>&rsaquo; ' + errorMsg[key] + '</li>';
            }
        }
        message += '</ul>';

        let modal = $('<div>')
            .html(message)
            .dialog({
                title: 'An error occurred',
                resizable: false,
                draggable: false,
                modal: true,
                dialogClass: 'no-close',
                buttons: {
                    'OK': function() {
                        modal.dialog('close');
                    }
                }
            })
            .show();

        return false;
    }
}

export default AccountMerge;
