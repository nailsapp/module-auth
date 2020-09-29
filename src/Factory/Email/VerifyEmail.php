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

    public function getTestData(): array
    {
        // TODO: Implement getTestData() method.
        return [];
    }
}
