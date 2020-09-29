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

    /**
     * Returns test data to use when sending test emails
     *
     * @return array
     */
    public function getTestData(): array
    {
        return [
            'ipAddress' => '0.0.0.0',
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }
}
