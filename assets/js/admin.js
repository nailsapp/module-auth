'use strict';

import '../sass/admin.scss';
import AccountCreate from './components/AccountCreate.js';
import AccountEdit from './components/AccountEdit.js';
import AccountMerge from './components/AccountMerge.js';
import Groups from './components/Groups.js';
import SearchUser from './components/SearchUser.js';

(function() {
    new AccountCreate();
    new AccountEdit();
    new AccountMerge();
    new Groups();
    new SearchUser();
})();
