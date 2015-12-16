var NAILS_Admin_Accounts_Edit;
NAILS_Admin_Accounts_Edit = function()
{
    this.__construct = function()
    {
        this.initGroupSwitcher();
        this.initEmailManagement();
    };

    // --------------------------------------------------------------------------

    this.initGroupSwitcher = function()
    {
        $('select[name=group_id]').on('change', function()
        {
            $('#user-group-descriptions li').hide();
            $('#user-group-' + $(this).val()).show();
        });
    };

    // --------------------------------------------------------------------------

    this.initEmailManagement = function()
    {
        //  Bind all the things
        var _this = this;
        $('#addEmailForm a.submit').on('click', function()
        {
            _this.addEmail();
            return false;
        });

        $('#addEmailForm input[name=email]').on('keydown', function(e)
        {
            if (e.which === 13) {

                _this.addEmail();
                return false;
            }

            return true;
        });

        $('tr.existingEmail td.actions a').on('click', function()
        {
            var email = $(this).closest('tr').data('email');

            switch($(this).data('action'))
            {
                case 'delete':

                    _this.deleteEmail(email);
                    break;

                case 'verify':

                    _this.verifyEmail(email);
                    break;

                case 'makePrimary':

                    _this.makePrimaryEmail(email);
                    break;
            }

            return false;
        });
    };

    // --------------------------------------------------------------------------

    this.addEmail = function()
    {
        var email       = $('#addEmailForm input[name=email]').val();
        var isPrimary   = $('#addEmailForm input[name=isPrimary]').is(':checked') ? 1 : 0;
        var isVerified  = $('#addEmailForm input[name=isVerified]').is(':checked') ? 1 : 0;

        $('#emailForm input[name=action]').val('add');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm input[name=isPrimary]').val(isPrimary);
        $('#emailForm input[name=isVerified]').val(isVerified);
        $('#emailForm').submit();
    };

    // --------------------------------------------------------------------------

    this.deleteEmail = function(email)
    {
        $('<div>').text('Are you sure you want to delete "' + email + '"?').dialog({
            title: 'Are you sure?',
            resizable: false,
            draggable: false,
            modal: true,
            buttons:
            {
                OK: function()
                {
                    $('#emailForm input[name=action]').val('delete');
                    $('#emailForm input[name=email]').val(email);
                    $('#emailForm').submit();
                },
                Cancel: function()
                {
                    $(this).dialog('close');
                },
            }
        });
    };

    // --------------------------------------------------------------------------

    this.verifyEmail = function(email)
    {
        $('#emailForm input[name=action]').val('verify');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm').submit();
    };

    // --------------------------------------------------------------------------

    this.makePrimaryEmail = function(email)
    {
        $('#emailForm input[name=action]').val('makePrimary');
        $('#emailForm input[name=email]').val(email);
        $('#emailForm').submit();
    };

    // --------------------------------------------------------------------------

    return this.__construct();
};