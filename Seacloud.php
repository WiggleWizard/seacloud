<?php

require_once 'libs/WhatsAPI/src/whatsprot.class.php';
//require 'libs/WhatsAPI/src/events/WhatsAppEventListenerBase.php';

require 'ConfigParser.php';
require 'CommandMan.php';
require 'Commands.php';

class Seacloud
{
    // --- Object
    var $configPath;
    var $config;
    var $wp;
    var $processNodeBind;
    var $cmdMan;
    var $commands;
    var $lastPongTime = 0; // Unix time
    var $poolingOffline = false; // Triggered when pooling offline messages

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

        $this->wp = new WhatsProt($this->config->Get("number"), $this->config->Get("id"), $this->config->Get("nick"), false);

        // Attempt to connect then login, continue from there
        if($this->Connect())
            if(!$this->Login())
                exit();

        // Bind message recieve so we can parse user input from Whatsapp
        $this->BindMessageRX();

        // Construct a Command Manager
        $this->commands = new Commands($this);
        $this->cmdMan   = new CommandMan($this, $this->commands);

        // Pool offline messages before opening up service
        $this->Log("Unpooling offline messages");
        $this->poolingOffline = true;
        while($this->wp->pollMessage());
        $this->poolingOffline = false;
        $this->Log("Done unpooling offline messages");

        // Tell the owner that his service is online
        $this->NotifyOp("*** Seacloud ***\nService successfully started");
        $this->SetStatus("Seacloud chat service. Use .join to join the channel.");

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
        $this->Log("Logging in on " . $this->config->Get("number"));

        try
        {
            $this->wp->loginWithPassword($this->config->Get("pass"));
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
    private function BindMessageRX()
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
     * Messages all the owners of the service on Whatsapp.
     */
    function NotifyOp($str)
    {
        if($this->wp)
        {
            $this->Log("Telling owners: " . $str);

            foreach($this->config->GetArray("owner") as $owner)
            {
                $this->wp->sendMessage($owner, $str);
            }
        }
    }

    function SetStatus($str)
    {
        if($this->wp)
        {
            $this->wp->sendStatusUpdate($str);
        }
    }

    function BroadcastMessageFrom($msg, $from)
    {
        // Check if the user has joined, if not then just ignore his ass
        if(!array_key_exists($from, $this->users))
        {
            $this->wp->sendMessage($from, "*** Seacloud ***\nYou must .join before you can send or recieve messages on this Seacloud");
            return;
        }

        // Broadcast the message across all joined users but make sure to exclude
        // the sender from the queue
        foreach($this->users as $number => $userInfo)
        {
            if($number != $from)
                $this->wp->sendMessage($number, "<" . $this->users[$from]['alias'] . ">\n" . $msg);
        }
    }

    /**
     * Broadcasts a message to every user and excludes the numbers in the $excl array.
     *
     * @param [type] $msg  [description]
     * @param [type] $excl [description]
     */
    function BroadcastSystemMessageExcl($msg, $excl = array())
    {
        foreach($this->users as $number => $userInfo)
        {
            if(!in_array($number, $excl))
                $this->wp->sendMessage($number, "*** Seacloud ***\n" . $msg);
        }
    }

    /**
     * Handles the system wide prepend to the system messages (for consistency).
     *
     * @param [type] $msg [description]
     */
    function SendSystemMessageTo($msg, $to)
    {
        $this->wp->sendMessage($to, "*** Seacloud ***\n" . $msg);
    }

    /**
     * Checks if a user is joined in the users array.
     *
     * @param string/int $number The number to be checked
     */
    function IsJoined($number)
    {
        foreach($this->users as $joinedNo => $userInfo)
        {
            if($joinedNo == $number)
                return true;
        }

        return false;
    }

    function IsOp($number)
    {
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

$wc = new Seacloud();
$wc->Begin();

class ProcessNode
{
    protected $wp = false; // WhatsAPI object
    protected $sc = false; // Seacloud object

    public function __construct($sc, $wp)
    {
        $this->wp = $wp;
        $this->sc = $sc;
    }

    public function process($node)
    {
        // Get the text from the message
        $text    = $node->getChild('body');
        $text    = $text->getData();
        $msgTime = intval($node->getAttribute('t'));
        $waName  = $node->getAttribute("notify");
        $from    = explode("@", $node->getAttribute("from"))[0];

        echo "- ".$waName." [" . $from . "] @ ".date('H:i').": ".$text."\n";

        // Drop messages that were sent when the service was offline
        if(!$this->sc->poolingOffline)
        {
            $cmdResult = $this->sc->cmdMan->ParseMessage($text, $from);

            if($cmdResult == 0)
                $this->sc->BroadcastMessageFrom($text, $from);
            if($cmdResult == 2)
                $this->wp->sendMessage($from, "Command does not exist");
        }

    }
}
