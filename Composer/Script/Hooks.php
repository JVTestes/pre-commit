<?php

namespace PreCommit\Composer\Script;

use Composer\Script\Event;

class Hooks
{
    public static function config()
    {
        $xml = null;
        $config = __DIR__. "/../../../../../config.xml";
        if (!file_exists($config)) {
            throw new \Exception(sprintf('Configuration file not found!'));
        }

        $xml = simplexml_load_file($config);
        if (!isset($xml->dir)) {
            throw new \Exception(sprintf('Configuration node "dir" not found!'));
        }

        if (!isset($xml->dir->git)) {
            throw new \Exception(sprintf('Configuration node "git" not found!'));
        }

        return $xml;
    }

    public static function preHooks(Event $event)
    {
        $io = $event->getIO();
        $gitHook = strval(static::config()->dir->git).
            DIRECTORY_SEPARATOR.'hooks'.
            DIRECTORY_SEPARATOR.'pre-commit';

        if (file_exists($gitHook)) {
            unlink($gitHook);
            $io->write('<info>Pre-commit removed!</info>');
        }

        return true;
    }

    public static function postHooks(Event $event)
    {
        $io = $event->getIO();
        $gitHook = strval(static::config()->dir->git).
            DIRECTORY_SEPARATOR.'hooks'.
            DIRECTORY_SEPARATOR.'pre-commit';
        
        $docHook = strval(static::config()->dir->vendor).
            DIRECTORY_SEPARATOR.'jv-testes'.
            DIRECTORY_SEPARATOR.'pre-commit'.
            DIRECTORY_SEPARATOR.'pre-commit';
        
        if (file_exists($docHook)) {
            unlink($docHook);
        }
        
        $hook = fopen($docHook, 'w+');
        fwrite($hook, static::createHook());
        fclose($hook);

        copy($docHook, $gitHook);
        chmod($gitHook, 0777);

        $io->write('<info>Pre-commit created!</info>');

        return true;
    }

    protected static function createHook()
    {
        $load = strval(static::config()->dir->vendor).DIRECTORY_SEPARATOR.'autoload.php';

        $hook = <<< EOT
#!/usr/bin/php

<?php

require_once "$load";

use PreCommit\Composer\Script\CodeQualityTool;

\$console = new CodeQualityTool('Code Quality Tool', '1.0.0');
\$console->run();

EOT;

        return $hook;
    }
}

