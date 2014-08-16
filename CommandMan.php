<?php

class CommandMan
{
    protected $wc       = null;
    protected $commands = null;

    var $directive = "!";

    function __construct($wc, $commands)
    {
        $this->wc       = $wc;
        $this->commands = $commands;

        $this->wc->Log("Command Manager online");
    }

    /**
     * Parses the message and checks if it's a command or not. If it's a command
     * then attempt to find and execute the corresponding registered command.
     *
     * @param string     $str  Message
     * @param string/int $from From (phone number)
     */
    function ParseMessage($str, $from)
    {
        // If the first character of the string matches the directive
        if(substr($str, 0, 1) == $this->directive)
        {
            // We split out the command from the potential parameters.
            $cmdEnd = strpos($str, " ") - 1;
            $cmdEnd = ($cmdEnd == false ? strlen($str) - 1 : $cmdEnd);
            $cmd    = substr($str, 1, $cmdEnd);

            // Do a loop through all the registered commands and find the suitable one
            // to execute.
            foreach($this->commands->GetCommands() as $command)
            {
                if($command['command'] == $cmd)
                {
                    // Grab the params and split them up according to the command
                    // specs.
                    $params = explode(" ", $str, $command['argc'] + 1);

                    // Execute the corresponding command bind.
                    call_user_func_array(array($this->commands, $command['bind']), array($this->wc, $from, $params));

                    return 1;
                }
            }

            return 2; // No such command

            // if($cmd == "register")
            // {
            //     $anonName = "Anon#" . rand(0, 100000);
            //     $this->wc->wp->sendMessage($from, "-[Wachan]: You have been registered as " . $anonName . ". To change your name use !alias.");
            //
            //     $this->wc->users[(string) $from] = array('alias' => $anonName);
            // }
            // else if($cmd == "alias")
            // {
            //
            // }
            // else
            //     return 2;
            //
            // return 1;
        }
        else
            return 0; // Not even a command
    }
}

?>