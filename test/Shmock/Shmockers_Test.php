<?php

namespace Shmock;

require_once 'src/Shmock/Shmock.php';

class Shmockers_Test extends \PHPUnit_Framework_TestCase
{
    use Shmockers;

    public function test_this_test_can_use_the_shmock_helper_methods()
    {

        $shmock_email_service = $this->shmock('Shmock\Email_Service', function ($user) {
            $user->send('user@gmail.com', $this->stringContains("a message"));
        });

        $user = new User($shmock_email_service);
        $user->set_email_address('user@gmail.com');
        $user->send_email("This is a message");
    }
}

class User
{
    private $email_address;
    private $email_service;

    public function __construct($email_service)
    {
        $this->email_service = $email_service;
    }

    public function set_email_address($email)
    {
        $this->email_address = $email;
    }

    public function send_email($message)
    {
        $this->email_service->send($this->email_address, $message);
    }
}

class Email_Service
{
    public function send($email, $message)
    {
        // no-op, just for testing
    }
}
