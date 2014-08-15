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
                $anonName = "Anon#" . rand(0, 100000);
                $this->wc->wp->sendMessage($from, "-[Wachan]: You have been registered as " . $anonName . ". To change your name use !alias.");

                $this->wc->users[$from] = array('alias' => $anonName);
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
