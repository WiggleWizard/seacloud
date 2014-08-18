<?php

class Commands
{
    var $wc;
    var $commands = array(); // Array of all registered commands

    function __construct($wc)
    {
        $this->wc = $wc;

        $this->RegisterCommand("register", "Register", 0);
        $this->RegisterCommand("ping", "Ping", 0);
        $this->RegisterCommand("alias", "Alias", 1);
    }

    function GetCommands()
    {
        return $this->commands;
    }

    function Register($wc, $from, $params)
    {
        $anonName = "Anon#" . rand(0, 100000);
        $this->wc->SendSystemMessageTo("You have been registered as " . $anonName . ". To change your name use !alias.", $from);

        // Insert the user into the 'registered' users array
        $this->wc->users[(string) $from] = array('alias' => $anonName);

        // Breadcast the registration across the chan
        $this->wc->BroadcastSystemMessageExcl($anonName . " has joined the channel", array($from));
    }

    function Ping($wc, $from, $params)
    {
        $wc->wp->sendMessage($from, "Pong");
    }

    function Alias($wc, $from, $params)
    {
        $oldName = $this->wc->users[$from]['alias'];

        $this->wc->users[$from]['alias'] = $params[1];
        $this->wc->SendSystemMessageTo("Your alias has been changed to ".$params[1], $from);

        $this->wc->BroadcastSystemMessageExcl($oldName . " has changed his name to " . $params[1], array($from));
    }

    /**
     * Registers a new command to the command manager.
     *
     * @param [type] $cmd     [description]
     * @param [type] $binding [description]
     * @param [type] $argc    [description]
     */
    function RegisterCommand($cmd, $binding, $argc)
    {
        array_push($this->commands, array(
            'command' => $cmd,
            'bind'    => $binding,
            'argc'    => $argc
        ));
    }

}

?>
