<?php

namespace Nails\Auth\Factory\Email;

use Nails\Email\Factory\Email;

class NewUser extends Email
{
    /**
     * The email's type
     *
     * @var string
     */
    protected $sType = 'new_user';

    // --------------------------------------------------------------------------

    public function getTestData(): array
    {
        // TODO: Implement getTestData() method.
        return [];
    }
}
