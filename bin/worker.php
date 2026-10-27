<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use Jmr\Domain\ImportWorker;
use Jmr\Domain\XmlImporter;

$processed = (new ImportWorker(new XmlImporter()))->run();
echo "Processed {$processed} import batch(es).\n";
