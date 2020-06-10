class AccountEdit {

    /**
     * Construct AccountEdit
     */
    constructor() {
        if ($('.group-accounts.edit').length) {
            this.initEmailManagement();
        }
    }

    // --------------------------------------------------------------------------

    initEmailManagement() {
        //  Bind all the things
        $('#add-email-form a.submit')
            .on('click', () => {
                this.addEmail();
                return false;
            });

        $('#add-email-form input[name=email]')
            .on('keydown', (e) => {
                if (e.which === 13) {
                    this.addEmail();
                    return false;
                }

                return true;
            });

        $('tr.existing-email td.actions a')
            .on('click', (e) => {

                let $element = $(e.currentTarget);
                let email = $element.closest('tr').data('email');

                switch ($element.data('action')) {
                    case 'delete':
                        this.deleteEmail(email);
                        break;

                    case 'verify':
                        this.verifyEmail(email);
                        break;

                    case 'make-primary':
                        this.makePrimaryEmail(email);
                        break;

                    default:
                        console.log('no action');
                        break;
                }

                return false;
            });
    }

    // --------------------------------------------------------------------------

    addEmail() {
        let email = $('#add-email-form input[name=email]').val();
        let isPrimary = $('#add-email-form input[name=is_primary]').is(':checked') ? 1 : 0;
        let isVerified = $('#add-email-form input[name=is_verified]').is(':checked') ? 1 : 0;

        $('#email-form input[name=action]').val('add');
        $('#email-form input[name=email]').val(email);
        $('#email-form input[name=is_primary]').val(isPrimary);
        $('#email-form input[name=is_verified]').val(isVerified);
        $('#email-form').submit();
    }

    // --------------------------------------------------------------------------

    deleteEmail(email) {
        let modal = $('<div>')
            .text('Are you sure you want to delete "' + email + '"?')
            .dialog({
                title: 'Are you sure?',
                resizable: false,
                draggable: false,
                modal: true,
                buttons: {
                    'OK': function() {
                        $('#email-form input[name=action]').val('delete');
                        $('#email-form input[name=email]').val(email);
                        $('#email-form').submit();
                    },
                    'Cancel': function() {
                        modal.dialog('close');
                    },
                }
            });
    }

    // --------------------------------------------------------------------------

    verifyEmail(email) {
        $('#email-form input[name=action]').val('verify');
        $('#email-form input[name=email]').val(email);
        $('#email-form').submit();
    }

    // --------------------------------------------------------------------------

    makePrimaryEmail(email) {
        $('#email-form input[name=action]').val('makePrimary');
        $('#email-form input[name=email]').val(email);
        $('#email-form').submit();
    }
}

export default AccountEdit;
