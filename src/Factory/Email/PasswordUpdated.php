<?php

namespace Nails\Auth\Factory\Email;

use Nails\Email\Factory\Email;

class PasswordUpdated extends Email
{
    /**
     * The email's type
     *
     * @var string
     */
    protected $sType = 'password_updated';

    // --------------------------------------------------------------------------

    public function getTestData(): array
    {
        // TODO: Implement getTestData() method.
        return [];
    }
}
