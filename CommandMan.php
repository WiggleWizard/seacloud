<?php

class CommandMan
{
    protected $wc = null;

    var $directive = "!";

    function __construct($wc)
    {
        $this->wc = $wc;

        $this->wc->Log("Command Manager online");
    }

    function ParseMessage($str, $from)
    {
        // If the first character of the string matches the directive
        if(substr($str, 0, 1) == $this->directive)
        {
            $cmd = substr($str, 1);

            if($cmd == "register")
            {
                $this->wc->wp->sendMessage($from, "You have been registered");
            }
            else
                return 2;

            return 1;
        }
        else
            return 0;
    }
}

?>
