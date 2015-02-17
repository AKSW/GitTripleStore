<?php
namespace Saft\GitTripleStore;

use EasyRdf\Parser\Ntriples;

class PublicNtriplesParser extends Ntriples
{
    public function publicUnescapeString($str)
    {
        return $this->unescapeString($str);
    }
}