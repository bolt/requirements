Bolt Requirements
=================

PHP and system requirement definitions for Bolt.

Using
-----

```php
<?php

use Bolt\Requirement\BoltRequirements;
use Symfony\Requirements\Requirement;

$requires = new BoltRequirements('/path/to/bolt');

/** @var Requirement $require */
foreach ($requires->getFailedRequirements() as $require) {
    echo $require->getTestMessage(), PHP_EOL;
    echo $require->getHelpText(), PHP_EOL;
}

/** @var Requirement $require */
foreach ($requires->getFailedRecommendations() as $require) {
    echo $require->getTestMessage(), PHP_EOL;
    echo $require->getHelpText(), PHP_EOL;
}
```
