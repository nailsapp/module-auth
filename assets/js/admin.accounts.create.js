var NAILS_Admin_Accounts_Create;
NAILS_Admin_Accounts_Create = function()
{
    this.__construct = function()
    {
        $('select[name=group_id]').on('change', function() {

            $('#user-group-descriptions li').hide();
            $('#user-group-pwrules li').hide();
            $('#user-group-' + $(this).val()).show();
            $('#user-group-pw-' + $(this).val()).show();
        });
    };

    // --------------------------------------------------------------------------

    return this.__construct();
};
