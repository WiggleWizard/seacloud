<?php

class CommandMan
{
    protected $wc       = null;
    protected $commands = null;

    var $directive = ".";

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
            $cmdLen = strpos($str, " ");
            $cmdLen = ($cmdLen == false ? strlen($str) - 1 : $cmdLen - 1);
            $cmd    = substr($str, 1, $cmdLen);

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
        }
        else
            return 0; // Not even a command
    }
}

?>
