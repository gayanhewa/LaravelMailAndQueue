<?php

use Swift_Mailer as SwiftMailer;
use Swift_SmtpTransport as SmtpTransport;
use Swift_SendmailTransport as SendmailTransport;
use Swift_MailTransport as MailTransport;
use Illuminate\Mail\Transport\MailgunTransport;
use Illuminate\Mail\Transport\MandrillTransport;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Mailer;
use Illuminate\Log\Writer;
use Monolog\Logger;
use Illuminate\Queue\Capsule\Manager as Queue;

/** 
    Most of the code below is reused from a few implementation. 
    Implemntation of the worker can be found here : https://github.com/mattstauffer/IlluminateNonLaravel/blob/master/public/queue/index.php
**/

class Foo
{

    protected $config;

    protected $template;

    protected $smtp;

    public function __construct()
    {
        $this->config = [
                    'driver' => 'sqs',
                    'key' => '',
                    'secret' => '',
                    'queue' => '',
                    'region' => '',
                ];

        $this->template = '/path/to/mail/template.php'; 

        $this->smtp = [
            'username' => 'username',
            'password' => 'password',
            'host' => 'host',
            'port' => 'port'
        ];
    }


    public function emailSend()
    {
        $app = [];

        $logger = new Writer(new Logger('local'));
        // note: make sure log file is writable
        $logger->useFiles('../../logs/laravel.log');
        // chose a transport (SMTP, PHP Mail, Sendmail, Mailgun, Maindrill, Log)
        $transport = SmtpTransport::newInstance($this->smtp['host'], $this->smtp['port']);

        // SMTP specific configuration, remove these if you're not using SMTP
        $transport->setUsername($this->smtp['username']);
        $transport->setPassword($this->smtp['password']);
        $transport->setEncryption(true);
        $swift    = new SwiftMailer($transport);
        $finder   = new FileViewFinder(new Filesystem, [$this->tempalte]);
        $resolver = new EngineResolver;
        // determine which template engine to use
        $resolver->register('php', function()
        {
            return new PhpEngine;
        });
        $view   = new Factory($resolver, $finder, new Dispatcher());
        $mailer = new Mailer($view, $swift);
        $mailer->setLogger($logger);


        try {
            $app = new \Illuminate\Container\Container;

            $app->bindIf('queue', function ($app) {
                $queue = new \Illuminate\Queue\Capsule\Manager($app);

                $queue->addConnection($this->config, 'default');

                $queue->setAsGlobal();

                return $queue->connection();
            }, true);

            $app->bind('encrypter', function() {
                return new Illuminate\Encryption\Encrypter('foobar');
            });
            $app->bind('request', function() {
                return new Illuminate\Http\Request();
            });

            $mailer->setQueue($app['queue']); 
            $mailer->setContainer($app);      
            // pretend method can be used for testing
            $mailer->pretend(false);
            // prepare email view data
            $data = [
                'greeting' => 'You have arrived, girl.',
            ];

            $mailer->queue('general', $data, function ($message) {
                $message->from('noreply@sample.com', 'Code Guy');
                $message->to('gayanhewa@gmail.com', 'Keira Knightley');
                $message->subject('Yo!');
            });
            var_dump('Done');
        }catch(Exception $e) {
           die('Error occured.');
        }
    }
}