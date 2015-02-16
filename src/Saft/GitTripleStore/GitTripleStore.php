<?php
namespace Saft\GitTripleStore;

use Saft\StoreInterface\AbstractPatternFragmentTripleStore;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Exception;
use LogicException;
use Filicious\Filesystem;
use Filicious\Local\LocalAdapter;
use Filicious\File;

final class GitTripleStore extends AbstractPatternFragmentTripleStore
{
    private $log;
    private $config;
    private $fileSystem;
    private $graphManager;
    private $initialized;

    public function __construct(Config $config)
    {
        if (is_null($config)) {
            throw new \InvalidArgumentException('$config is null');
        }
        $this->log = new Logger('GitTripleStore');
        $this->log->pushHandler(new StreamHandler('php://output'));
        $this->config = $config;
        $this->fileSystem = new Filesystem(new LocalAdapter(
            $config->workingDirectory));
        $this->graphManager = new GraphManager($config->workingDirectory);
        $this->initialized = false;
    }

    public function intialize() {
        if ($this->initialized) {
            return;
        }
        
        $this->getOrCreateWorkingDirectory();
        $storeFile = $this->fileSystem->getFile('.store');
        if ($storeFile->exists()) {
            $this->graphManager->loadMapping($storeFile);
            $this->log->info('.store file loaded');
        } else {
            $this->graphManager->saveMapping($storeFile);
            $this->log->info('Created .store file');
        }
        
        $this->initialized = true;
        $this->log->info('Initialized');
    }
    
    private function ensureInitialized() {
        if (!$this->initialized) {
            throw new LogicException('Not initialized');
        }
    }
    
    public function getStoreInformation()
    {
        throw new Exception('Method not implemented');
    }

    public function getAvailableGraphs()
    {
        $this->ensureInitialized();
        return $this->graphManager->getAvailableGraphs();
    }

    public function getDefaultGraph()
    {
        throw new Exception('Method not implemented');
    }

    public function addMultipleStatements(array $Statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function deleteMultipleStatements(array $Statements, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }

    public function getMatchingStatements(array $Statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function hasMatchingStatement($Statement, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }

    private function getOrCreateWorkingDirectory() {
        $workingDir = $this->fileSystem->getFile('.');
        if ($workingDir->exists()) {
            $this->ensureWorkingDirReadability();
            return $workingDir;
        }
        $workingDir->createDirectory(true);
        $this->log->info('Created working directory');
        if (!$workingDir->exists()) {
            throw new Exception('Unable to create working directory');
        } else if (!$workingDir->isReadable()) {
            throw new Exception('Created working directory is not readable');
        }
        return $workingDir;
    }

    private function ensureWorkingDirReadability() {
        $workingDir = $this->fileSystem->getFile('.');
        if (!$workingDir->exists()) {
            throw new Exception('Working Directory does not exist');
        } else if (!$workingDir->isDirectory()) {
            throw new Exception('Working Directory is not directory');
        } else if (!$workingDir->isReadable()) {
            throw new Exception('Working Directory is not readable');
        }
    }
}
