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
        $('#addEmailForm a.submit')
            .on('click', () => {
                this.addEmail();
                return false;
            });

        $('#addEmailForm input[name=email]')
            .on('keydown', (e) => {
                if (e.which === 13) {
                    this.addEmail();
                    return false;
                }

                return true;
            });

        $('tr.existingEmail td.actions a')
            .on('click', (e, element) => {

                let $element = $(element);
                let email = $element.closest('tr').data('email');

                switch ($element.data('action')) {
                    case 'delete':
                        this.deleteEmail(email);
                        break;

                    case 'verify':
                        this.verifyEmail(email);
                        break;

                    case 'makePrimary':
                        this.makePrimaryEmail(email);
                        break;
                }

                return false;
            });
    }

    // --------------------------------------------------------------------------

    addEmail() {
        let email = $('#addEmailForm input[name=email]').val();
        let isPrimary = $('#addEmailForm input[name=isPrimary]').is(':checked') ? 1 : 0;
        let isVerified = $('#addEmailForm input[name=isVerified]').is(':checked') ? 1 : 0;

        $('#emailForm input[name=action]').val('add');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm input[name=isPrimary]').val(isPrimary);
        $('#emailForm input[name=isVerified]').val(isVerified);
        $('#emailForm').submit();
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
                buttons:
                    {
                        'OK': function() {
                            $('#emailForm input[name=action]').val('delete');
                            $('#emailForm input[name=email]').val(email);
                            $('#emailForm').submit();
                        },
                        'Cancel': function() {
                            modal.dialog('close');
                        },
                    }
            });
    }

    // --------------------------------------------------------------------------

    verifyEmail(email) {
        $('#emailForm input[name=action]').val('verify');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm').submit();
    }

    // --------------------------------------------------------------------------

    makePrimaryEmail(email) {
        $('#emailForm input[name=action]').val('makePrimary');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm').submit();
    }
}

export default AccountEdit;
