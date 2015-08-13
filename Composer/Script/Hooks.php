<?php

namespace PreCommit\Composer\Script;

use Composer\Script\Event;

$fileDir = dirname(dirname(__FILE__));
$vendorDir = dirname(dirname(dirname(dirname($fileDir))));

define('ROOT_DIR', $vendorDir);

class Hooks
{
    public static function preHooks(Event $event)
    {
        $io = $event->getIO();
        $gitHook = static::gitDir().
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
        $gitHook = static::gitDir().
            DIRECTORY_SEPARATOR.'hooks'.
            DIRECTORY_SEPARATOR.'pre-commit';
        
        $docHook = ROOT_DIR.
            DIRECTORY_SEPARATOR.'vendor'.
            DIRECTORY_SEPARATOR.'jv-testes'.
            DIRECTORY_SEPARATOR.'pre-commit'.
            DIRECTORY_SEPARATOR.'hooks'.
            DIRECTORY_SEPARATOR.'pre-commit';

        symlink($docHook, $gitHook);
        chmod($gitHook, 0777);

        $io->write('<info>Pre-commit created!</info>');

        return true;
    }
    
    public static function gitDir()
    {
        $configXml = ROOT_DIR.DIRECTORY_SEPARATOR.'config.xml';

        if (!file_exists($configXml)) {
            throw new \Exception(sprintf('Configuration file not found!'));
        }

        $xml = simplexml_load_file($configXml);
        if (!isset($xml->dir)) {
            throw new \Exception(sprintf('Configuration node "dir" not found!'));
        }

        if (!isset($xml->dir->git)) {
            throw new \Exception(sprintf('Configuration node "git" not found!'));
        }
        
        return strval($xml->dir->git);
    }
}
