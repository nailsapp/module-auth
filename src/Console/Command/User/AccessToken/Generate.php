<?php

namespace Nails\Auth\Console\Command\User\AccessToken;

use Nails\Auth\Exception\Console\UserNotFoundException;
use Nails\Auth\Model\User;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Reset
 *
 * @package Nails\Auth\Console\Command\User\AccessToken
 */
class Generate extends Base
{
    /**
     * Configures the command
     */
    protected function configure()
    {
        $this->setName('user:accesstoken:generate')
            ->setDescription('Generate an access token for a user')
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'The users ID, email, or username'
            )
            ->addOption(
                'label',
                'l',
                InputOption::VALUE_OPTIONAL,
                'The token\'s label'
            )
            ->addOption(
                'scope',
                's',
                InputOption::VALUE_OPTIONAL,
                'The token\'s scope'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $this->banner('Genenrate an Access Token');

        // --------------------------------------------------------------------------

        $sUser  = $this->oInput->getOption('user');
        $sLabel = $this->oInput->getOption('label');
        $sScope = $this->oInput->getOption('scope');

        // --------------------------------------------------------------------------

        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', 'nails/module-auth');
        /** @var User\AccessToken $oUserPasswordModel */
        $oUserAccessTokenModel = Factory::model('UserAccessToken', 'nails/module-auth');

        // --------------------------------------------------------------------------

        if (is_numeric($sUser)) {
            $oUser = $oUserModel->getById($sUser);
        } else {
            $oUser = $oUserModel->getByEmail($sUser);
            if (empty($oUser)) {
                $oUser = $oUserModel->getByUsername($sUser);
            }
        }

        if (empty($oUser)) {
            throw new UserNotFoundException('Could not find a user by ID, email, or username "' . $sUser . '"');
        }

        // --------------------------------------------------------------------------

        //  Confirm
        $oOutput->writeln('OK, here\'s what\'s going to happen:');
        $oOutput->writeln('');
        $oOutput->writeln('- An access token will be generated for user #' . $oUser->id . ' ' . $oUser->first_name . ' ' . $oUser->last_name);
        $oOutput->writeln('');
        if (!$this->confirm('Continue?', true)) {
            return self::EXIT_CODE_FAILURE;
        }

        // --------------------------------------------------------------------------

        $oAccessToken = $oUserAccessTokenModel->create([
            'user_id' => $oUser->id,
            'scope'   => $sScope,
            'label'   => $sLabel,
        ]);

        if (!$oAccessToken) {
            $this->error(array_filter([
                'Failed to generate access token',
                $oUserAccessTokenModel->lastError(),
            ]));
            return self::EXIT_CODE_FAILURE;
        }

        $oOutput->writeln('');
        $oOutput->writeln('Access Token: <info>' . $oAccessToken->token . '</info>');
        $oOutput->writeln('Expires:      <info>' . $oAccessToken->expires . '</info>');
        $oOutput->writeln('');

        // --------------------------------------------------------------------------

        //  And we're done
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');
        $oOutput->writeln('');

        return self::EXIT_CODE_SUCCESS;
    }
}
