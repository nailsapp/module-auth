<?php

namespace Nails\Auth\Console\Command\User;

use Nails\Console\Command\Base;
use Nails\Environment;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base
{
    protected function configure()
    {
        $this->setName('make:user');
        $this->setDescription('Creates a new super user');

        $this->addOption(
            'defaults',
            'd',
            InputOption::VALUE_NONE,
            'Use Default Values'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param  InputInterface $oInput The Input Interface provided by Symfony
     * @param  OutputInterface $oOutput The Output Interface provided by Symfony
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $oOutput->writeln('');
        $oOutput->writeln('<info>---------------</info>');
        $oOutput->writeln('<info>Nails User Tool</info>');
        $oOutput->writeln('<info>---------------</info>');

        // --------------------------------------------------------------------------

        if (!defined('APP_PRIVATE_KEY')) {
            $oOutput->writeln('<error>APP_PRIVATE_KEY is not defined; does Nails need installed?</error>');

            return $this->abort();
        }

        if (!defined('DEPLOY_PRIVATE_KEY')) {
            $oOutput->writeln('<error>DEPLOY_PRIVATE_KEY is not defined; does Nails need installed?</error>');

            return $this->abort();
        }

        // --------------------------------------------------------------------------

        //  Setup Factory - config files are required prior to set up
        Factory::setup();

        // --------------------------------------------------------------------------

        //  Check environment
        if (Environment::is('PRODUCTION')) {

            $oOutput->writeln('');
            $oOutput->writeln('--------------------------------------');
            $oOutput->writeln('| <info>WARNING: The app is in PRODUCTION.</info> |');
            $oOutput->writeln('--------------------------------------');
            $oOutput->writeln('');

            if (!$this->confirm('Continue with user generation?', true)) {
                return $this->abort(static::EXIT_CODE_SUCCESS);
            }
        }

        // --------------------------------------------------------------------------

        //  Detect super group ID
        $oDb     = Factory::service('ConsoleDatabase', 'nailsapp/module-console');
        $oResult = $oDb->query(
            'SELECT id, label FROM `' . NAILS_DB_PREFIX . 'user_group` WHERE `acl` LIKE \'%"admin:superuser"%\' LIMIT 1'
        );
        if (!$oResult->rowCount()) {
            throw new \Exception('Could not find a group with superuser permissions.');
        }
        $oGroup = $oResult->fetchObject();

        // --------------------------------------------------------------------------

        //  Are we using defaults?
        $bDefaults = $oInput->getOption('defaults');
        if ($bDefaults) {
            $aUser = $this->getDefaultUser();
        } else {
            $oOutput->writeln('');
            $aUser = [
                'first_name' => '',
                'last_name'  => '',
                'username'   => '',
                'email'      => '',
                'password'   => '',
            ];
        }

        //  Ask for any fields which are empty
        foreach ($aUser as $sField => &$sValue) {
            if (empty($sValue)) {
                $sField = ucwords(strtolower(str_replace('_', ' ', $sField)));
                $sError = '';
                do {
                    $sValue = $this->ask($sError . $sField . ':', '');
                    $sError = '<error>Please specify</error> ';
                } while (empty($sValue));
            }
        }
        unset($sValue);

        //  Confirm
        $oOutput->writeln('');
        $oOutput->writeln('OK, here\'s what\'s going to happen:');
        $oOutput->writeln('');
        $oOutput->writeln('Create a user with the following details:');
        $oOutput->writeln('');
        $oOutput->writeln(' - ' . str_pad('Group ID:', 11) . ' <comment>' . $oGroup->id . ' (' . $oGroup->label . ')</comment>');
        foreach ($aUser as $sField => $sValue) {
            $sField = ucwords(strtolower(str_replace('_', ' ', $sField)));
            $oOutput->writeln(' - ' . str_pad($sField . ':', 11) . ' <comment>' . $sValue . '</comment>');
        }
        $oOutput->writeln('');

        //  Execute
        if (!$this->confirm('Continue?', true, $oInput, $oOutput)) {
            return $this->abort();
        }

        // --------------------------------------------------------------------------

        $oOutput->writeln('');
        $oOutput->write('Creating User... ');
        $this->createUser($aUser, $oGroup->id);
        $oOutput->writeln('<comment>done!</comment>');

        // --------------------------------------------------------------------------

        //  Cleaning up
        $oOutput->writeln('');
        $oOutput->writeln('<comment>Cleaning up...</comment>');

        // --------------------------------------------------------------------------

        //  And we're done
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');

        return self::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /*
     * Get the default user as defined in the tool's config file (at ~/.nails)
     */
    private function getDefaultUser()
    {
        $oDefault    = new \stdClass();
        $sConfigFile = $_SERVER['HOME'] . '/.nails';
        if (file_exists($sConfigFile)) {
            $sJson = file_get_contents($sConfigFile);
            $oJson = json_decode($sJson);
            if (!empty($oJson->{'nailsapp/module-auth'}->default_user)) {
                $oDefault = $oJson->{'nailsapp/module-auth'}->default_user;
            }
        }

        return [
            'first_name' => !empty($oDefault->first_name) ? $oDefault->first_name : '',
            'last_name'  => !empty($oDefault->last_name) ? $oDefault->last_name : '',
            'username'   => !empty($oDefault->username) ? $oDefault->username : '',
            'email'      => !empty($oDefault->email) ? $oDefault->email : '',
            'password'   => !empty($oDefault->password) ? $oDefault->password : '',
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Create the user
     *
     * @param array $aUser The details to create the user with
     * @return void
     */
    private function createUser($aUser, $iGroupId)
    {
        //  Get the database
        $oDb = Factory::service('ConsoleDatabase', 'nailsapp/module-console');

        //  Test username/email for duplicates
        $oStatement = $oDb->prepare('
                SELECT COUNT(*) total FROM`' . NAILS_DB_PREFIX . 'user`
                WHERE
                    `username` = :username
            ');
        $oStatement->execute(
            [
                'username' => $aUser['username'],
            ]
        );

        $oResult = $oStatement->fetchObject();
        if ((int) $oResult->total > 0) {
            throw new \Exception('Username "' . $aUser['username'] . '" is already in use.');
        }

        $oStatement = $oDb->prepare('
                SELECT COUNT(*) total FROM`' . NAILS_DB_PREFIX . 'user_email`
                WHERE
                    `email` = :email
            ');
        $oStatement->execute(
            [
                'email' => $aUser['email'],
            ]
        );

        $oResult = $oStatement->fetchObject();
        if ((int) $oResult->total > 0) {
            throw new \Exception('Email "' . $aUser['email'] . '" is already in use.');
        }

        //  Correctly encode the password
        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $oPassword      = $oPasswordModel->generateHashObject($aUser['password']);

        // --------------------------------------------------------------------------

        //  Create the main record
        try {

            $oStatement = $oDb->prepare('
                INSERT INTO `' . NAILS_DB_PREFIX . 'user`
                (
                    `group_id`,
                    `ip_address`,
                    `last_ip`,
                    `username`,
                    `password`,
                    `password_md5`,
                    `password_engine`,
                    `salt`,
                    `created`,
                    `first_name`,
                    `last_name`
                )
                VALUES
                (
                    :group_id,
                    :ip_address,
                    :last_ip,
                    :username,
                    :password,
                    :password_md5,
                    :password_engine,
                    :salt,
                    NOW(),
                    :first_name,
                    :last_name
                );
            ');

            $oStatement->execute(
                [
                    'group_id'        => $iGroupId,
                    'ip_address'      => '127.0.0.1',
                    'last_ip'         => '127.0.0.1',
                    'username'        => $aUser['username'],
                    'password'        => $oPassword->password,
                    'password_md5'    => $oPassword->password_md5,
                    'password_engine' => $oPassword->engine,
                    'salt'            => $oPassword->salt,
                    'first_name'      => $aUser['first_name'],
                    'last_name'       => $aUser['last_name'],
                ]
            );

            $iUserId = $oDb->lastInsertId();

            //  Update the main record's id_md5 value
            $oDb->query('UPDATE `' . NAILS_DB_PREFIX . 'user` SET `id_md5` = MD5(`id`) WHERE `id` = ' . $iUserId . ';');

            //  Create the user meta record
            $oStatement = $oDb->prepare('
                INSERT INTO `' . NAILS_DB_PREFIX . 'user_meta_app`
                (
                    `user_id`
                )
                VALUES
                (
                    :user_id
                );
            ');

            $oStatement->execute(
                [
                    'user_id' => $iUserId,
                ]
            );

            //  Create the user email record
            $oStatement = $oDb->prepare('
                INSERT INTO `' . NAILS_DB_PREFIX . 'user_email`
                (
                    `user_id`,
                    `email`,
                    `code`,
                    `is_verified`,
                    `is_primary`,
                    `date_added`,
                    `date_verified`
                )
                VALUES
                (
                    :user_id,
                    :email,
                    :code,
                    1,
                    1,
                    NOW(),
                    NOW()
                );
            ');

            $oStatement->execute(
                [
                    'user_id' => $iUserId,
                    'email'   => $aUser['email'],
                    'code'    => $oPasswordModel->salt(),
                ]
            );

        } catch (\Exception $e) {
            if (!empty($iUserId)) {
                $oDb->query('DELETE FROM `' . NAILS_DB_PREFIX . 'user` WHERE `id` = ' . $iUserId);
            }
            throw new \Exception($e->getMessage());
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Performs the abort functionality and returns the exit code
     *
     * @param  array $aMessages The error message
     * @param  integer $iExitCode The exit code
     * @return int
     */
    protected function abort($iExitCode = self::EXIT_CODE_FAILURE, $aMessages = [])
    {
        $aMessages[] = 'Aborting user creation';
        if (!empty($this->oDb) && $this->oDb->isTransactionRunning()) {
            $aMessages[] = 'Rolling back database';
            $this->oDb->transactionRollback();
        }

        return parent::abort($iExitCode, $aMessages);
    }
}
