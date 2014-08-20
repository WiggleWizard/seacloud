<?php

class Commands
{
    var $sc;
    var $commands = array(); // Array of all registered commands

    function __construct($sc)
    {
        $this->sc = $sc;

        $this->RegisterCommand("join", "Join", 0);
        $this->RegisterCommand("ping", "Ping", 0);
        $this->RegisterCommand("alias", "Alias", 1);
        $this->RegisterCommand("invite", "Invite", 1);
        $this->RegisterCommand("list", "ListUsers", 0); $this->RegisterCommand("online", "ListUsers", 0);
        $this->RegisterCommand("afk", "Afk", 0);
        $this->RegisterCommand("leave", "Leave", 0);
    }

    function GetCommands()
    {
        return $this->commands;
    }

    function Join($sc, $from, $params)
    {
        $anonName = "Anon#" . rand(0, 100000);
        $this->sc->SendSystemMessageTo("You have joined as " . $anonName . ". To change your name use .alias. You can leave at any time by using .leave and if you need to mute the channel simply use .afk", $from);

        // Insert the user into the 'registered' users array
        $this->sc->users[(string) $from] = array('alias' => $anonName);

        // Breadcast the registration across the channel but exclude the person who joined
        $this->sc->BroadcastSystemMessageExcl($anonName . " has joined the channel", array($from));
    }

    function Ping($sc, $from, $params)
    {
        $sc->SendSystemMessageTo("Pong", $from);
    }

    function Alias($sc, $from, $params)
    {
        // Check to see if it's a valid name or not
        if($params[1] == "")
        {
            $this->sc->SendSystemMessageTo("Illegal name", $from);
            return false;
        }

        $oldName = $this->sc->users[$from]['alias'];

        $this->sc->users[$from]['alias'] = $params[1];
        $this->sc->SendSystemMessageTo("Your alias has been changed to ".$params[1], $from);

        $this->sc->BroadcastSystemMessageExcl($oldName . " has changed his name to " . $params[1], array($from));
    }

    function Invite($sc, $from, $params)
    {
        if(sizeof($params) >= 2)
        {
            // Parse the parameter (Useful for copy pasta)
            $number = str_replace(' ', '', $params[1]);
            $number = str_replace('+', '', $number);

            $this->sc->SendSystemMessageTo("You have been invited to chat on Seacloud by "
            . $this->sc->users[$from]['alias'] .
            ". To join just type .join", $number);

            $this->sc->SendSystemMessageTo("You have sent an invite to " . $number, $from);
        }
        else
        {
            $this->sc->SendSystemMessageTo("Usage: .invite <number>", $from);
        }
    }

    function ListUsers($sc, $from, $params)
    {
        $online = "";

        foreach($sc->users as $user)
        {
            $online .= $user['alias'] . ", ";
        }

        $sc->SendSystemMessageTo("Online: " . $online, $from);
    }

    function Afk($sc, $from, $params)
    {
        if(array_key_exists('afk', $sc->users[$from]))
        {
            if($sc->users[$from]['afk'])
            {
                $sc->users[$from]['afk'] = false;
                $sc->SendSystemMessageTo("You have come back from being afk", $from);

                $sc->BroadcastSystemMessageExcl($sc->users[$from]['alias'] . " has come back from being afk", array($from));

                return;
            }
        }

        $sc->users[$from]['afk'] = true;
        $sc->SendSystemMessageTo("You have gone afk", $from);

        $sc->BroadcastSystemMessageExcl($sc->users[$from]['alias'] . " has gone afk", array($from));
    }

    function Leave($sc, $from, $params)
    {
        if($sc->IsJoined($from))
        {
            // We kinda cheating here by broadcasting the message first, but hey, no one will know ;)
            $sc->BroadcastSystemMessageExcl($sc->users[$from] . " has left the channel", array($from));
            unset($sc->users[$from]);
        }
        else
        {
            $sc->SendSystemMessageTo("You cannot leave a channel that you are not connected to", $from);
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
