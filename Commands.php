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
        $this->wc->wp->sendMessage($from, "-[Wachan]: You have been registered as " . $anonName . ". To change your name use !alias.");

        $this->wc->users[(string) $from] = array('alias' => $anonName);
    }

    function Ping($wc, $from, $params)
    {
        $wc->wp->sendMessage($from, "Pong");
    }

    function Alias($wc, $from, $params)
    {
        $this->wc->users[$from]['alias'] = $params[1];
        $this->wc->wp->sendMessage($from, "-[Wachan]: Your alias has been changed to ".$params[1]);
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
