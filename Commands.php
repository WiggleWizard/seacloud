<?php

class Commands
{
    var $sc;
    var $commands = array(); // Array of all registered commands

    function __construct($sc)
    {
        $this->wc = $sc;

        $this->RegisterCommand("join", "Join", 0);
        $this->RegisterCommand("ping", "Ping", 0);
        $this->RegisterCommand("alias", "Alias", 1);
        $this->RegisterCommand("invite", "Invite", 2);
    }

    function GetCommands()
    {
        return $this->commands;
    }

    function Join($sc, $from, $params)
    {
        $anonName = "Anon#" . rand(0, 100000);
        $this->wc->SendSystemMessageTo("You have joined as " . $anonName . ". To change your name use .alias", $from);

        // Insert the user into the 'registered' users array
        $this->wc->users[(string) $from] = array('alias' => $anonName);

        // Breadcast the registration across the chan
        $this->wc->BroadcastSystemMessageExcl($anonName . " has joined the channel", array($from));
    }

    function Ping($sc, $from, $params)
    {
        $sc->wp->sendMessage($from, "Pong");
    }

    function Alias($sc, $from, $params)
    {
        // Check to see if it's a valid name or not
        if($params[1] == "")
        {
            $this->wc->SendSystemMessageTo("Illegal name", $from);
            return false;
        }

        $oldName = $this->wc->users[$from]['alias'];

        $this->wc->users[$from]['alias'] = $params[1];
        $this->wc->SendSystemMessageTo("Your alias has been changed to ".$params[1], $from);

        $this->wc->BroadcastSystemMessageExcl($oldName . " has changed his name to " . $params[1], array($from));
    }

    function Invite($sc, $from, $params)
    {
        if(sizeof($params) > 2)
        {

        }
        else
        {
            $this->wc->SendSystemMessageTo("Usage: .invite <number> <message>", $from);
        }
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
