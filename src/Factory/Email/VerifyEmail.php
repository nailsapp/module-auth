<?php

namespace Nails\Auth\Factory\Email;

use Nails\Email\Factory\Email;

class VerifyEmail extends Email
{
    /**
     * The email's type
     *
     * @var string
     */
    protected $sType = 'verify_email';

    // --------------------------------------------------------------------------

    /**
     * Returns test data to use when sending test emails
     *
     * @return array
     */
    public function getTestData(): array
    {
        return [
            'verifyUrl' => 'https://example.com',
        ];
    }
}
