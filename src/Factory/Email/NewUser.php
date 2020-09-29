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

    /**
     * Returns test data to use when sending test emails
     *
     * @return array
     */
    public function getTestData(): array
    {
        return [
            'admin'     => [
                'id'         => 1,
                'first_name' => 'Jim',
                'last_name'  => 'Jones',
                'group'      => [
                    'id'    => 1,
                    'label' => 'Administrator',
                ],
            ],
            'password'  => random_string(),
            'isTemp'    => true,
            'verifyUrl' => 'https://example.com',
        ];
    }
}
