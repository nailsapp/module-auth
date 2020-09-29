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

    /**
     * Returns test data to use when sending test emails
     *
     * @return array
     */
    public function getTestData(): array
    {
        return [
            'resetUrl'   => 'https://example.com',
            'identifier' => 'user@example.com',
        ];
    }
}
