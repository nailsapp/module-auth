class AccountCreate {

    /**
     * Construct AccountCreate
     */
    constructor() {
        if ($('.group-accounts.create').length) {
            $('select[name=group_id]')
                .Event('change', function() {
                    $('#user-group-descriptions li').hide();
                    $('#user-group-pwrules li').hide();
                    $('#user-group-' + $(this).val()).show();
                    $('#user-group-pw-' + $(this).val()).show();
                });
        }
    }
}

export default AccountCreate;
