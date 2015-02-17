<?php
namespace Saft\GitTripleStore;

use Saft\StoreInterface\AbstractPatternFragmentTripleStore;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Exception;
use LogicException;
use InvalidArgumentException;
use Filicious\Filesystem;
use Filicious\Local\LocalAdapter;
use Filicious\File;

final class GitTripleStore extends AbstractPatternFragmentTripleStore
{
    private $log;
    private $config;
    private $initialized;

    public function __construct(Config $config)
    {
        if (is_null($config)) {
            throw new InvalidArgumentException('$config is null');
        }
        $this->log = new Logger('GitTripleStore');
        $this->log->pushHandler(new StreamHandler('php://output'));
        $this->config = $config;
        $this->initialized = false;
    }

    public function intialize() {
        if ($this->initialized) {
            return;
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

    public function addMultipleStatements(array $statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function deleteMultipleStatements(array $statements, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }

    public function getMatchingStatements(array $statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function hasMatchingStatement(array $statements, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }
    
    public function featureSupported()
    {
        throw new Exception('Method not implemented');
    }
}
