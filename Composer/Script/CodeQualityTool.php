<?php

namespace PreCommit\Composer\Script;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Application;

class CodeQualityTool extends Application
{
    public $output;
    public $input;
    public $config;

    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);
        
        $xml = null;
        $config = __DIR__. "/../../../../../config.xml";
        if (! file_exists($config)) {
            throw new \Exception('Config not found.');
        }
        
        $this->config = simplexml_load_file($config);
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        
        $output->writeln('<fg=white;options=bold;bg=red>Code Quality Tool</fg=white;options=bold;bg=red>');
        $output->writeln('<info>Fetching files</info>');
        $files = $this->extractCommitedFiles();

        $output->writeln('<info>Running PHPLint</info>');
        $phpLint = $this->phpLint($files);
        if ($phpLint !== true) {
            $this->output->writeln($phpLint['file']);
            $this->output->writeln(sprintf('<error>%s</error>', trim($phpLint['error'])));
            throw new \Exception('There are some PHP syntax errors!');
        }

        if (!isset($this->config->run->phpcsfix) || ($this->config->run->phpcsfix == 'true')) {
            $output->writeln('<info>Checking code style</info>');
            $codeStyle = $this->codeStyle($files);
            if ($codeStyle !== true) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($codeStyle)));
                throw new \Exception(sprintf('There are coding standards violations!'));
            }
        }

        $output->writeln('<info>Checking code style with PHPCS</info>');
        $codeStylePsr = $this->codeStylePsr($files);
        if ($codeStylePsr !== true) {
            $this->output->writeln(sprintf('<error>%s</error>', trim($codeStylePsr)));
            throw new \Exception(sprintf('There are PHPCS coding standards violations!'));
        }

        $output->writeln('<info>Checking code style with PHPCS JS</info>');
        $codeStyleJS = $this->codeStyleJS($files);
        if ($codeStyleJS !== true) {
            $this->output->writeln(sprintf('<error>%s</error>', trim($codeStyleJS)));
            throw new \Exception(sprintf('There are PHPCS JS coding standards violations!'));
        }
        
        $output->writeln('<info>Checking code mess with PHPMD</info>');
        $phpmd = $this->phPmd($files);
        if ($phpmd !== true) {
            $this->output->writeln($phpmd['file']);
            $this->output->writeln(sprintf('<error>%s</error>', trim($phpmd['errorOutput'])));
            $this->output->writeln(sprintf('<info>%s</info>', trim($phpmd['error'])));
            throw new \Exception(sprintf('There are PHPMD violations!'));
        }

        if (!isset($this->config->run->phpunit) || ($this->config->run->phpunit == 'true')) {
            $output->writeln('<info>Running unit tests</info>');
            if (!$this->unitTests()) {
                throw new \Exception('Fix the fucking unit tests!');
            }
        }

        $output->writeln('<info>Good job dude!</info>');
    }

    protected function extractCommitedFiles()
    {
        $output = array();
        $rc = 0;

        exec('git rev-parse --verify HEAD 2> /dev/null', $output, $rc);

        $against = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
        if ($rc == 0) {
            $against = 'HEAD';
        }

        exec("git diff-index --cached --name-status $against | egrep '^(A|M)' | awk '{print $2;}'", $output);

        return $output;
    }

    protected function phpLint($files)
    {
        $needle = '/(\.php)|(\.inc)$/';
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $processBuilder = new ProcessBuilder(array('php', '-l', $file));
            $process = $processBuilder->getProcess();
            $process->run();

            if (!$process->isSuccessful()) {
                return ['file' => $file, 'error' => $process->getErrorOutput()];
            }
        }

        return $succeed;
    }

    protected function phPmd($files)
    {
        $needle = '/(\.php)$/';
        $succeed = true;

        $fileRule =  __DIR__. "/../../../../../phpmd.xml";

        $rule = 'codesize,unusedcode,naming';
        if (file_exists($fileRule)) {
            $rule = $fileRule;
        }

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $processBuilder = new ProcessBuilder([
                'php',
                $this->config->dir->vendor.'/bin/phpmd',
                $file,
                'text',
                $rule
            ]);
            $processBuilder->setWorkingDirectory($this->config->dir->root);
            $process = $processBuilder->getProcess();
            $process->run();

            if (!$process->isSuccessful()) {
                return ['file' => $file, 'errorOutput' => $process->getErrorOutput(), 'error' => $process->getOutput()];
            }
        }

        return $succeed;
    }

    protected function unitTests()
    {
        $filePhpunit = __DIR__. "/../../../../../phpunit.xml";

        if (file_exists($filePhpunit)) {
            $processBuilder = new ProcessBuilder(array('php', $this->config->dir->vendor.'/bin/phpunit'));
            $processBuilder->setWorkingDirectory($this->config->dir->root);
            $processBuilder->setTimeout(3600);
            $phpunit = $processBuilder->getProcess();

            $phpunit->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            return $phpunit->isSuccessful();
        }

        $this->output->writeln(sprintf('<fg=yellow>%s</>', 'Not PHPUnit!'));
        return true;
    }

    protected function codeStyle(array $files)
    {
        $needle = '/(\.php)$/';
        $succeed = true;
        
        foreach ($files as $file) {
            $srcFile = preg_match($needle, $file);

            if (!$srcFile) {
                continue;
            }

            $processBuilder = new ProcessBuilder([
                'php',
                $this->config->dir->vendor.'/bin/php-cs-fixer',
                '--dry-run',
                '--diff',
                '--verbose',
                'fix',
                $file,
            ]);

            $processBuilder->setWorkingDirectory($this->config->dir->root);
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run();
            
            if (!$phpCsFixer->isSuccessful()) {
                return $phpCsFixer->getOutput();
            }
        }

        return $succeed;
    }

    protected function codeStylePsr(array $files)
    {
        $succeed = true;
        $needle = '/(\.php)$/';

        $phpcs = __DIR__. "/../../../../../phpcs.xml";

        if (file_exists($phpcs)) {
            $standard = $phpcs;
        } else {
            $standard = 'PSR2';
        }

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $processBuilder = new ProcessBuilder([
                'php',
                $this->config->dir->vendor.'/bin/phpcs',
                '--standard='.$standard,
                $file
            ]);

            $processBuilder->setWorkingDirectory($this->config->dir->root);
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run();

            if (!$phpCsFixer->isSuccessful()) {
                return $phpCsFixer->getOutput();
            }
        }

        return $succeed;
    }

    protected function codeStyleJS(array $files)
    {
        $succeed = true;
        $needle = '/(\.js)$/';

        $phpcsjs =  __DIR__. "/../../../../../phpcsjs.xml";

        $standard = 'squiz';
        if (file_exists($phpcsjs)) {
            $standard = $phpcsjs;
        }

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $processBuilder = new ProcessBuilder([
                'php',
                $this->config->dir->vendor.'/bin/phpcs',
                '--standard='.$standard,
                $file
            ]);

            $processBuilder->setWorkingDirectory($this->config->dir->root);
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run();

            if (!$phpCsFixer->isSuccessful()) {
                return $phpCsFixer->getOutput();
            }
        }

        return $succeed;
    }
}
