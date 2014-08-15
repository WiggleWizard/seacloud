<?php

require_once 'libs/WhatsAPI/src/whatsprot.class.php';
//require 'libs/WhatsAPI/src/events/WhatsAppEventListenerBase.php';

require 'ConfigParser.php';
require 'CommandMan.php';

class Wachan
{
    // --- Object
    var $configPath;
    var $config;
    var $wp;
    var $processNodeBind;
    var $cmdMan;
    var $lastPongTime = 0; // Unix time

    // --- User
    var $users = array(); // Holds user information like alias etc

    // --- Fiddles
    var $pongInterval = 20; // In seconds

    function __construct()
    {
        set_time_limit(0); // Bypass the INI settings
        date_default_timezone_set("Europe/London"); // WhatsAPI moans about not having this
    }

    function Begin()
    {
        $this->GetArgs();
        $this->ReadConfig();

        $this->wp = new WhatsProt($this->config->get("number"), $this->config->get("id"), $this->config->get("nick"), false);

        // Attempt to connect then login, continue from there
        if($this->Connect())
            if(!$this->Login())
                exit();

        // Bind message recieve so we can parse user input from Whatsapp
        $this->BindMessageRX();

        // Construct a Command Manager
        $this->cmdMan = new CommandMan($this);

        // Tell the owner that his service is online
        $this->NotifyOwner("-[Wachan]: Service successfully started");

        // Entering the main loop here, we are first sending all pooled TX messages
        // using pollMessage() and then we are asking to pool RX messages with
        // getMessages().
        //
        // We are also going to send a pong to the Whatsapp servers to indicate that
        // our service connection is still alive so Whatsapp doesn't automatically
        // time us out and disconnects us.
        while(1)
        {
            try
            {
                $this->wp->pollMessage();
                $this->wp->getMessages();
            }
            catch(Exception $e)
            {
                echo("Error\n");
                exit();
            }

            // Here's where the magic keepalive happens
            if($this->lastPongTime == 0 || time() - $this->pongInterval >= $this->lastPongTime)
            {
                $this->wp->sendPong(rand(0, 10000));
                $this->lastPongTime = time();
            }
        }
    }

    /**
     * Attempt to connect to the Whatsapp servers.
     *
     * @return bool False if connection failed
     */
    private function Connect()
    {
        $this->Log("Connecting to Whatsapp serverss");

        try
        {
            $this->wp->connect();
        }
        catch(Exception $e)
        {
            $this->ErrorLog("Connection failed");
            return false;
        }

        $this->Log("Connection success");
        return true;
    }

    /**
     * Attempt to login.
     */
    private function Login()
    {
        $this->Log("Logging in on " . $this->config->get("number"));

        try
        {
            $this->wp->loginWithPassword($this->config->get("pass"));
        }
        catch(Exception $e)
        {
            $this->LogError("Login failed, check your details");
            return false;
        }

        $this->Log("Login successful");
        return true;
    }

    /**
     * This humanizes the connection to the Whatsapp service, convincing the server
     * that this is a human connecting, minimizing the chances of being disconnected
     * and/or banned.
     */
    private function Humanize()
    {
        $this->Log("Humanizing connection");

        $contacts = array($this->config->get("owner"));
        $this->wp->sendSync($contacts);
        $this->wp->sendClientConfig();
        // $this->wp->sendSetProfilePicture("venom.jpg");
    }

    /**
     * Bind the message recieve event function.
     */
    function BindMessageRX()
    {
        $this->processNodeBind = new ProcessNode($this, $this->wp);
        $this->wp->setNewMessageBind($this->processNodeBind);
    }

    /**
     * Gets the args of the terminal.
     *
     * -c <path>|<file> Config that's read
     */
    private function GetArgs()
    {
        global $argc, $argv;

        for($i = 0; $i < $argc; $i++)
        {
            // Gets the config specified
            if($argv[$i] == "-c")
                $this->configPath = $argv[$i + 1];
        }
    }

    /**
     * Reads the config file that was specified when getArgs() is run.
     */
    private function ReadConfig()
    {
        if($this->configPath == null)
        {
            $this->LogError("You must run getArgs() before attempting to read config");
            exit();
        }

        $this->Log("Reading Config: " . $this->configPath);
        $this->config = new ConfigParser($this->configPath);
    }


    ////////////////
    // FUNCTIONAL //
    ////////////////

    /**
     * Messages the owner of the service on Whatsapp.
     */
    function NotifyOwner($str)
    {
        if($this->wp)
        {
            $this->Log("Telling owner: " . $str);
            $this->wp->sendMessage($this->config->get("owner"), $str);
            while($this->wp->pollMessage());
        }
    }

    function SetStatus($str)
    {
        if($this->wp)
        {
            $this->wp->sendStatusUpdate($str);
        }
    }


    ///////////////////////
    // LOGGING FUNCTIONS //
    ///////////////////////

    function Log($str)
    {
        echo("[\e[32m+\033[0m] " . $str . "\n");
    }

    function LogWarn($str)
    {
        echo("[\e[33m+\033[0m] " . $str . "\n");
    }

    function LogError($str)
    {
        echo("[\e[31m-\033[0m] " . $str . "\n");
    }
}

$wc = new Wachan();
$wc->Begin();

class ProcessNode
{
    protected $wp = false; // WhatsAPI object
    protected $wc = false; // Wachan object

    public function __construct($wc, $wp)
    {
        $this->wp = $wp;
        $this->wc = $wc;
    }

    public function process($node)
    {
        // Get the text from the message
        $text   = $node->getChild('body');
        $text   = $text->getData();
        $notify = $node->getAttribute("notify");
        $from   = explode("@", $node->getAttribute("from"))[0];

        echo "- ".$notify." [" . $from . "] @ ".date('H:i').": ".$text."\n";

        $cmdResult = $this->wc->cmdMan->ParseMessage($text, $from);

        if($cmdResult == 0)
            $this->wp->sendMessage($from, "You sent a normal message");
        if($cmdResult == 2)
            $this->wp->sendMessage($from, "Command does not exist");

    }
}
