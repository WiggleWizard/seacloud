<?php

require_once 'libs/WhatsAPI/src/whatsprot.class.php';
require_once 'libs/phpseclib/phpseclib/Crypt/AES.php';
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
    var $cipher; // Used to encrypt the persistence file
    var $cmdMan;
    var $commands;
    var $lastPongTime = 0; // Unix time
    var $lastPersistTime = 0; // Last time data was saved to persistence
    var $poolingOffline = false; // Triggered when pooling offline messages

    // --- User
    var $users = array(); // Holds user information like alias etc

    // --- Fiddles
    var $pongInterval = 20; // In seconds
    var $persist = true; // Persistence means that when the service goes down it's in the same state as when it was up
    var $persistenceInterval = 10; // The amount of seconds between each persistent save to flat file

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

        // Bind events
        $this->CommitBinds();

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

        $this->cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
        $this->cipher->setKey($this->config->Get('persistencekey'));

        // Load persisted data
        $this->LoadPersistedData();

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

            // Persist data if enabled
            if($this->lastPersistTime == 0 || time() - $this->persistenceInterval >= $this->lastPersistTime)
            {
                if($this->persist)
                {
                    $this->SavePersistentData();
                    $this->lastPersistTime = time();
                }
            }
        }
    }

    /**
     * Load persistent data from the persistence file
     */
    private function LoadPersistedData()
    {
        if(file_exists("persistence.dat"))
        {
            // Get the contents and decrypt it
            $s  = file_get_contents("persistence.dat");
            $ds = $this->cipher->decrypt($s);

            $this->users = unserialize($ds);
        }
        else
            $this->LogWarn("No persistence data found");
    }

    /**
     * Save data into a persistence file
     */
    private function SavePersistentData()
    {
        // Encrypt the data when writing
        $es = $this->cipher->encrypt(serialize($this->users));
        file_put_contents("persistence.dat", $es);
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
     * Hooks into the events of WhatsAPI.
     */
    private function CommitBinds()
    {
        $this->wp->eventManager()->bind("onGetMessage", array($this, "OnMessageRx"));
        $this->wp->eventManager()->bind("onGetImage", array($this, "OnImageRx"));
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


    ////////////////////////////////////////////////////////////////////////////////////////////////
    // FUNCTIONAL //////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////

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

    /**
     * This is the standard
     * @param [type] $msg  [description]
     * @param [type] $from [description]
     */
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
            if($number != $from && !$this->IsAfk($number))
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
            if(!in_array($number, $excl) && !$this->IsAfk($number))
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

    function IsAfk($number)
    {
        // If it doesn't exist then simply fucking make it. Bam. Magic.
        if(!array_key_exists('afk', $this->users[$number]))
        {
            $this->users[$number]['afk'] = false;
        }

        return $this->users[$number]['afk'];
    }


    ////////////////////////////////////////////////////////////////////////////////////////////
    // LOGGING FUNCTIONS ///////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

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


    //////////////////////////////////////////////////////////////////////////////////////////////////
    // BINDINGS //////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////

    function OnImageRx(
        $phone, // The user phone number including the country code.
        $from, // The sender JID.
        $msgid, // The message id.
        $type, // The message type.
        $time, // The unix time when send message notification.
        $name, // The sender name.
        $size, // The image size.
        $url, // The url to bigger image version.
        $file, // The image name.
        $mimetype, // The image mime type.
        $filehash, // The image file hash.
        $width, // The image width.
        $height, // The image height.
        $thumbnail // The base64_encode image thumbnail.
    )
    {
        $from = explode("@", $from)[0];

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
            {
                // Status checks on the users
                if(!$this->IsAfk($number))
                {
                    $this->wp->sendMessageImage($number, $url, false, $size, $filehash);
                    $this->wp->sendMessage($number, $this->users[$from]['alias'] . " sent an image");
                }
            }
        }

    }

    function OnMessageRx(
        $phone, // The user phone number including the country code.
        $from, // The sender JID.
        $msgid, // The message id.
        $type, // The message type.
        $time, // The unix time when send message notification.
        $name, // The sender name.
        $message // The message.
    )
    {
        $from = explode("@", $from)[0];

        echo "- ".$name." [" . $from . "] @ ".date('H:i').": ".$message."\n";

        // Drop messages that were sent when the service was offline
        if(!$this->poolingOffline)
        {
            $cmdResult = $this->cmdMan->ParseMessage($message, $from);

            if($cmdResult == 0)
                $this->BroadcastMessageFrom($message, $from);
            if($cmdResult == 2)
                $this->wp->sendMessage($from, "Command does not exist");
        }
    }
}

$sc = new Seacloud();
$sc->Begin();
