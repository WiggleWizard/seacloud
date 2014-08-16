<?php

class Commands
{
    var $wc;
    var $commands = array(); // Array of all registered commands

    function __construct($wc)
    {
        $this->wc = $wc;

        $this->RegisterCommand("ping", "Ping", 0);
        $this->RegisterCommand("alias", "Alias", 1);
    }

    function GetCommands()
    {
        return $this->commands;
    }

    function Ping($wc, $from, $params)
    {
        $wc->wp->sendMessage($from, "Pong");
    }

    function Alias($wc, $from, $params)
    {
        $this->wc->users[$from]['alias'] = $params[1];
        $this->wc->wp->sendMessage("-[Wachan]: Your alias has been changed to ".$params[1]);
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
