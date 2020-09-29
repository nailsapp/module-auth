<?php

namespace Nails\Auth\Factory\Email;

use Nails\Email\Factory\Email;

class ForgottenPassword extends Email
{
    /**
     * The email's type
     *
     * @var string
     */
    protected $sType = 'forgotten_password';

    // --------------------------------------------------------------------------

    public function getTestData(): array
    {
        // TODO: Implement getTestData() method.
        return [];
    }
}
